<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppNotification extends Model
{
    protected $fillable = ['user_id', 'judul', 'pesan', 'tautan', 'dibaca_at'];

    protected function casts(): array
    {
        return ['dibaca_at' => 'datetime'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function terbaca(): bool
    {
        return $this->dibaca_at !== null;
    }
}
