<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\AuditService;
use App\Services\CaptchaService;
use App\Services\DigitransService;
use App\Services\OtpService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;

class LoginController extends Controller
{
    public function showLogin()
    {
        return view('auth.login');
    }

    public function login(Request $request, CaptchaService $captcha)
    {
        $credentials = $request->validate([
            'login' => ['required', 'string'],
            'password' => ['required', 'string'],
            'captcha' => ['required', 'string', 'size:5'],
        ], [
            'login.required' => 'Username atau NIP wajib diisi.',
            'password.required' => 'Password wajib diisi.',
            'captcha.required' => 'Kode CAPTCHA wajib diisi.',
            'captcha.size' => 'Kode CAPTCHA harus 5 karakter.',
        ]);

        // Verifikasi CAPTCHA di server (sekali pakai, ber masa berlaku).
        if (! $captcha->verify($credentials['captcha'])) {
            return back()->withInput($request->except('captcha'))
                ->withErrors(['captcha' => 'Kode CAPTCHA salah atau kedaluwarsa. Silakan coba lagi.']);
        }

        // Pembatasan percobaan login (throttling) per kombinasi IP + akun.
        $key = strtolower($credentials['login']).'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);

            throw ValidationException::withMessages([
                'login' => "Terlalu banyak percobaan login. Coba lagi dalam {$seconds} detik.",
            ]);
        }

        // Cari user berdasarkan username atau NIP (normalisasi spasi).
        $identifier = str_replace(' ', '', trim($credentials['login']));
        $user = User::where('username', $identifier)
            ->orWhere('nip', $identifier)
            ->orWhereRaw("REPLACE(nip, ' ', '') = ?", [$identifier])
            ->first();

        $passwordBenar = $user && Auth::validate(['id' => $user->id, 'password' => $credentials['password']]);

        // ===== Integrasi DigiTrans (Biro OSDMRB) =====
        // Bila user tidak ada / password lokal tidak cocok, verifikasi ke
        // database DigiTrans — cocok → akun lokal dibuat/diperbarui otomatis.
        if (! $passwordBenar && DigitransService::aktif()) {
            $userDigitrans = DigitransService::autentikasi($identifier, $credentials['password']);
            if ($userDigitrans) {
                $user = $userDigitrans;
                $passwordBenar = true;
                AuditService::catat('login-digitrans', 'Autentikasi', "User {$user->username} terverifikasi via basis data DigiTrans.");
            }
        }

        if (! $passwordBenar) {
            RateLimiter::hit($key, 60);

            return back()->withInput($request->except('captcha'))
                ->withErrors(['login' => 'Username/NIP atau password salah.']);
        }

        if (! $user->is_aktif) {
            return back()->withErrors(['login' => 'Akun Anda dinonaktifkan. Hubungi administrator.']);
        }

        RateLimiter::clear($key);

        // ===== Verifikasi dua langkah (OTP) bila diaktifkan Superadmin =====
        // Password benar belum berarti masuk: kode OTP dikirim ke email terdaftar,
        // user harus memasukkannya di halaman verifikasi sebelum sesi dibuat.
        if (OtpService::aktif()) {
            if (! $user->email) {
                return back()->withErrors(['login' => 'Akun Anda belum memiliki email terdaftar sehingga kode OTP tidak dapat dikirim. Hubungi administrator (menu Manajemen User) untuk melengkapi email.']);
            }

            $otp = OtpService::kirim($user);
            if (! $otp) {
                return back()->withErrors(['login' => 'Gagal mengirim kode OTP ke email Anda. Hubungi administrator untuk memeriksa konfigurasi SMTP (Pengaturan → SMTP & Email).']);
            }

            $request->session()->put([
                'otp_user_id' => $user->id,
                'otp_kedaluwarsa' => $otp->kedaluwarsa_at->timestamp,
                'otp_ingat' => $request->boolean('ingat'),
            ]);
            AuditService::catat('otp-kirim', 'Autentikasi', "User {$user->username} lolos password — kode OTP dikirim ke email.");

            return redirect()->route('login.otp');
        }

        return $this->masukkanUser($request, $user);
    }

    /** Finalisasi sesi login (dipakai jalur langsung & jalur OTP). */
    private function masukkanUser(Request $request, User $user)
    {
        Auth::login($user, $request->boolean('ingat') || (bool) $request->session()->pull('otp_ingat'));
        $request->session()->regenerate();
        $user->update(['last_login_at' => now()]);

        AuditService::catat('login', 'Autentikasi', "User {$user->username} berhasil masuk.");

        return redirect()->intended(route('dashboard'));
    }

    // ---------- Verifikasi OTP ----------

    /** User yang sedang menunggu verifikasi OTP (tersimpan di session, bukan di URL). */
    private function otpUser(Request $request): ?User
    {
        $user = User::find($request->session()->get('otp_user_id'));

        if (! $user || ! $user->is_aktif) {
            $request->session()->forget(['otp_user_id', 'otp_kedaluwarsa', 'otp_ingat']);

            return null;
        }

        return $user;
    }

    public function showOtp(Request $request)
    {
        $user = $this->otpUser($request);
        if (! $user) {
            return redirect()->route('login');
        }

        return view('auth.otp', [
            'user' => $user,
            'email' => OtpService::sembunyikanEmail($user->email),
            'kedaluwarsa' => $request->session()->get('otp_kedaluwarsa'),
            'panjang' => OtpService::panjang(),
        ]);
    }

    public function verifyOtp(Request $request)
    {
        $user = $this->otpUser($request);
        if (! $user) {
            return redirect()->route('login');
        }

        $credentials = $request->validate([
            'kode' => ['required', 'string', 'digits_between:4,8'],
        ], [
            'kode.required' => 'Kode OTP wajib diisi.',
            'kode.digits_between' => 'Kode OTP harus 4–8 digit angka.',
        ]);

        [$sukses, $pesan] = OtpService::verifikasi($user, $credentials['kode']);
        if (! $sukses) {
            return back()->withErrors(['kode' => $pesan]);
        }

        $request->session()->forget(['otp_user_id', 'otp_kedaluwarsa']);
        $respon = $this->masukkanUser($request, $user);
        AuditService::catat('login-otp', 'Autentikasi', "User {$user->username} lolos verifikasi OTP.");

        return $respon;
    }

    /** Kirim ulang kode OTP — dibatasi 1x per menit per user+IP. */
    public function resendOtp(Request $request)
    {
        $user = $this->otpUser($request);
        if (! $user) {
            return redirect()->route('login');
        }

        $key = 'otp-kirim|'.$user->id.'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 1)) {
            $detik = RateLimiter::availableIn($key);

            return back()->withErrors(['kode' => "Mohon tunggu {$detik} detik sebelum meminta kode baru."]);
        }
        RateLimiter::hit($key, 60);

        $otp = OtpService::kirim($user);
        if (! $otp) {
            return back()->withErrors(['kode' => 'Gagal mengirim ulang kode OTP. Hubungi administrator.']);
        }

        $request->session()->put('otp_kedaluwarsa', $otp->kedaluwarsa_at->timestamp);

        return back()->with('sukses', 'Kode OTP baru telah dikirim ke '.$user->email.'.');
    }

    /** Batalkan proses OTP dan kembali ke halaman login. */
    public function batalkanOtp(Request $request)
    {
        $request->session()->forget(['otp_user_id', 'otp_kedaluwarsa', 'otp_ingat']);

        return redirect()->route('login');
    }

    public function logout(Request $request)
    {
        AuditService::catat('logout', 'Autentikasi', 'User keluar dari aplikasi.');
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
