<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Presensi extends Model
{
    public $timestamps = false;

    protected $fillable = ['user_id', 'tanggal', 'clock_in', 'clock_out'];

    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
            'clock_in' => 'datetime',
            'clock_out' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function durasiMenit(): ?int
    {
        if (! $this->clock_in || ! $this->clock_out) {
            return null;
        }

        return (int) $this->clock_in->diffInMinutes($this->clock_out);
    }
}
