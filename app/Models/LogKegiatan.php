<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LogKegiatan extends Model
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_DIAJUKAN = 'diajukan';
    public const STATUS_DISETUJUI = 'disetujui';
    public const STATUS_REVISI = 'revisi';

    public const STATUS_LABEL = [
        self::STATUS_DRAFT => 'Draft',
        self::STATUS_DIAJUKAN => 'Diajukan',
        self::STATUS_REVISI => 'Perlu Revisi',
        self::STATUS_DISETUJUI => 'Disetujui',
    ];

    protected $fillable = [
        'user_id', 'tanggal', 'nama_kegiatan', 'output_id', 'pengguna',
        'jam_mulai', 'jam_selesai', 'status', 'catatan_revisi',
    ];

    protected function casts(): array
    {
        return ['tanggal' => 'date'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function output()
    {
        return $this->belongsTo(OutputRef::class);
    }

    public function taskTypes()
    {
        return $this->belongsToMany(TaskType::class, 'log_task_type');
    }

    public function lampirans()
    {
        return $this->hasMany(LogLampiran::class);
    }

    public function approvals()
    {
        return $this->hasMany(ApprovalLog::class)->latest();
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABEL[$this->status] ?? $this->status;
    }

    /** Durasi dalam menit berdasarkan jam mulai-selesai. */
    public function durasiMenit(): ?int
    {
        if (! $this->jam_mulai || ! $this->jam_selesai) {
            return null;
        }
        $mulai = \Carbon\Carbon::parse($this->jam_mulai);
        $selesai = \Carbon\Carbon::parse($this->jam_selesai);
        if ($selesai->lt($mulai)) {
            $selesai->addDay(); // melewati tengah malam
        }

        return (int) $mulai->diffInMinutes($selesai);
    }

    public function bolehEdit(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_REVISI], true);
    }
}
