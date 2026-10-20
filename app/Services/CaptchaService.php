<?php

namespace App\Services;

/**
 * CAPTCHA sisi server: kode 5 karakter disimpan di session dengan masa berlaku,
 * dirender sebagai gambar PNG memakai GD (tanpa dependensi font eksternal).
 */
class CaptchaService
{
    public const SESSION_KEY = 'captcha_code';
    public const EXPIRY_KEY = 'captcha_expiry';
    public const TTL_MENIT = 10;
    private const KARAKTER = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    public function generate(): string
    {
        $kode = '';
        for ($i = 0; $i < 5; $i++) {
            $kode .= self::KARAKTER[random_int(0, strlen(self::KARAKTER) - 1)];
        }

        session([
            self::SESSION_KEY => $kode,
            self::EXPIRY_KEY => now()->addMinutes(self::TTL_MENIT)->timestamp,
        ]);

        return $kode;
    }

    public function verify(?string $input): bool
    {
        $kode = session(self::SESSION_KEY);
        $expiry = session(self::EXPIRY_KEY);

        if (! $kode || ! $expiry) {
            return false;
        }

        // Berlaku sekali pakai & kedaluwarsa otomatis.
        session()->forget([self::SESSION_KEY, self::EXPIRY_KEY]);

        if (now()->timestamp > $expiry) {
            return false;
        }

        return strtoupper(trim((string) $input)) === strtoupper($kode);
    }

    /** Render gambar PNG captcha (ke output buffer) — prioritas keterbacaan. */
    public function renderImage(string $kode): void
    {
        $w = 220;
        $h = 64;
        $img = imagecreatetruecolor($w, $h);

        $bg = imagecolorallocate($img, 255, 255, 255);          // putih kontras maksimal
        imagefilledrectangle($img, 0, 0, $w, $h, $bg);

        $navy = imagecolorallocate($img, 11, 42, 91);            // navy gelap, tajam
        $garis = imagecolorallocate($img, 203, 213, 225);        // noise garis abu muda (halus)

        // hanya 2 garis halus di belakang teks (cukup anti-bot, tidak mengganggu mata)
        for ($i = 0; $i < 2; $i++) {
            $y = random_int(10, $h - 10);
            imageline($img, 6, $y, $w - 6, random_int(10, $h - 10), $garis);
        }

        $fontTtf = $this->cariFontTtf();
        $len = strlen($kode);

        if ($fontTtf !== null) {
            // Font TTF besar, tegak & jelas — posisi vertikal dihitung agar TEPAT
            // di tengah kanvas (diukur via bbox, bukan tebakan), jitter kecil dan
            // dijamin tidak pernah keluar batas (tidak bisa kepotong atas/bawah).
            $ukuran = 30;
            $bUkur = imagettfbbox($ukuran, 0, $fontTtf, 'M');
            $atas = abs($bUkur[5]);                       // tinggi huruf di atas baseline
            $bawah = abs($bUkur[1]);                      // ruang descender

            $yMin = $atas + 4;                            // batas aman atas
            $yMax = $h - 4 - max($bawah, 2);              // batas aman bawah
            $yTengah = intdiv($h + $atas - $bawah, 2);    // baseline agar glyph terpusat
            $yAcak = [
                max($yMin, $yTengah - 3),
                min($yMax, $yTengah + 3),
            ];

            $padX = 14;
            $slot = intdiv($w - 2 * $padX, $len);
            for ($i = 0; $i < $len; $i++) {
                $x = $padX + 2 + $i * $slot;
                $y = random_int($yAcak[0], $yAcak[1]);
                imagettftext($img, $ukuran, 0, $x, $y, $navy, $fontTtf, $kode[$i]);
            }
        } else {
            // Cadangan tanpa TTF: font bawaan di-render besar lalu diperhalus
            // (supersampling). Skala & posisi dihitung dari ukuran font asli
            // supaya huruf selalu muat penuh di kanvas (tidak kepotong).
            $skala = 2;
            $cw = (int) ceil($w / $skala);
            $ch = (int) ceil($h / $skala);
            $kecil = imagecreatetruecolor($cw, $ch);
            $bgKecil = imagecolorallocate($kecil, 255, 255, 255);
            imagefilledrectangle($kecil, 0, 0, $cw, $ch, $bgKecil);

            $fontH = imagefontheight(5);
            $yMinK = 1;
            $yMaxK = $ch - $fontH - 1;
            $yTengahK = intdiv($ch - $fontH, 2);

            $slotKecil = intdiv($cw - 10, $len);
            for ($i = 0; $i < $len; $i++) {
                $x = 5 + $i * $slotKecil;
                $y = random_int(max($yMinK, $yTengahK - 2), min($yMaxK, $yTengahK + 2));
                imagestring($kecil, 5, $x, $y, $kode[$i], $navy);
            }

            imagecopyresampled($img, $kecil, 0, 0, 0, 0, $w, $h, $cw, $ch);
        }

        header('Content-Type: image/png');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        imagepng($img);
    }

    /** Cari font TTF yang tersedia di server (DejaVu di Linux/cPanel, Arial di macOS, dll). */
    private function cariFontTtf(): ?string
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache ?: null;
        }

        $kandidat = [
            '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf', // umum di Linux/cPanel
            '/usr/share/fonts/dejavu/DejaVuSans-Bold.ttf',
            '/usr/share/fonts/TTF/DejaVuSans-Bold.ttf',
            '/usr/share/fonts/truetype/liberation/LiberationSans-Bold.ttf',
            '/System/Library/Fonts/Supplemental/Arial Bold.ttf',     // macOS
            '/System/Library/Fonts/Supplemental/Arial.ttf',
            '/Library/Fonts/Arial Bold.ttf',
        ];

        foreach ($kandidat as $f) {
            if (is_readable($f)) {
                $cache = $f;

                return $f;
            }
        }

        $cache = false;

        return null;
    }
}