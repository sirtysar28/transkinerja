<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Kode OTP login — kode asli TIDAK disimpan, hanya hash SHA-256.
 * Sekali pakai (dipakai_at) & berbatas percobaan.
 */
class OtpCode extends Model
{
    protected $fillable = ['user_id', 'kode', 'kedaluwarsa_at', 'percobaan', 'dipakai_at'];

    protected $casts = ['kedaluwarsa_at' => 'datetime', 'dipakai_at' => 'datetime'];

    /** Kode asli (plain) — hanya ada di memori saat pembuatan/pengiriman, tidak disimpan ke DB. */
    public ?string $kodePlain = null;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function masihBerlaku(): bool
    {
        return $this->dipakai_at === null && now()->lessThan($this->kedaluwarsa_at);
    }
}
