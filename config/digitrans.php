<?php

/*
|--------------------------------------------------------------------------
| Integrasi DigiTrans (Biro OSDMRB)
|--------------------------------------------------------------------------
| TransKinerja dapat membaca database DigiTrans (satu server cPanel) sebagai
| sumber utama autentikasi & data pegawai. Mapping tabel/kolom di bawah
| menyesuaikan skema DigiTrans tanpa mengubah kode — cukup ubah .env.
|
| Alur: user login → dicari di DB lokal → bila gagal/kosong, dicoba ke
| DigiTrans → cocok → user lokal dibuat/diperbarui otomatis (provision)
| → sesi TransKinerja berjalan normal (OTP, audit, role tetap berlaku).
*/

return [
    // Master saklar: matikan semua integrasi tanpa deploy ulang
    'aktif' => env('DIGITRANS_AKTIF', false),

    'koneksi' => 'digitrans',

    // Tabel & kolom pada database DigiTrans (default = skema asli triventk_dashboard_osdmrb,
    // terkonfirmasi via `php artisan digitrans:cek`: tidak ada kolom username,
    // NIP tersimpan sebagai employee_id, status akun pada is_active)
    'tabel' => env('DIGITRANS_TABEL', 'users'),
    'kolom' => [
        'id' => env('DIGITRANS_KOLOM_ID', 'id'),
        'username' => env('DIGITRANS_KOLOM_USERNAME'),          // tidak ada di DigiTrans → login pakai email/NIP
        'nip' => env('DIGITRANS_KOLOM_NIP', 'employee_id'),
        'nama' => env('DIGITRANS_KOLOM_NAMA', 'name'),
        'email' => env('DIGITRANS_KOLOM_EMAIL', 'email'),
        'password' => env('DIGITRANS_KOLOM_PASSWORD', 'password'),
        'jabatan' => env('DIGITRANS_KOLOM_JABATAN'),            // tidak ada di DigiTrans
        'unit' => env('DIGITRANS_KOLOM_UNIT'),                  // tidak ada di DigiTrans
        'aktif' => env('DIGITRANS_KOLOM_AKTIF', 'is_active'),
    ],

    // Login memakai akun DigiTrans (password diverifikasi dari sana).
    // false = hanya sinkronisasi data pegawai, tetap login pakai password lokal.
    'login_dari_digitrans' => env('DIGITRANS_LOGIN', true),

    // Sinkronisasi berkala: nama/email/jabatan/unit ikut DigiTrans.
    // Role, atasan, unit kerja mapping, dan is_aktif TIDAK disentuh.
    'sinkron_data' => env('DIGITRANS_SINKRON', true),

    // Auto-buat akun lokal saat pegawai DigiTrans pertama kali login
    'auto_provision' => env('DIGITRANS_AUTO_PROVISION', true),

    // Role bawaan user hasil provision/sinkronisasi
    'role_bawaan' => env('DIGITRANS_ROLE_BAWAAN', 'pegawai'),

    // Email placeholder bila akun DigiTrans tanpa email (wajib unik di lokal)
    'email_placeholder_domain' => env('DIGITRANS_EMAIL_DOMAIN', 'digitrans.local'),
];
