<?php

/*
|--------------------------------------------------------------------------
| Integrasi DigiTrans (Biro OSDMRB)
|--------------------------------------------------------------------------
| TransKinerja membaca database DigiTrans (satu server cPanel) sebagai sumber
| utama AKSES LOGIN & DATA PEGAWAI. Mapping tabel/kolom di bawah mengikuti
| skema asli DigiTrans (triventk_dashboard_osdmrb) — cukup ubah .env bila
| skema berubah, tanpa menyentuh kode.
|
| Skema asli (terkonfirmasi via `php artisan digitrans:cek`):
|   users(id, name, email, password bcrypt, employee_id→employees, is_active)
|   employees(nip, name, position_name/jabatan, unit_id→units, rank_id→ranks,
|             employment_status_id, education_level_id, gender L/P, birth_*,
|             religion, address, phone, tmt_*, retirement_date, education_1..3)
|   units(id, name, code) · ranks(id, name) · employment_statuses(id, name)
|   education_levels(id, code, name)
|
| Alur: user login → dicari di DB lokal → bila gagal/kosong, dicoba ke
| DigiTrans (users JOIN employees+referensi) → cocok → user lokal
| dibuat/diperbarui otomatis (provision) lengkap dengan data kepegawaiannya
| → sesi TransKinerja berjalan normal (OTP, audit, role tetap berlaku).
*/

return [
    // Master saklar: matikan semua integrasi tanpa deploy ulang
    'aktif' => env('DIGITRANS_AKTIF', false),

    'koneksi' => 'digitrans',

    // Tabel akun utama DigiTrans
    'tabel' => env('DIGITRANS_TABEL', 'users'),

    // Kolom dipakai dengan format "tabel.kolom" (tabel utama boleh tanpa prefiks).
    // Kolom yang tabel/kolomnya tidak ada di DigiTrans otomatis dilewati.
    'kolom' => [
        'id' => env('DIGITRANS_KOLOM_ID', 'users.id'),
        'username' => env('DIGITRANS_KOLOM_USERNAME'),              // tidak ada di DigiTrans → login pakai email/NIP
        'nip' => env('DIGITRANS_KOLOM_NIP', 'employees.nip'),
        'nama' => env('DIGITRANS_KOLOM_NAMA', 'employees.name'),
        'email' => env('DIGITRANS_KOLOM_EMAIL', 'users.email'),
        'password' => env('DIGITRANS_KOLOM_PASSWORD', 'users.password'),
        'jabatan' => env('DIGITRANS_KOLOM_JABATAN', 'employees.position_name'),
        'unit' => env('DIGITRANS_KOLOM_UNIT', 'units.name'),
        'unit_kode' => env('DIGITRANS_KOLOM_UNIT_KODE', 'units.code'),
        'aktif' => env('DIGITRANS_KOLOM_AKTIF', 'users.is_active'),

        // Data kepegawaian lanjutan (dari tabel employees + referensi)
        'pangkat' => env('DIGITRANS_KOLOM_PANGKAT', 'ranks.name'),
        'status_pegawai' => env('DIGITRANS_KOLOM_STATUS_PEGAWAI', 'employment_statuses.name'),
        'jenis_kelamin' => env('DIGITRANS_KOLOM_JENIS_KELAMIN', 'employees.gender'),
        'tempat_lahir' => env('DIGITRANS_KOLOM_TEMPAT_LAHIR', 'employees.birth_place'),
        'tanggal_lahir' => env('DIGITRANS_KOLOM_TANGGAL_LAHIR', 'employees.birth_date'),
        'agama' => env('DIGITRANS_KOLOM_AGAMA', 'employees.religion'),
        'alamat' => env('DIGITRANS_KOLOM_ALAMAT', 'employees.address'),
        'no_telp' => env('DIGITRANS_KOLOM_TELEPON', 'employees.phone'),
        'eselon' => env('DIGITRANS_KOLOM_ESELON', 'employees.eselon'),
        'fungsional' => env('DIGITRANS_KOLOM_FUNGSIONAL', 'employees.functional_level'),
        'tmt_jabatan' => env('DIGITRANS_KOLOM_TMT_JABATAN', 'employees.tmt_jabatan'),
        'kenaikan_pangkat' => env('DIGITRANS_KOLOM_KENAIKAN_PANGKAT', 'employees.next_promotion_date'),
        'pensiun' => env('DIGITRANS_KOLOM_PENSIUN', 'employees.retirement_date'),
        'pendidikan' => env('DIGITRANS_KOLOM_PENDIDIKAN', 'education_levels.name'),
        'pendidikan_1' => env('DIGITRANS_KOLOM_PENDIDIKAN_1', 'employees.education_1'),
        'pendidikan_2' => env('DIGITRANS_KOLOM_PENDIDIKAN_2', 'employees.education_2'),
        'pendidikan_3' => env('DIGITRANS_KOLOM_PENDIDIKAN_3', 'employees.education_3'),
    ],

    // LEFT JOIN tambahan ke tabel terkait (dilewati otomatis bila tidak ada).
    // Format: ['tabel' => …, 'on' => 'tabelA.kolomA = tabelB.kolomB']
    'join' => [
        ['tabel' => 'employees', 'on' => 'users.employee_id = employees.id'],
        ['tabel' => 'units', 'on' => 'employees.unit_id = units.id'],
        ['tabel' => 'ranks', 'on' => 'employees.rank_id = ranks.id'],
        ['tabel' => 'employment_statuses', 'on' => 'employees.employment_status_id = employment_statuses.id'],
        ['tabel' => 'education_levels', 'on' => 'employees.education_level_id = education_levels.id'],
    ],

    // Login memakai akun DigiTrans (password diverifikasi dari sana; akun
    // lokal dibuat/diperbarui otomatis saat login pertama).
    // false = hanya sinkronisasi data pegawai, tetap login pakai password lokal.
    'login_dari_digitrans' => env('DIGITRANS_LOGIN', true),

    // Sinkronisasi berkala: nama/NIP/jabatan/unit/pangkat/status/dst ikut DigiTrans.
    // Role, atasan, dan password lokal TIDAK disentuh.
    'sinkron_data' => env('DIGITRANS_SINKRON', true),

    // Auto-buat akun lokal saat pegawai DigiTrans pertama kali login
    'auto_provision' => env('DIGITRANS_AUTO_PROVISION', true),

    // Role bawaan user hasil provision/sinkronisasi (dinaikkan manual oleh admin)
    'role_bawaan' => env('DIGITRANS_ROLE_BAWAAN', 'pegawai'),

    // Email placeholder bila akun DigiTrans tanpa email (wajib unik di lokal)
    'email_placeholder_domain' => env('DIGITRANS_EMAIL_DOMAIN', 'digitrans.local'),
];
