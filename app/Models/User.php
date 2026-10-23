<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Hash;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    public const ROLE_PEGAWAI = 'pegawai';
    public const ROLE_ATASAN = 'atasan';
    public const ROLE_ADMIN_UNIT = 'admin_unit';
    public const ROLE_PIMPINAN = 'pimpinan';
    public const ROLE_SUPERADMIN = 'superadmin';

    /** Alias lama — kini peran tertinggi bernama Superadmin. */
    public const ROLE_ADMIN_PUSAT = self::ROLE_SUPERADMIN;

    public const ROLES = [
        self::ROLE_PEGAWAI => 'Pegawai ASN',
        self::ROLE_ATASAN => 'Atasan / Pejabat Penilai',
        self::ROLE_ADMIN_UNIT => 'Admin Unit Kerja',
        self::ROLE_PIMPINAN => 'Pimpinan',
        self::ROLE_SUPERADMIN => 'Superadmin',
    ];

    public const ROLE_LEVEL = [
        self::ROLE_PEGAWAI => 1,
        self::ROLE_ATASAN => 2,
        self::ROLE_ADMIN_UNIT => 3,
        self::ROLE_PIMPINAN => 4,
        self::ROLE_SUPERADMIN => 5,
    ];

    /** Level pendidikan (kolom LEVEL PENDIDIKAN pada format Data Dashboard). */
    public const LEVEL_PENDIDIKAN = ['SD', 'SLTP', 'SLTA', 'D1', 'D2', 'D3', 'D4', 'S1', 'S2', 'S3'];

    /** Skala kemampuan (renang & bahasa Inggris). */
    public const KEMAMPUAN = ['Tidak Bisa' => 'Tidak Bisa', 'Pemula' => 'Pemula', 'Menengah' => 'Menengah', 'Mahir' => 'Mahir'];

    /** Status kepegawaian baku (kolom STATUS pada format Data Dashboard). */
    public const STATUS_PEGAWAI = ['PNS', 'CPNS', 'PPPK', 'Non ASN'];

    /** Batas usia pensiun (BUP) menurut kelompok jabatan. */
    public const BUP_UMUM = 58;
    public const BUP_ESELON_I_II_FUNGSIONAL_MADYA = 60;

    protected $fillable = [
        'name', 'nip', 'username', 'email', 'password', 'role',
        'pangkat_golongan', 'jabatan', 'unit_kerja_id', 'atasan_id', 'is_aktif', 'last_login_at',
        'status_pegawai', 'jenis_kelamin', 'tempat_lahir', 'tanggal_lahir',
        'agama', 'alamat', 'no_telp', 'pendidikan_terakhir', 'level_pendidikan',
        'tmt_jabatan', 'kenaikan_pangkat', 'batas_usia_pensiun',
        'kemampuan_renang', 'kemampuan_inggris',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_aktif' => 'boolean',
            'last_login_at' => 'datetime',
            'tanggal_lahir' => 'date',
            'tmt_jabatan' => 'date',
            'kenaikan_pangkat' => 'date',
            'batas_usia_pensiun' => 'date',
        ];
    }

    // ---------- Relasi ----------
    public function unitKerja()
    {
        return $this->belongsTo(UnitKerja::class);
    }

    public function atasan()
    {
        return $this->belongsTo(self::class, 'atasan_id');
    }

    public function bawahan()
    {
        return $this->hasMany(self::class, 'atasan_id');
    }

    public function logKegiatans()
    {
        return $this->hasMany(LogKegiatan::class);
    }

    public function presensis()
    {
        return $this->hasMany(Presensi::class);
    }

    public function notifikasi()
    {
        return $this->hasMany(AppNotification::class);
    }

    // ---------- Helper ----------
    public function level(): int
    {
        return self::ROLE_LEVEL[$this->role] ?? 0;
    }

    public function roleLabel(): string
    {
        return self::ROLES[$this->role] ?? $this->role;
    }

    public function isAdminPusat(): bool
    {
        return $this->role === self::ROLE_SUPERADMIN;
    }

    /** Pimpinan & Superadmin dapat melihat laporan kinerja seluruh pegawai. */
    public function lihatSemuaPegawai(): bool
    {
        return in_array($this->role, [self::ROLE_PIMPINAN, self::ROLE_SUPERADMIN], true);
    }

    /**
     * Hitung batas usia pensiun (BUP) otomatis:
     * 60 tahun untuk jabatan pimpinan tinggi (Eselon I/II) & fungsional madya,
     * selain itu 58 tahun. Mengembalikan tanggal (Y-m-d) atau null.
     */
    public static function hitungBup(?string $tanggalLahir, ?string $levelEselon = null, ?string $levelFungsional = null): ?string
    {
        if (! $tanggalLahir) {
            return null;
        }

        $eselon = strtoupper(trim((string) $levelEselon));
        $fungsional = mb_strtolower(trim((string) $levelFungsional));

        $bup60 = in_array($eselon, ['I', 'II'], true)
            || str_contains($fungsional, 'madya')
            || str_contains($fungsional, 'utama');

        try {
            return \Carbon\Carbon::parse($tanggalLahir)
                ->addYears($bup60 ? self::BUP_ESELON_I_II_FUNGSIONAL_MADYA : self::BUP_UMUM)
                ->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    // ---------- Mutator: bakukan nilai master saat disimpan ----------
    // Jaring pengaman agar data dari sumber mana pun (form manual / import) selalu
    // seragam dengan opsi filter — tak ada lagi "pns" vs "PNS" atau "Laki-Laki" vs "Laki-laki".
    protected function setStatusPegawaiAttribute($v): void
    {
        $this->attributes['status_pegawai'] = self::normStatusPegawai($v === null ? null : (string) $v);
    }

    protected function setJenisKelaminAttribute($v): void
    {
        $this->attributes['jenis_kelamin'] = self::normJenisKelamin($v === null ? null : (string) $v);
    }

    protected function setLevelPendidikanAttribute($v): void
    {
        $this->attributes['level_pendidikan'] = self::normLevelPendidikan($v === null ? null : (string) $v);
    }

    protected function setKemampuanRenangAttribute($v): void
    {
        $this->attributes['kemampuan_renang'] = self::normKemampuan($v === null ? null : (string) $v);
    }

    protected function setKemampuanInggrisAttribute($v): void
    {
        $this->attributes['kemampuan_inggris'] = self::normKemampuan($v === null ? null : (string) $v);
    }

    // ---------- Normalisasi nilai master (dipakai import, filter & migrasi data lama) ----------

    /** Laki-laki / Perempuan — terima varian: "L", "P", "Laki-Laki", "Pria", "Wanita", dst. */
    public static function normJenisKelamin(?string $v): ?string
    {
        $v = mb_strtolower(trim((string) $v));
        if ($v === '' || $v === '-') {
            return null;
        }

        return str_starts_with($v, 'l') || str_contains($v, 'laki') || str_contains($v, 'pria') || str_contains($v, 'lelaki') || $v === 'm'
            ? 'Laki-laki'
            : (str_starts_with($v, 'p') || str_contains($v, 'perempuan') || str_contains($v, 'wanita') || str_contains($v, 'cewe') || $v === 'f' ? 'Perempuan' : null);
    }

    /** PNS / CPNS / PPPK — terima varian huruf besar/kecil & spasi. */
    public static function normStatusPegawai(?string $v): ?string
    {
        $v = mb_strtoupper(trim((string) $v));
        if ($v === '' || $v === '-') {
            return null;
        }

        return match (true) {
            str_contains($v, 'CPNS') => 'CPNS',
            str_contains($v, 'PPPK') || str_contains($v, 'PPP') => 'PPPK',
            str_contains($v, 'PNS') => 'PNS',
            default => $v,
        };
    }

    /** Tidak Bisa / Pemula / Menengah / Mahir — terima varian bebas. */
    public static function normKemampuan(?string $v): ?string
    {
        $v = mb_strtolower(trim((string) $v));
        if ($v === '' || $v === '-') {
            return null;
        }

        return match (true) {
            str_contains($v, 'tidak') || str_contains($v, 'belum') || $v === 'none' || $v === 'n' => 'Tidak Bisa',
            str_contains($v, 'pemula') || str_contains($v, 'dasar') || str_contains($v, 'kurang') || str_contains($v, 'beginner') => 'Pemula',
            str_contains($v, 'menengah') || str_contains($v, 'sedang') || str_contains($v, 'cukup') || str_contains($v, 'intermediate') => 'Menengah',
            str_contains($v, 'mahir') || str_contains($v, 'baik sekali') || str_contains($v, 'lanjut') || str_contains($v, 'ahli') || str_contains($v, 'advanced') || str_contains($v, 'expert') || str_contains($v, 'tinggi') || str_contains($v, 'lancar') || str_contains($v, 'fasih') => 'Mahir',
            default => null,
        };
    }

    /** SD s.d. S3 — terima varian: "S-1", "SMA", "SLTA", "Sarjana", "Magister", dst. */
    public static function normLevelPendidikan(?string $v): ?string
    {
        $v = mb_strtoupper(preg_replace('/[^A-Z0-9]+/i', '', (string) $v));
        if ($v === '') {
            return null;
        }

        return match (true) {
            str_starts_with($v, 'S3') || str_contains($v, 'DOKTOR') || str_contains($v, 'DOCTOR') || str_contains($v, 'PHD') => 'S3',
            str_starts_with($v, 'S2') || str_contains($v, 'MAGISTER') || str_contains($v, 'MASTER') => 'S2',
            str_starts_with($v, 'S1') || str_contains($v, 'SARJANA') || str_contains($v, 'BACHELOR') => 'S1',
            $v === 'D4' => 'D4',
            $v === 'D3' || str_contains($v, 'DIPLOMA3') => 'D3',
            $v === 'D2' || str_contains($v, 'DIPLOMA2') => 'D2',
            $v === 'D1' || str_contains($v, 'DIPLOMA1') => 'D1',
            str_contains($v, 'SLTA') || str_contains($v, 'SMA') || str_contains($v, 'SMK') || str_contains($v, 'SMU') || str_contains($v, 'MAN') || str_contains($v, 'MAK') => 'SLTA',
            str_contains($v, 'SLTP') || str_contains($v, 'SMP') || str_contains($v, 'MTS') => 'SLTP',
            str_starts_with($v, 'SD') => 'SD',
            default => null,
        };
    }

    public function bolehKelolaPegawai(): bool
    {
        return in_array($this->role, [self::ROLE_ADMIN_UNIT, self::ROLE_SUPERADMIN], true);
    }

    /**
     * Cakupan pegawai yang dapat dipantau user ini:
     * pegawai -> diri sendiri; atasan -> bawahan; admin unit -> satu unit;
     * pimpinan & superadmin -> seluruh pegawai.
     * Gunakan dari instance: $user->cakupanPegawai() (pemanggilan statik tidak tahu user aktif).
     */
    public function scopeCakupanPegawai($query)
    {
        return match ($this->role) {
            self::ROLE_PEGAWAI => $query->where('users.id', $this->id),
            self::ROLE_ATASAN => $query->where(fn ($q) => $q->where('users.id', $this->id)->orWhere('atasan_id', $this->id)),
            self::ROLE_ADMIN_UNIT => $query->where(fn ($q) => $q->where('users.id', $this->id)->orWhere('unit_kerja_id', $this->unit_kerja_id)),
            self::ROLE_PIMPINAN, self::ROLE_SUPERADMIN => $query,
            default => $query->where('users.id', $this->id),
        };
    }
}
