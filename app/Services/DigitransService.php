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
 *  1. cari()          — cari baris akun di database DigiTrans
 *  2. autentikasi()   — verifikasi kredensial terhadap DigiTrans lalu
 *                       buat/perbarui (provision) user lokal secara otomatis
 *  3. sinkronPegawai()— tarik seluruh pegawai DigiTrans → perbarui data
 *                       kepegawaian lokal (nama, email, jabatan, unit)
 *
 * Kecerdasan skema: nama kolom dibaca dari information_schema — query hanya
 * memakai kolom yang benar-benar ada di tabel DigiTrans, sehingga mapping
 * .env yang keliru tidak bikin error (fitur terkait otomatis dilewati).
 *
 * Prinsip aman: TransKinerja hanya MEMBACA DigiTrans (SELECT saja).
 * Role, atasan, is_aktif, dan password lokal dikelola TransKinerja sendiri.
 */
class DigitransService
{
    /** Cache daftar kolom tabel DigiTrans (null = belum dicek). */
    private static ?array $kolomCache = null;

    /** Integrasi menyala? */
    public static function aktif(): bool
    {
        if (! config('digitrans.aktif')) {
            return false;
        }

        return (bool) config('database.connections.digitrans.database');
    }

    /** Query builder ke tabel users DigiTrans (read-only). */
    private static function tabel()
    {
        return DB::connection(config('digitrans.koneksi'))
            ->table(config('digitrans.tabel'));
    }

    /** Kolom terpasang di config (mentah, tanpa validasi skema). */
    private static function kolom(string $kunci): ?string
    {
        return config("digitrans.kolom.{$kunci}");
    }

    /**
     * Kolom yang BENAR-BENAR ada di tabel DigiTrans (via information_schema).
     * Array kosong = skema tak terbaca → jangan difilter (asumsi valid).
     */
    public static function kolomTersedia(): array
    {
        if (self::$kolomCache !== null) {
            return self::$kolomCache;
        }

        try {
            $baris = DB::connection(config('digitrans.koneksi'))->select(
                'SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?',
                [config('database.connections.' . config('digitrans.koneksi') . '.database'), config('digitrans.tabel')]
            );

            self::$kolomCache = array_map(static fn ($r) => is_object($r) ? ($r->COLUMN_NAME ?? reset($r)) : reset($r), $baris);
        } catch (\Throwable $e) {
            Log::warning('Digitrans: gagal baca skema — ' . $e->getMessage());
            self::$kolomCache = [];
        }

        return self::$kolomCache;
    }

    /**
     * Kolom config yang bisa dipakai: hanya bila benar-benar ada di tabel
     * DigiTrans. Mengembalikan null bila kolom tidak ada (fitur dilewati).
     */
    public static function pakaiKolom(string $kunci): ?string
    {
        $kolom = self::kolom($kunci);
        if (! $kolom) {
            return null;
        }

        $tersedia = self::kolomTersedia();
        if ($tersedia !== [] && ! in_array($kolom, $tersedia, true)) {
            return null;
        }

        return $kolom;
    }

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
            return self::tabel()
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
        $kolomPassword = self::pakaiKolom('password');
        if (! $akun || ! $kolomPassword) {
            return null;
        }

        $hash = $akun->{$kolomPassword} ?? null;
        if (! $hash || ! Hash::check($password, $hash)) {
            return null;
        }

        return $hash;
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

        $kolomAktif = self::pakaiKolom('aktif');
        if ($kolomAktif && isset($akun->{$kolomAktif}) && ! $akun->{$kolomAktif}) {
            return null; // akun dimatikan di DigiTrans → tolak
        }

        return self::provision($akun, $hash);
    }

    /**
     * Buat / tautkan / perbarui user lokal dari baris DigiTrans.
     * Prioritas pencocokan: digitrans_id → username → nip → email.
     */
    public static function provision(object $akun, ?string $hashLokal = null): ?User
    {
        $kolomId = self::pakaiKolom('id');
        if (! $kolomId) {
            return null;
        }

        $digitransId = $akun->{$kolomId};
        $username = self::nilai($akun, 'username');
        $nip = self::nilai($akun, 'nip');
        $email = self::nilai($akun, 'email')
            ?? ($username ? $username . '@' . config('digitrans.email_placeholder_domain') : null);

        // Hash password: hasil verifikasi (mode login) ATAU salinan hash dari baris
        // DigiTrans (mode sync) — password dua aplikasi selalu identik.
        $kolomPassword = self::pakaiKolom('password');
        $hash = $hashLokal ?: ($kolomPassword ? ($akun->{$kolomPassword} ?? null) : null);

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
        // - punya hash DigiTrans → samakan (termasuk user lama, agar perubahan
        //   password di DigiTrans ikut tersinkron ke TransKinerja)
        // - user baru tanpa hash → password acak tak-tertebak (diganti otomatis
        //   dari DigiTrans begitu hash-nya tersedia / user login via DigiTrans)
        if ($hash && $user->password !== $hash) {
            $user->password = $hash;
        } elseif (! $user->password) {
            $user->password = Hash::make(Str::random(48));
        }

        return self::perbaruiData($user, $akun);
    }

    /** Perbarui data kepegawaian (nama/jabatan/unit) — tidak menyentuh role/atasan.
     *  Status aktif ikut DigiTrans untuk akun yang tertaut (master tunggal). */
    private static function perbaruiData(User $user, object $akun): User
    {
        if (config('digitrans.sinkron_data')) {
            $nama = self::nilai($akun, 'nama');
            if ($nama) {
                $user->name = $nama;
            }

            $jabatan = self::nilai($akun, 'jabatan');
            if ($jabatan) {
                $user->jabatan = $jabatan;
            }

            $kolomUnit = self::pakaiKolom('unit');
            if ($kolomUnit) {
                $namaUnit = $akun->{$kolomUnit} ?? null;
                if ($namaUnit && trim((string) $namaUnit) !== '') {
                    $unit = UnitKerja::where('nama', trim((string) $namaUnit))->first();
                    if ($unit) {
                        $user->unit_kerja_id = $unit->id;
                    }
                }
            }
        }

        // DigiTrans = master status akun: is_active false → nonaktif di TransKinerja
        $kolomAktif = self::pakaiKolom('aktif');
        if ($kolomAktif && array_key_exists($kolomAktif, (array) $akun)) {
            $user->is_aktif = (bool) $akun->{$kolomAktif};
        }

        $user->save();

        return $user;
    }

    /** Ambil nilai kolom (dengan validasi keberadaan kolom di skema). */
    private static function nilai(object $akun, string $kunci): ?string
    {
        $kolom = self::pakaiKolom($kunci);
        if (! $kolom) {
            return null;
        }
        $isi = $akun->{$kolom} ?? null;

        return ($isi !== null && trim((string) $isi) !== '') ? trim((string) $isi) : null;
    }

    /**
     * Sinkronisasi massal seluruh pegawai DigiTrans → lokal.
     * Dipanggil manual (php artisan digitrans:sync) atau via cron cPanel.
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

        $kolomId = self::pakaiKolom('id') ?? 'id';

        try {
            $baris = self::tabel()->get();
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
                Log::warning('Digitrans: baris id ' . ($akun->{$kolomId} ?? '?') . ' gagal — ' . $e->getMessage());
            }
        }

        // Akun yang sudah TIDAK ADA di DigiTrans → nonaktifkan di TransKinerja
        // (tidak dihapus: riwayat kinerja & relasi data tetap utuh)
        $idDigi = $baris->pluck($kolomId)->filter()->map(fn ($v) => (int) $v)->all();
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
            return self::tabel()->limit(1)->count() >= 0;
        } catch (\Throwable $e) {
            Log::warning('Digitrans: koneksi gagal — ' . $e->getMessage());

            return false;
        }
    }
}
