<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\CaptchaController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LaporanController;
use App\Http\Controllers\LogBookController;
use App\Http\Controllers\ManajemenUserController;
use App\Http\Controllers\MonitoringController;
use App\Http\Controllers\NotifikasiController;
use App\Http\Controllers\PegawaiController;
use App\Http\Controllers\PencarianController;
use App\Http\Controllers\PersetujuanController;
use App\Http\Controllers\PresensiController;
use App\Http\Controllers\PengaturanController;
use App\Http\Controllers\UnitKerjaController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| TransKinerja — Kementerian Transmigrasi Republik Indonesia
|--------------------------------------------------------------------------
*/

// Guest
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'showLogin'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])->middleware('throttle:login')->name('login.attempt');
    Route::get('/captcha', [CaptchaController::class, 'image'])->name('captcha');

    // Verifikasi dua langkah (OTP) — aktif bila diaktifkan Superadmin di Pengaturan → OTP & Keamanan
    Route::get('/login/otp', [LoginController::class, 'showOtp'])->name('login.otp');
    Route::post('/login/otp', [LoginController::class, 'verifyOtp'])->middleware('throttle:login')->name('login.otp.verify');
    Route::post('/login/otp/kirim-ulang', [LoginController::class, 'resendOtp'])->middleware('throttle:login')->name('login.otp.resend');
    Route::post('/login/otp/batalkan', [LoginController::class, 'batalkanOtp'])->name('login.otp.batalkan');
});

Route::get('/', function () {
    return redirect()->route(auth()->check() ? 'dashboard' : 'login');
});

// Authenticated
Route::middleware(['auth'])->group(function () {

    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Notifikasi
    Route::get('/notifikasi', [NotifikasiController::class, 'index'])->name('notifikasi.index');
    Route::post('/notifikasi/{notifikasi}/baca', [NotifikasiController::class, 'baca'])->name('notifikasi.baca');
    Route::post('/notifikasi/baca-semua', [NotifikasiController::class, 'bacaSemua'])->name('notifikasi.bacaSemua');

    // Pencarian global
    Route::get('/pencarian', [PencarianController::class, 'index'])->name('pencarian');

    // Presensi & jam kerja (semua pengguna ASN terdaftar)
    Route::middleware('role:pegawai,atasan,admin_unit,superadmin')->group(function () {
        Route::get('/presensi', [PresensiController::class, 'index'])->name('presensi.index');
        Route::post('/presensi/clock-in', [PresensiController::class, 'clockIn'])->name('presensi.clockIn');
        Route::post('/presensi/clock-out', [PresensiController::class, 'clockOut'])->name('presensi.clockOut');
    });

    // Log Book (pegawai & atasan mencatat aktivitas sendiri)
    Route::middleware('role:pegawai,atasan,admin_unit,superadmin')->prefix('logbook')->name('logbook.')->group(function () {
        Route::get('/', [LogBookController::class, 'index'])->name('index');
        Route::get('/buat', [LogBookController::class, 'create'])->name('create');
        Route::post('/', [LogBookController::class, 'store'])->name('store');
        Route::get('/{logbook}', [LogBookController::class, 'show'])->name('show');
        Route::get('/{logbook}/ubah', [LogBookController::class, 'edit'])->name('edit');
        Route::put('/{logbook}', [LogBookController::class, 'update'])->name('update');
        Route::delete('/{logbook}', [LogBookController::class, 'destroy'])->name('destroy');
        Route::post('/{logbook}/ajukan', [LogBookController::class, 'ajukan'])->name('ajukan');
        Route::get('/lampiran/{lampiran}/unduh', [LogBookController::class, 'downloadLampiran'])->name('lampiran.download');
        Route::delete('/lampiran/{lampiran}', [LogBookController::class, 'hapusLampiran'])->name('lampiran.hapus');
    });

    // Laporan kinerja (semua peran, data sesuai cakupan)
    Route::get('/laporan', [LaporanController::class, 'index'])->name('laporan.index');
    Route::get('/laporan/cetak', [LaporanController::class, 'cetak'])->name('laporan.cetak');
    Route::get('/laporan/pdf', [LaporanController::class, 'pdf'])->name('laporan.pdf');
    Route::get('/laporan/export', [LaporanController::class, 'export'])->name('laporan.export');

    // Persetujuan (atasan, admin unit, admin pusat)
    Route::middleware('role:atasan,admin_unit,superadmin')->prefix('persetujuan')->name('persetujuan.')->group(function () {
        Route::get('/', [PersetujuanController::class, 'index'])->name('index');
        Route::get('/riwayat', [PersetujuanController::class, 'riwayat'])->name('riwayat');
        Route::get('/{persetujuan}', [PersetujuanController::class, 'show'])->name('show');
        Route::post('/{persetujuan}/setujui', [PersetujuanController::class, 'setujui'])->name('setujui');
        Route::post('/{persetujuan}/revisi', [PersetujuanController::class, 'revisi'])->name('revisi');
        Route::post('/{persetujuan}/buka-kembali', [PersetujuanController::class, 'bukaKembali'])->name('bukaKembali');
    });

    // Monitoring & analitik
    Route::middleware('role:atasan,admin_unit,superadmin')->get('/monitoring', [MonitoringController::class, 'index'])->name('monitoring.index');

    // Pegawai & unit kerja (admin unit & admin pusat)
    Route::middleware('role:admin_unit,superadmin')->group(function () {
        Route::get('/pegawai/template-import', [PegawaiController::class, 'template'])->name('pegawai.template');
        Route::resource('pegawai', PegawaiController::class)->except(['show']);
        Route::post('/pegawai/import', [PegawaiController::class, 'import'])->name('pegawai.import');
        Route::post('/pegawai/sinkron-digitrans', [PegawaiController::class, 'sinkronDigitrans'])->name('pegawai.sinkronDigitrans');
        Route::post('/pegawai/{pegawai}/reset-password', [PegawaiController::class, 'resetPassword'])->name('pegawai.resetPassword');

        Route::middleware('role:superadmin')->group(function () {
            Route::get('/unit-kerja', [UnitKerjaController::class, 'index'])->name('unit-kerja.index');
            Route::post('/unit-kerja', [UnitKerjaController::class, 'store'])->name('unit-kerja.store');
            Route::put('/unit-kerja/{unitKerja}', [UnitKerjaController::class, 'update'])->name('unit-kerja.update');
        });
    });

    // Manajemen User — daftar & mapping akses login (menu tersendiri, superadmin)
    Route::middleware('role:superadmin')->prefix('manajemen-user')->name('manajemen-user.')->group(function () {
        Route::get('/', [ManajemenUserController::class, 'index'])->name('index');
        Route::post('/{akses}', [ManajemenUserController::class, 'update'])->name('update');
        Route::post('/{akses}/toggle', [ManajemenUserController::class, 'toggle'])->name('toggle');
    });

    // Pengaturan — satu halaman bertab
    Route::prefix('pengaturan')->name('pengaturan.')->group(function () {
        Route::get('/', [PengaturanController::class, 'index'])->name('index');
        Route::put('/profil', [PengaturanController::class, 'profilUpdate'])->name('profil.update');

        // URL lama → arahkan ke tab terkait
        Route::get('/profil', fn () => redirect()->route('pengaturan.index', ['tab' => 'profil']))->name('profil');

        Route::middleware('role:superadmin')->group(function () {
            Route::post('/smtp', [PengaturanController::class, 'smtpStore'])->name('smtp.store');
            Route::post('/smtp/test', [PengaturanController::class, 'smtpTest'])->name('smtp.test');
            Route::post('/otp', [PengaturanController::class, 'otpStore'])->name('otp.store');
            Route::post('/periode', [PengaturanController::class, 'periodeStore'])->name('periode.store');
            Route::post('/periode/{periode}/toggle', [PengaturanController::class, 'periodeToggle'])->name('periode.toggle');
            Route::post('/referensi/jenis-tugas', [PengaturanController::class, 'taskTypeStore'])->name('taskType.store');
            Route::post('/referensi/jenis-tugas/{taskType}/toggle', [PengaturanController::class, 'taskTypeToggle'])->name('taskType.toggle');
            Route::post('/referensi/output', [PengaturanController::class, 'outputStore'])->name('output.store');
            Route::post('/referensi/output/{outputRef}/toggle', [PengaturanController::class, 'outputToggle'])->name('output.toggle');
            Route::get('/periode', fn () => redirect()->route('pengaturan.index', ['tab' => 'periode']))->name('periode');
            Route::get('/referensi', fn () => redirect()->route('pengaturan.index', ['tab' => 'referensi']))->name('referensi');
            Route::get('/audit', fn () => redirect()->route('pengaturan.index', ['tab' => 'audit']))->name('audit');
            Route::get('/otp', fn () => redirect()->route('pengaturan.index', ['tab' => 'otp']))->name('otp');
        });
    });
});
