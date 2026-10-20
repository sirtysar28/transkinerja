<?php

namespace App\Providers;

use App\Models\AppNotification;
use App\Models\LogKegiatan;
use App\Policies\LogKegiatanPolicy;
use Carbon\Carbon;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use App\Models\Pengaturan;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Carbon::setLocale(config('app.locale', 'id'));

        // Konfigurasi SMTP dinamis dari halaman Pengaturan (superadmin).
        // Aman dijalankan saat instalasi awal/migrasi — bila tabel belum siap, dilewati.
        try {
            if (Schema::hasTable('pengaturans') && Pengaturan::get('smtp_host')) {
                $enkripsi = Pengaturan::get('smtp_enkripsi', 'tls');
                config([
                    'mail.default' => 'smtp',
                    'mail.mailers.smtp.host' => Pengaturan::get('smtp_host'),
                    'mail.mailers.smtp.port' => (int) Pengaturan::get('smtp_port', 587),
                    'mail.mailers.smtp.encryption' => $enkripsi === 'none' ? null : $enkripsi,
                    'mail.mailers.smtp.username' => Pengaturan::get('smtp_username'),
                    'mail.mailers.smtp.password' => Pengaturan::get('smtp_password'),
                    'mail.from.address' => Pengaturan::get('mail_from_address', config('mail.from.address')),
                    'mail.from.name' => Pengaturan::get('mail_from_name', config('mail.from.name')),
                ]);
            }
        } catch (\Throwable) {
            // Tabel pengaturan belum tersedia (migrasi pertama / artisan pertama kali).
        }

        // Policy
        Gate::policy(LogKegiatan::class, LogKegiatanPolicy::class);

        // Rate limiter percobaan login
        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(10)->by(strtolower($request->input('login', '')).'|'.$request->ip());
        });

        // Data bersama untuk header layout (notifikasi belum dibaca)
        View::composer('layouts.partials.header', function ($view) {
            $user = auth()->user();
            $notifikasiBelum = collect();
            $notifikasiTerbaru = collect();
            if ($user) {
                $notifikasiBelum = AppNotification::where('user_id', $user->id)
                    ->whereNull('dibaca_at')->orderByDesc('created_at')->limit(5)->get();
                $notifikasiTerbaru = AppNotification::where('user_id', $user->id)
                    ->orderByDesc('created_at')->limit(5)->get();
            }
            $view->with([
                'notifikasiBelum' => $notifikasiBelum,
                'notifikasiTerbaru' => $notifikasiTerbaru,
                'jumlahBelumBaca' => $notifikasiBelum->count(),
            ]);
        });
    }
}
