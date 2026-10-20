<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UnitKerja extends Model
{
    protected $table = 'unit_kerja';

    protected $fillable = ['kode', 'nama', 'parent_id', 'is_aktif'];

    protected function casts(): array
    {
        return ['is_aktif' => 'boolean'];
    }

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function pegawai()
    {
        return $this->hasMany(User::class);
    }
}
