<?php

namespace App\Http\Controllers;

use App\Services\CaptchaService;
use Symfony\Component\HttpFoundation\Response;

class CaptchaController extends Controller
{
    /**
     * Setiap pemanggilan menghasilkan kode baru + gambar baru (tombol "Ganti").
     * Dirender via output buffer agar kompatibel dengan web & pengujian otomatis.
     */
    public function image(CaptchaService $captcha): Response
    {
        $kode = $captcha->generate();

        ob_start();
        $captcha->renderImage($kode);
        $gambar = ob_get_clean();

        return response($gambar, 200, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
            'Pragma' => 'no-cache',
        ]);
    }
}
