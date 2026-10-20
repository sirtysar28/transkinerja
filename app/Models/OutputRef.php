<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OutputRef extends Model
{
    protected $fillable = ['nama', 'is_aktif'];

    protected function casts(): array
    {
        return ['is_aktif' => 'boolean'];
    }
}
