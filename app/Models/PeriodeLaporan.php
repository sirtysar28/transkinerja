<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PeriodeLaporan extends Model
{
    protected $fillable = ['bulan', 'tahun', 'tanggal_buka', 'tanggal_tutup', 'is_aktif'];

    protected function casts(): array
    {
        return [
            'tanggal_buka' => 'date',
            'tanggal_tutup' => 'date',
            'is_aktif' => 'boolean',
        ];
    }

    public static function namaBulan(int $bulan): string
    {
        $bulanIndo = [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
            'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

        return $bulanIndo[$bulan] ?? (string) $bulan;
    }

    public function label(): string
    {
        return self::namaBulan($this->bulan).' '.$this->tahun;
    }

    public function terbuka(): bool
    {
        $today = now()->toDateString();

        return $today >= $this->tanggal_buka->toDateString()
            && $today <= $this->tanggal_tutup->toDateString();
    }
}
