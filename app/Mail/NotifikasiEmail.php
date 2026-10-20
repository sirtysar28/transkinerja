<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Email notifikasi TransKinerja — body HTML penuh ber-branding Kementerian Transmigrasi.
 */
class NotifikasiEmail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $judul,
        public string $pesan,
        public ?string $tautan = null,
        public ?User $penerima = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '[TransKinerja] '.$this->judul,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.notifikasi',
            with: [
                'judul' => $this->judul,
                'pesan' => $this->pesan,
                'tautan' => $this->tautan,
                'penerima' => $this->penerima,
            ],
        );
    }
}
