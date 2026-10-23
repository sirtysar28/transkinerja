<?php

namespace App\Services;

use App\Models\UnitKerja;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Jembatan integrasi DigiTrans (Biro OSDMRB) — satu server cPanel/MariaDB.
 *
 * Kemampuan:
 *  1. cari()          — cari baris akun (JOIN data pegawai) di database DigiTrans
 *  2. autentikasi()   — verifikasi kredensial terhadap DigiTrans lalu
 *                       buat/perbarui (provision) user lokal secara otomatis
 *  3. sinkronPegawai()— tarik seluruh pegawai DigiTrans → perbarui data
 *                       kepegawaian lokal (nama, NIP, jabatan, unit, pangkat,
 *                       status kepegawaian, TTL, agama, pendidikan, TMT, BUP)
 *
 * Skema asli DigiTrans (terkonfirmasi):
 *   users(id, name, email, password, employee_id→employees, is_active, otp_*)
 *   employees(nip, name, position_name, unit_id→units, rank_id→ranks,
 *             employment_status_id, education_level_id, gender, birth_place,
 *             birth_date, religion, address, phone, tmt_*, retirement_date, …)
 *   units(id, name, code) · ranks(id, name) · employment_statuses(id, name)
 *   education_levels(id, code, name)
 *
 * Kecerdasan skema: join & kolom dibaca dari information_schema — query hanya
 * memakai tabel/kolom yang benar-benar ada, mapping .env yang keliru tidak
 * bikin error (fitur terkait otomatis dilewati).
 *
 * Prinsip aman: TransKinerja hanya MEMBACA DigiTrans (SELECT saja).
 * Role, atasan, dan password lokal dikelola TransKinerja sendiri; status aktif
 * akun mengikuti DigiTrans (master tunggal).
 */
class DigitransService
{
    /** Cache daftar kolom per tabel DigiTrans. */
    private static array $kolomCache = [];

    /** Cache konfigurasi join aktif (select alias + klausul join). */
    private static ?array $joinCache = null;

    /** Integrasi menyala? */
    public static function aktif(): bool
    {
        if (! config('digitrans.aktif')) {
            return false;
        }

        return (bool) config('database.connections.' . config('digitrans.koneksi') . '.database');
    }

    /** Koneksi database DigiTrans. */
    private static function db()
    {
        return DB::connection(config('digitrans.koneksi'));
    }

    // ------------------------------------------------------------------
    // Skema & mapping kolom
    // ------------------------------------------------------------------

    /**
     * Kolom yang BENAR-BENAR ada pada sebuah tabel DigiTrans (via
     * information_schema). Array kosong = skema tak terbaca → jangan difilter.
     */
    public static function kolomTersedia(?string $tabel = null): array
    {
        $tabel ??= config('digitrans.tabel');

        if (isset(self::$kolomCache[$tabel])) {
            return self::$kolomCache[$tabel];
        }

        try {
            $baris = self::db()->select(
                'SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?',
                [config('database.connections.' . config('digitrans.koneksi') . '.database'), $tabel]
            );

            self::$kolomCache[$tabel] = array_map(static fn ($r) => is_object($r) ? ($r->COLUMN_NAME ?? reset($r)) : reset($r), $baris);
        } catch (\Throwable $e) {
            Log::warning("Digitrans: gagal baca skema tabel {$tabel} — " . $e->getMessage());
            self::$kolomCache[$tabel] = [];
        }

        return self::$kolomCache[$tabel];
    }

    /** [tabel, kolom] dari nilai mapping ("employees.nip" → ["employees","nip"]). */
    private static function pecahKolom(string $kunci): array
    {
        $nilai = (string) config("digitrans.kolom.{$kunci}");
        $nilai = trim($nilai);

        if ($nilai === '') {
            return ['', ''];
        }

        return str_contains($nilai, '.')
            ? [substr($nilai, 0, strpos($nilai, '.')), substr($nilai, strpos($nilai, '.') + 1)]
            : [(string) config('digitrans.tabel'), $nilai];
    }

    /**
     * Kolom config yang bisa dipakai: hanya bila tabelnya tergabung dalam
     * query (tabel utama / join aktif) DAN kolomnya benar-benar ada.
     */
    public static function pakaiKolom(string $kunci): ?string
    {
        [$tabel, $kolom] = self::pecahKolom($kunci);
        if ($kolom === '') {
            return null;
        }

        if (! self::tabelTerpakai($tabel)) {
            return null;
        }

        $tersedia = self::kolomTersedia($tabel);
        if ($tersedia !== [] && ! in_array($kolom, $tersedia, true)) {
            return null;
        }

        return "{$tabel}.{$kolom}";
    }

    /** Tabel utama atau tabel yang join-nya berhasil divalidasi. */
    private static function tabelTerpakai(string $tabel): bool
    {
        return $tabel === config('digitrans.tabel') || in_array($tabel, self::joinAktif()['tabel'], true);
    }

    // ------------------------------------------------------------------
    // Query akun (tabel utama + LEFT JOIN data pegawai)
    // ------------------------------------------------------------------

    /**
     * Validasi & bangun join aktif: hanya join yang kedua kolom ON-nya ada.
     * Hasil cache: ['tabel' => [...], 'select' => [...alias...], 'join' => [...pasangan on...]]
     */
    private static function joinAktif(): array
    {
        if (self::$joinCache !== null) {
            return self::$joinCache;
        }

        $hasil = ['tabel' => [], 'join' => []];

        foreach ((array) config('digitrans.join') as $join) {
            if (! isset($join['tabel'], $join['on']) || ! is_string($join['on'])) {
                continue;
            }
            if (! str_contains($join['on'], '=')) {
                continue;
            }

            [$kiri, $kanan] = array_map('trim', explode('=', $join['on'], 2));
            $bagian = fn (string $s): array => str_contains($s, '.')
                ? [substr($s, 0, strpos($s, '.')), substr($s, strpos($s, '.') + 1)]
                : [(string) config('digitrans.tabel'), $s];
            [$tKiri, $cKiri] = $bagian($kiri);
            [$tKanan, $cKanan] = $bagian($kanan);

            $kolomKiri = self::kolomTersedia($tKiri);
            $kolomKanan = self::kolomTersedia($tKanan);
            if ($kolomKiri === [] || $kolomKanan === []
                || ! in_array($cKiri, $kolomKiri, true) || ! in_array($cKanan, $kolomKanan, true)) {
                continue; // tabel/kolom tidak ada → join dilewati
            }

            $hasil['tabel'][] = $join['tabel'];
            $hasil['join'][] = [$join['tabel'], "`{$tKiri}`.`{$cKiri}`", "`{$tKanan}`.`{$cKanan}`"];
        }

        return self::$joinCache = $hasil;
    }

    /**
     * Query builder akun DigiTrans: tabel utama + LEFT JOIN pegawai/referensi,
     * SELECT hanya kolom mapping dengan alias unik __<kunci> (hindari tabrakan
     * nama kolom seperti "name" yang ada di banyak tabel).
     */
    private static function queryAkun()
    {
        $query = self::db()->table(config('digitrans.tabel'));
        foreach (self::joinAktif()['join'] as [$tabel, $kiri, $kanan]) {
            $query->leftJoin($tabel, DB::raw($kiri), '=', DB::raw($kanan));
        }

        $select = [];
        foreach (array_keys((array) config('digitrans.kolom')) as $kunci) {
            $kolom = self::pakaiKolom($kunci);
            if ($kolom) {
                $titik = strpos($kolom, '.');
                $tabelKolom = substr($kolom, 0, $titik);
                $namaKolom = substr($kolom, $titik + 1);
                $select[] = "`{$tabelKolom}`.`{$namaKolom}` AS `__{$kunci}`";
            }
        }
        if ($select !== []) {
            $query->selectRaw(implode(', ', $select));
        }

        return $query;
    }

    // ------------------------------------------------------------------
    // Pencarian & autentikasi
    // ------------------------------------------------------------------

    /** Cari satu baris akun DigiTrans berdasar username/NIP/email. */
    public static function cari(string $identifier): ?object
    {
        if (! self::aktif()) {
            return null;
        }

        $identifier = str_replace(' ', '', trim($identifier));
        $kolomUser = self::pakaiKolom('username');
        $kolomNip = self::pakaiKolom('nip');
        $kolomEmail = self::pakaiKolom('email');

        if (! $kolomUser && ! $kolomNip && ! $kolomEmail) {
            Log::warning('Digitrans: tidak ada kolom pencarian yang cocok pada tabel ' . config('digitrans.tabel') . '.');

            return null;
        }

        try {
            return self::queryAkun()
                ->where(function ($q) use ($kolomUser, $kolomNip, $kolomEmail, $identifier) {
                    if ($kolomUser) {
                        $q->orWhere($kolomUser, $identifier);
                    }
                    if ($kolomNip) {
                        $q->orWhere($kolomNip, $identifier)
                            ->orWhereRaw("REPLACE({$kolomNip}, ' ', '') = ?", [$identifier]);
                    }
                    if ($kolomEmail) {
                        $q->orWhere($kolomEmail, $identifier);
                    }
                })
                ->first();
        } catch (\Throwable $e) {
            Log::warning('Digitrans: gagal query — ' . $e->getMessage());

            return null;
        }
    }

    /**
     * Verifikasi password terhadap hash DigiTrans (bcrypt Laravel).
     * Mengembalikan hash bila cocok, null bila tidak/tidak tersambung.
     */
    public static function verifikasi(?object $akun, string $password): ?string
    {
        if (! $akun) {
            return null;
        }

        $hash = $akun->{self::kolomAlias('password')} ?? null;
        if (! $hash || ! Hash::check($password, $hash)) {
            return null;
        }

        return $hash;
    }

    /** Nama alias select untuk sebuah kunci mapping (null bila kolom tak dipakai). */
    private static function kolomAlias(string $kunci): string
    {
        return '__' . $kunci;
    }

    /**
     * Alur lengkap saat login: cari di DigiTrans → verifikasi →
     * provision user lokal → kembalikan model User lokal.
     * Mengembalikan null bila gagal / integrasi mati.
     */
    public static function autentikasi(string $identifier, string $password): ?User
    {
        if (! self::aktif() || ! config('digitrans.login_dari_digitrans')) {
            return null;
        }

        $akun = self::cari($identifier);
        if (! $akun) {
            return null;
        }

        $hash = self::verifikasi($akun, $password);
        if (! $hash) {
            return null;
        }

        if (! self::nilai($akun, 'aktif', anggapAktif: true)) {
            return null; // akun dimatikan di DigiTrans → tolak
        }

        return self::provision($akun, $hash);
    }

    // ------------------------------------------------------------------
    // Provision (taut/urut akun lokal dari baris DigiTrans)
    // ------------------------------------------------------------------

    /**
     * Buat / tautkan / perbarui user lokal dari baris DigiTrans.
     * Prioritas pencocokan: digitrans_id → username → nip → email.
     */
    public static function provision(object $akun, ?string $hashLokal = null): ?User
    {
        $digitransId = self::nilai($akun, 'id');
        if ($digitransId === null) {
            return null;
        }

        $username = self::nilai($akun, 'username');
        $nip = self::nilai($akun, 'nip');
        $email = self::nilai($akun, 'email')
            ?? ($username ? $username . '@' . config('digitrans.email_placeholder_domain') : null);

        // Hash password: hasil verifikasi (mode login) ATAU salinan hash dari baris
        // DigiTrans (mode sync) — password dua aplikasi selalu identik.
        $hash = $hashLokal ?: self::nilai($akun, 'password');

        // Prioritas tautan: digitrans_id → username → nip → email (yang belum tertaut)
        $user = User::where('digitrans_id', $digitransId)->first()
            ?? ($username ? User::where('username', $username)->whereNull('digitrans_id')->first() : null)
            ?? ($nip ? User::where('nip', $nip)->whereNull('digitrans_id')->first() : null)
            ?? ($email && ! str_ends_with($email, config('digitrans.email_placeholder_domain'))
                ? User::where('email', $email)->whereNull('digitrans_id')->first()
                : null);

        // User belum ada → hanya dibuat bila auto-provision menyala
        if (! $user && ! config('digitrans.auto_provision')) {
            return null;
        }

        if (! $user) {
            // DigiTrans tidak punya kolom username → turunkan dari NIP/email
            if (! $username) {
                $username = $nip ?: ($email ? explode('@', $email)[0] : null);
                if (! $username) {
                    return null;
                }
            }

            $user = new User([
                'username' => $username,
                'role' => config('digitrans.role_bawaan'),
                'is_aktif' => true,
            ]);
        }

        $user->digitrans_id = $digitransId;
        $user->nip = $user->nip ?: $nip;
        $user->email = $user->email ?: $email;

        // Kolom password lokal NOT NULL:
        // - punya hash DigiTrans → samakan (perubahan password DigiTrans ikut ke sini)
        // - user baru tanpa hash → password acak tak-tertebak
        if ($hash && $user->password !== $hash) {
            $user->password = $hash;
        } elseif (! $user->password) {
            $user->password = Hash::make(Str::random(48));
        }

        return self::perbaruiData($user, $akun);
    }

    /**
     * Perbarui data kepegawaian dari DigiTrans (master tunggal):
     * nama, jabatan, unit kerja, pangkat, status kepegawaian, jenis kelamin,
     * TTL, agama, alamat, telepon, pendidikan, TMT jabatan, kenaikan pangkat,
     * dan batas usia pensiun. Role & atasan TIDAK disentuh — dikelola lokal.
     * Status aktif akun ikut DigiTrans.
     */
    private static function perbaruiData(User $user, object $akun): User
    {
        if (config('digitrans.sinkron_data')) {
            // Identitas dasar — hanya isi bila DigiTrans punya nilainya
            foreach ([
                'name' => 'nama',
                'jabatan' => 'jabatan',
                'tempat_lahir' => 'tempat_lahir',
                'agama' => 'agama',
                'alamat' => 'alamat',
                'no_telp' => 'no_telp',
            ] as $field => $kunci) {
                $nilai = self::nilai($akun, $kunci);
                if ($nilai !== null) {
                    $user->{$field} = $nilai;
                }
            }

            // Tanggal-tanggal kepegawaian (cast date di model menerima Y-m-d)
            foreach ([
                'tanggal_lahir' => 'tanggal_lahir',
                'tmt_jabatan' => 'tmt_jabatan',
                'kenaikan_pangkat' => 'kenaikan_pangkat',
                'batas_usia_pensiun' => 'pensiun',
            ] as $field => $kunci) {
                $nilai = self::nilai($akun, $kunci);
                if ($nilai !== null) {
                    $user->{$field} = $nilai;
                }
            }

            // NIP: DigiTrans master — perbarui bila berubah
            $nip = self::nilai($akun, 'nip');
            if ($nip !== null) {
                $user->nip = $nip;
            }

            // Normalisasi otomatis lewat mutator model:
            // gender L/P → Laki-laki/Perempuan; PNS/CPNS/PPPK Penuh Waktu → PNS/CPNS/PPPK;
            // nama jenjang pendidikan → SD..S3
            foreach ([
                'jenis_kelamin' => 'jenis_kelamin',
                'status_pegawai' => 'status_pegawai',
            ] as $field => $kunci) {
                $nilai = self::nilai($akun, $kunci);
                if ($nilai !== null) {
                    $user->{$field} = $nilai;
                }
            }

            $pangkat = self::nilai($akun, 'pangkat');
            if ($pangkat !== null) {
                $user->pangkat_golongan = $pangkat;
            }

            $pendidikan = self::nilai($akun, 'pendidikan');
            if ($pendidikan !== null) {
                $user->level_pendidikan = $pendidikan; // dinormalkan mutator
            }

            // Riwayat pendidikan: education_3 (tertinggi) → 2 → 1
            $riwayat = self::nilai($akun, 'pendidikan_3')
                ?? self::nilai($akun, 'pendidikan_2')
                ?? self::nilai($akun, 'pendidikan_1');
            if ($riwayat !== null) {
                $user->pendidikan_terakhir = $riwayat;
            }

            // Unit kerja: cocokkan berdasar nama, lalu kode; buat bila belum ada
            $namaUnit = self::nilai($akun, 'unit');
            $kodeUnit = self::nilai($akun, 'unit_kode');
            if ($namaUnit !== null || $kodeUnit !== null) {
                $unit = ($namaUnit ? UnitKerja::where('nama', $namaUnit)->first() : null)
                    ?? ($kodeUnit ? UnitKerja::where('kode', $kodeUnit)->first() : null)
                    ?? ($namaUnit ? UnitKerja::firstOrCreate(['nama' => $namaUnit], array_filter(['kode' => $kodeUnit])) : null);
                if ($unit) {
                    $user->unit_kerja_id = $unit->id;
                }
            }
        }

        // DigiTrans = master status akun: is_active false → nonaktif di TransKinerja
        $kolomAktif = self::pakaiKolom('aktif');
        if ($kolomAktif) {
            $aktif = self::nilai($akun, 'aktif');
            if ($aktif !== null) {
                $user->is_aktif = (bool) $aktif;
            }
        }

        $user->save();

        return $user;
    }

    /** Ambil nilai kolom hasil query (alias __kunci), dinormalisasi string/trim. */
    private static function nilai(object $akun, string $kunci, bool $anggapAktif = false): ?string
    {
        if ($anggapAktif && ! self::pakaiKolom($kunci)) {
            return '1'; // kolom status aktif tak ada di DigiTrans → anggap aktif
        }

        $isi = $akun->{self::kolomAlias($kunci)} ?? null;

        return ($isi !== null && trim((string) $isi) !== '') ? trim((string) $isi) : null;
    }

    // ------------------------------------------------------------------
    // Sinkronisasi massal
    // ------------------------------------------------------------------

    /**
     * Sinkronisasi massal seluruh pegawai DigiTrans → lokal.
     * Dipanggil manual (php artisan digitrans:sync), tiap 15 menit (scheduler),
     * atau tombol Sinkron di halaman Pegawai.
     * Satu baris bermasalah (mis. duplikat) TIDAK menghentikan sisanya.
     *
     * @return array{diproses: int, dibuat: int, diperbarui: int, dilewati: int, gagal: int, dinonaktifkan: int}
     */
    public static function sinkronPegawai(): array
    {
        $stat = ['diproses' => 0, 'dibuat' => 0, 'diperbarui' => 0, 'dilewati' => 0, 'gagal' => 0, 'dinonaktifkan' => 0];

        if (! self::aktif()) {
            return $stat;
        }

        try {
            $baris = self::queryAkun()->get();
        } catch (\Throwable $e) {
            Log::warning('Digitrans: sinkronisasi gagal — ' . $e->getMessage());

            return $stat;
        }

        foreach ($baris as $akun) {
            $stat['diproses']++;

            try {
                $user = self::provision($akun);

                if (! $user) {
                    $stat['dilewati']++;
                } else {
                    $user->wasRecentlyCreated ? $stat['dibuat']++ : $stat['diperbarui']++;
                }
            } catch (\Throwable $e) {
                $stat['gagal']++;
                Log::warning('Digitrans: baris id ' . (self::nilai($akun, 'id') ?? '?') . ' gagal — ' . $e->getMessage());
            }
        }

        // Akun yang sudah TIDAK ADA di DigiTrans → nonaktifkan di TransKinerja
        // (tidak dihapus: riwayat kinerja & relasi data tetap utuh)
        $idDigi = $baris->map(fn ($a) => self::nilai($a, 'id'))->filter()->map(fn ($v) => (int) $v)->all();
        if ($idDigi !== []) {
            $stat['dinonaktifkan'] = User::whereNotNull('digitrans_id')
                ->whereNotIn('digitrans_id', $idDigi)
                ->where('is_aktif', true)
                ->update(['is_aktif' => false]);
        }

        return $stat;
    }

    /** Tes koneksi (untuk artisan digitrans:cek). */
    public static function tesKoneksi(): bool
    {
        try {
            return self::db()->table(config('digitrans.tabel'))->limit(1)->count() >= 0;
        } catch (\Throwable $e) {
            Log::warning('Digitrans: koneksi gagal — ' . $e->getMessage());

            return false;
        }
    }
}
