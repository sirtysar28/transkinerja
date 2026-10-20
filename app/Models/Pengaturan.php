<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Penyimpanan pengaturan aplikasi berbasis kunci-nilai (SMTP, notifikasi email, dll).
 */
class Pengaturan extends Model
{
    protected $table = 'pengaturans';

    protected $primaryKey = 'kunci';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = ['kunci', 'nilai', 'diubah_oleh', 'diubah_pada'];

    protected $casts = ['diubah_pada' => 'datetime'];

    /** Ambil nilai pengaturan (nilai default bila belum ada). */
    public static function get(string $kunci, ?string $default = null): ?string
    {
        return static::find($kunci)?->nilai ?? $default;
    }

    /** Simpan/ubah nilai pengaturan. */
    public static function set(string $kunci, ?string $nilai, ?int $userId = null): void
    {
        static::updateOrCreate(
            ['kunci' => $kunci],
            ['nilai' => $nilai, 'diubah_oleh' => $userId ?? auth()->id(), 'diubah_pada' => now()],
        );
    }
}
