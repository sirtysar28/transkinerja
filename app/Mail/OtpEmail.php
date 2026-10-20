<?php

namespace App\Mail;

use App\Models\OtpCode;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Email kode OTP login — body HTML ber-branding Kementerian Transmigrasi.
 *
 * Sengaja TIDAK di-queue: kode asli hanya berada di memori (kodePlain) dan
 * pengiriman bersifat mendesak — user menunggu di halaman verifikasi.
 */
class OtpEmail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public OtpCode $otp,
        public User $penerima,
        public int $masaBerlakuMenit,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '[TransKinerja] Kode OTP '.$this->kodeTampil(),
        );
    }

    /** Kode asli diambil dari properti memori (tidak pernah disimpan di database). */
    public function kodeTampil(): string
    {
        return $this->otp->kodePlain ?? '------';
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.otp',
            with: [
                'kode' => $this->kodeTampil(),
                'penerima' => $this->penerima,
                'menit' => $this->masaBerlakuMenit,
                'kedaluwarsa' => $this->otp->kedaluwarsa_at,
            ],
        );
    }
}
