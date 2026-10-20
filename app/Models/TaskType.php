<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TaskType extends Model
{
    protected $fillable = ['kode', 'nama', 'keterangan', 'is_aktif'];

    protected function casts(): array
    {
        return ['is_aktif' => 'boolean'];
    }
}
