<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApprovalLog extends Model
{
    public const AKSI_AJUKAN = 'ajukan';
    public const AKSI_REVISI = 'revisi';
    public const AKSI_SETUJU = 'setujui';
    public const AKSI_BUKA = 'buka_kembali';

    protected $fillable = ['log_kegiatan_id', 'aktor_id', 'aksi', 'catatan'];

    public function logKegiatan()
    {
        return $this->belongsTo(LogKegiatan::class);
    }

    public function aktor()
    {
        return $this->belongsTo(User::class, 'aktor_id');
    }

    public function aksiLabel(): string
    {
        return match ($this->aksi) {
            self::AKSI_AJUKAN => 'Diajukan',
            self::AKSI_REVISI => 'Dikembalikan untuk revisi',
            self::AKSI_SETUJU => 'Disetujui',
            self::AKSI_BUKA => 'Dibuka kembali',
            default => $this->aksi,
        };
    }
}
