<?php

namespace App\Services;

use App\Mail\OtpEmail;
use App\Models\OtpCode;
use App\Models\Pengaturan;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Modul OTP login (verifikasi dua langkah via email).
 * Status ON/OFF & parameternya diatur Superadmin pada Pengaturan → OTP & Keamanan.
 * Kunci pengaturan: otp_aktif, otp_panjang, otp_masa_berlaku (menit), otp_maks_percobaan.
 */
class OtpService
{
    /** Apakah verifikasi OTP diaktifkan (ON)? */
    public static function aktif(): bool
    {
        try {
            return Pengaturan::get('otp_aktif') === '1';
        } catch (\Throwable) {
            return false;
        }
    }

    public static function panjang(): int
    {
        return (int) (Pengaturan::get('otp_panjang') ?: 6);
    }

    public static function masaBerlakuMenit(): int
    {
        return (int) (Pengaturan::get('otp_masa_berlaku') ?: 10);
    }

    public static function maksPercobaan(): int
    {
        return (int) (Pengaturan::get('otp_maks_percobaan') ?: 5);
    }

    /**
     * Buat kode OTP baru untuk user, batalkan kode lama, kirim ke email.
     * Mengembalikan model OtpCode (dengan kodePlain di memori) atau null bila gagal kirim.
     */
    public static function kirim(User $user): ?OtpCode
    {
        // Tanpa email terdaftar, kode tidak mungkin dikirim — jangan buat kode.
        if (! $user->email) {
            return null;
        }

        // Gugurkan semua kode aktif milik user ini (sekali pakai — hanya kode terbaru yang sah).
        OtpCode::where('user_id', $user->id)->whereNull('dipakai_at')->update(['dipakai_at' => now()]);

        $panjang = min(8, max(4, self::panjang()));
        $kode = '';
        for ($i = 0; $i < $panjang; $i++) {
            $kode .= (string) random_int(0, 9);
        }

        $otp = OtpCode::create([
            'user_id' => $user->id,
            'kode' => hash('sha256', $kode),
            'kedaluwarsa_at' => now()->addMinutes(min(60, max(1, self::masaBerlakuMenit()))),
        ]);
        $otp->kodePlain = $kode;

        try {
            Mail::to($user->email)->send(new OtpEmail($otp, $user, self::masaBerlakuMenit()));
        } catch (\Throwable $e) {
            Log::warning("Gagal kirim OTP ke {$user->email}: {$e->getMessage()}");

            return null;
        }

        return $otp;
    }

    /**
     * Verifikasi kode yang dimasukkan user terhadap kode aktif terbaru.
     * Mengembalikan [sukses, pesanError].
     */
    public static function verifikasi(User $user, string $kode): array
    {
        $otp = OtpCode::where('user_id', $user->id)
            ->whereNull('dipakai_at')
            ->latest('id')
            ->first();

        if (! $otp) {
            return [false, 'Kode OTP tidak ditemukan atau sudah dipakai. Silakan masuk kembali.'];
        }

        if (now()->greaterThan($otp->kedaluwarsa_at)) {
            $otp->update(['dipakai_at' => now()]);

            return [false, 'Kode OTP sudah kedaluwarsa. Silakan kirim ulang kode baru.'];
        }

        if ($otp->percobaan >= self::maksPercobaan()) {
            $otp->update(['dipakai_at' => now()]);

            return [false, 'Percobaan melebihi batas. Silakan kirim ulang kode baru.'];
        }

        if (! hash_equals($otp->kode, hash('sha256', $kode))) {
            $sisa = self::maksPercobaan() - $otp->percobaan - 1;
            $otp->increment('percobaan');

            return [false, "Kode OTP salah. Sisa percobaan: {$sisa} kali."];
        }

        $otp->update(['dipakai_at' => now()]);

        return [true, ''];
    }

    /** Sembunyikan sebagian email untuk ditampilkan di halaman OTP (j***@kemen.go.id). */
    public static function sembunyikanEmail(?string $email): string
    {
        if (! $email || ! str_contains($email, '@')) {
            return '-';
        }
        [$lokal, $domain] = explode('@', $email, 2);
        $awal = mb_substr($lokal, 0, 1);
        $akhir = mb_strlen($lokal) > 2 ? mb_substr($lokal, -1) : '';

        return $awal.str_repeat('*', max(2, mb_strlen($lokal) - 1 - ($akhir ? 1 : 0))).$akhir.'@'.$domain;
    }
}
