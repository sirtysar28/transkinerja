<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = ['aktor_id', 'aksi', 'modul', 'entitas', 'entitas_id', 'deskripsi', 'ip', 'created_at'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function aktor()
    {
        return $this->belongsTo(User::class, 'aktor_id');
    }
}
