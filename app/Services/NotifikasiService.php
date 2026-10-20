<?php

namespace App\Services;

use App\Mail\NotifikasiEmail;
use App\Models\AppNotification;
use App\Models\Pengaturan;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class NotifikasiService
{
    /**
     * Kirim notifikasi dalam aplikasi + (opsional) email HTML ke email
     * terdaftar setiap penerima, sesuai pengaturan SMTP superadmin.
     */
    public static function kirim(int|array $userId, string $judul, string $pesan, ?string $tautan = null): void
    {
        $ids = is_array($userId) ? $userId : [$userId];
        $emailAktif = Pengaturan::get('notifikasi_email_aktif') === '1';

        foreach ($ids as $id) {
            $notif = AppNotification::create([
                'user_id' => $id,
                'judul' => $judul,
                'pesan' => $pesan,
                'tautan' => $tautan,
            ]);

            if (! $emailAktif) {
                continue;
            }

            $penerima = User::find($id);
            if (! $penerima?->email) {
                continue;
            }

            try {
                Mail::to($penerima->email)->send(new NotifikasiEmail(
                    judul: $judul,
                    pesan: $pesan,
                    tautan: $tautan ? url($tautan) : null,
                    penerima: $penerima,
                ));
            } catch (\Throwable $e) {
                // Kegagalan email tidak boleh mengganggu alur aplikasi.
                Log::warning("Gagal kirim email notifikasi #{$notif->id} ke {$penerima->email}: {$e->getMessage()}");
            }
        }
    }
}
