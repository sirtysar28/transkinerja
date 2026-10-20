<?php

namespace Tests\Feature;

use App\Mail\OtpEmail;
use App\Models\OtpCode;
use App\Models\Pengaturan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Modul OTP login (verifikasi dua langkah) + Manajemen Akses Login (Pengaturan).
 */
class OtpAksesLoginTest extends TestCase
{
    use RefreshDatabase;

    private function sesiCaptcha(): array
    {
        return ['captcha_code' => 'A7K2M', 'captcha_expiry' => time() + 600];
    }

    private function buatUser(array $attr = []): User
    {
        $username = $attr['username'] ?? 'anggit';

        return User::factory()->create(array_merge([
            'username' => $username,
            'email' => $username.'@transmigrasi.go.id',
            'password' => 'rahasia',
        ], $attr));
    }

    /** Aktifkan OTP seolah disimpan superadmin lewat Pengaturan → OTP & Keamanan. */
    private function aktifkanOtp(): void
    {
        Pengaturan::set('smtp_host', 'smtp.transmigrasi.go.id');
        Pengaturan::set('otp_aktif', '1');
        Pengaturan::set('otp_panjang', '6');
        Pengaturan::set('otp_masa_berlaku', '10');
        Pengaturan::set('otp_maks_percobaan', '5');
    }

    /** Login langkah 1 (password benar) lalu ambil kode OTP dari email yang "terkirim". */
    private function loginDanAmbilKode(User $user): string
    {
        $kode = null;
        Mail::assertSent(function (OtpEmail $mail) use (&$kode) {
            $kode = $mail->otp->kodePlain;

            return true;
        });

        if ($kode === null) {
            $this->fail('Email OTP tidak terkirim.');
        }

        return $kode;
    }

    // ================= OTP: ON/OFF =================

    public function test_otp_off_login_langsung_tanpa_verifikasi(): void
    {
        $user = $this->buatUser();

        $this->withSession($this->sesiCaptcha())
            ->post('/login', ['login' => 'anggit', 'password' => 'rahasia', 'captcha' => 'a7k2m'])
            ->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->last_login_at, 'last_login_at tercatat.');
    }

    public function test_otp_on_password_benar_diarahkan_ke_halaman_otp(): void
    {
        $this->aktifkanOtp();
        $this->buatUser();
        Mail::fake();

        $respon = $this->withSession($this->sesiCaptcha())
            ->post('/login', ['login' => 'anggit', 'password' => 'rahasia', 'captcha' => 'a7k2m']);

        $respon->assertRedirect(route('login.otp'));
        $this->assertGuest(); // sesi belum boleh dibuat sebelum OTP benar
        Mail::assertSent(OtpEmail::class);
        $this->get(route('login.otp'))->assertOk()->assertSee('Verifikasi OTP');
    }

    public function test_kode_otp_disimpan_sebagai_hash(): void
    {
        $this->aktifkanOtp();
        $user = $this->buatUser();
        Mail::fake();

        $this->withSession($this->sesiCaptcha())
            ->post('/login', ['login' => 'anggit', 'password' => 'rahasia', 'captcha' => 'a7k2m']);

        $kode = $this->loginDanAmbilKode($user);
        $baris = OtpCode::where('user_id', $user->id)->whereNull('dipakai_at')->first();

        $this->assertNotSame($kode, $baris->kode, 'Kode asli tidak boleh tersimpan di database.');
        $this->assertSame(hash('sha256', $kode), $baris->kode);
    }

    public function test_otp_benar_sesi_terbuat_dan_last_login_tercatat(): void
    {
        $this->aktifkanOtp();
        $user = $this->buatUser();
        Mail::fake();

        $this->withSession($this->sesiCaptcha())
            ->post('/login', ['login' => 'anggit', 'password' => 'rahasia', 'captcha' => 'a7k2m'])
            ->assertRedirect(route('login.otp'));

        $kode = $this->loginDanAmbilKode($user);

        $this->post(route('login.otp.verify'), ['kode' => $kode])
            ->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->last_login_at);
    }

    public function test_otp_salah_dan_kedaluwarsa_ditolak(): void
    {
        $this->aktifkanOtp();
        $user = $this->buatUser();
        Mail::fake();

        $this->withSession($this->sesiCaptcha())
            ->post('/login', ['login' => 'anggit', 'password' => 'rahasia', 'captcha' => 'a7k2m']);
        $kode = $this->loginDanAmbilKode($user);

        // Kode salah — selalu berbeda dari kode asli (modulo 1.000.000)
        $salah = str_pad((string) ((((int) $kode) + 1) % 1000000), 6, '0', STR_PAD_LEFT);
        $this->post(route('login.otp.verify'), ['kode' => $salah])
            ->assertSessionHasErrors('kode');
        $this->assertGuest();

        // Kode kedaluwarsa
        OtpCode::where('user_id', $user->id)->whereNull('dipakai_at')->update(['kedaluwarsa_at' => now()->subMinute()]);
        $this->post(route('login.otp.verify'), ['kode' => $kode])
            ->assertSessionHasErrors('kode');
        $this->assertGuest();
    }

    public function test_otp_kirim_ulang_menggugurkan_kode_lama(): void
    {
        $this->aktifkanOtp();
        $user = $this->buatUser();
        Mail::fake();

        $this->withSession($this->sesiCaptcha())
            ->post('/login', ['login' => 'anggit', 'password' => 'rahasia', 'captcha' => 'a7k2m']);
        $kodeLama = $this->loginDanAmbilKode($user);

        $this->post(route('login.otp.resend'))->assertSessionHas('sukses');

        $kodeBaru = $this->loginDanAmbilKode($user);
        $this->assertNotSame($kodeLama, $kodeBaru);

        // Kode lama tidak lagi sah
        $this->post(route('login.otp.verify'), ['kode' => $kodeLama])
            ->assertSessionHasErrors('kode');
        $this->assertGuest();
    }

    public function test_otp_on_user_tanpa_email_ditolak(): void
    {
        $this->aktifkanOtp();
        $this->buatUser(['email' => null]);
        Mail::fake();

        $this->withSession($this->sesiCaptcha())
            ->post('/login', ['login' => 'anggit', 'password' => 'rahasia', 'captcha' => 'a7k2m'])
            ->assertSessionHasErrors('login');

        $this->assertGuest();
        Mail::assertNothingSent();
    }

    public function test_halaman_otp_tanpa_proses_login_ditOLAK(): void
    {
        $this->get(route('login.otp'))->assertRedirect(route('login'));
    }

    // ================= Pengaturan OTP =================

    public function test_superadmin_bisa_menyimpan_pengaturan_otp(): void
    {
        $super = $this->buatUser(['username' => 'superadmin', 'role' => 'superadmin']);
        Pengaturan::set('smtp_host', 'smtp.transmigrasi.go.id');

        $this->actingAs($super)
            ->post(route('pengaturan.otp.store'), [
                'otp_aktif' => '1',
                'otp_panjang' => 6,
                'otp_masa_berlaku' => 10,
                'otp_maks_percobaan' => 5,
            ])
            ->assertSessionHas('sukses');

        $this->assertSame('1', Pengaturan::get('otp_aktif'));

        // Matikan kembali
        $this->actingAs($super)
            ->post(route('pengaturan.otp.store'), [
                'otp_panjang' => 6,
                'otp_masa_berlaku' => 10,
                'otp_maks_percobaan' => 5,
            ])
            ->assertSessionHas('sukses');
        $this->assertSame('0', Pengaturan::get('otp_aktif'));
    }

    public function test_otp_tidak_bisa_dinyalakan_tanpa_smtp(): void
    {
        $super = $this->buatUser(['username' => 'superadmin', 'role' => 'superadmin']);

        $this->actingAs($super)
            ->post(route('pengaturan.otp.store'), [
                'otp_aktif' => '1',
                'otp_panjang' => 6,
                'otp_masa_berlaku' => 10,
                'otp_maks_percobaan' => 5,
            ])
            ->assertSessionHas('gagal');

        $this->assertNotSame('1', Pengaturan::get('otp_aktif'));
    }

    public function test_otp_hanya_superadmin(): void
    {
        $pegawai = $this->buatUser(['role' => 'pegawai']);

        $this->actingAs($pegawai)
            ->post(route('pengaturan.otp.store'), [
                'otp_aktif' => '1', 'otp_panjang' => 6, 'otp_masa_berlaku' => 10, 'otp_maks_percobaan' => 5,
            ])
            ->assertForbidden();
    }

    // ================= Manajemen Akses Login =================

    public function test_halaman_manajemen_user_menampilkan_daftar_dan_status_akses(): void
    {
        $super = $this->buatUser(['username' => 'superadmin', 'role' => 'superadmin']);
        $this->buatUser(['username' => 'nonaktifuser', 'is_aktif' => false]);

        $this->actingAs($super)
            ->get(route('manajemen-user.index'))
            ->assertOk()
            ->assertSee('Manajemen User')
            ->assertSee('Mapping Akses Login')
            ->assertSee('Punya akses login (aktif)')
            ->assertSee('nonaktifuser');
    }

    public function test_url_lama_tab_akses_dialihkan_ke_menu_baru(): void
    {
        $super = $this->buatUser(['username' => 'superadmin', 'role' => 'superadmin']);

        $this->actingAs($super)
            ->get('/pengaturan?tab=akses')
            ->assertRedirect(route('manajemen-user.index'));
    }

    public function test_mapping_akses_ubah_peran_username_dan_email(): void
    {
        $super = $this->buatUser(['username' => 'superadmin', 'role' => 'superadmin']);
        $pegawai = $this->buatUser(['username' => 'p1', 'role' => 'pegawai']);

        $this->actingAs($super)
            ->post(route('manajemen-user.update', $pegawai), [
                'username' => 'p1baru',
                'email' => 'p1@transmigrasi.go.id',
                'role' => 'atasan',
                'is_aktif' => '1',
                'password' => 'sandibaru',
            ])
            ->assertSessionHas('sukses');

        $pegawai->refresh();
        $this->assertSame('atasan', $pegawai->role);
        $this->assertSame('p1baru', $pegawai->username);
        $this->assertSame('p1@transmigrasi.go.id', $pegawai->email);
        $this->assertTrue((bool) $pegawai->is_aktif);
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('sandibaru', $pegawai->password));
    }

    public function test_cabut_dan_aktifkan_kembali_akses_login(): void
    {
        $super = $this->buatUser(['username' => 'superadmin', 'role' => 'superadmin']);
        $pegawai = $this->buatUser(['username' => 'p1', 'role' => 'pegawai']);

        // Cabut akses → keluar dari sesi admin, lalu buktikan p1 tidak bisa login
        $this->actingAs($super)->post(route('manajemen-user.toggle', $pegawai))->assertSessionHas('sukses');
        $this->assertFalse((bool) $pegawai->fresh()->is_aktif);
        $this->post('/logout');

        $this->withSession($this->sesiCaptcha())
            ->post('/login', ['login' => 'p1', 'password' => 'rahasia', 'captcha' => 'a7k2m'])
            ->assertSessionHasErrors('login');
        $this->assertGuest();

        // Aktifkan kembali
        $this->actingAs($super)->post(route('manajemen-user.toggle', $pegawai))->assertSessionHas('sukses');
        $this->assertTrue((bool) $pegawai->fresh()->is_aktif);
    }

    public function test_superadmin_tidak_bisa_mencabut_akses_sendiri(): void
    {
        $super = $this->buatUser(['username' => 'superadmin', 'role' => 'superadmin']);

        $this->actingAs($super)
            ->post(route('manajemen-user.toggle', $super))
            ->assertSessionHas('gagal');
        $this->assertTrue((bool) $super->fresh()->is_aktif);
    }

    public function test_superadmin_terakhir_tidak_bisa_diturunkan(): void
    {
        $super = $this->buatUser(['username' => 'superadmin', 'role' => 'superadmin']);

        $this->actingAs($super)
            ->post(route('manajemen-user.update', $super), [
                'username' => 'superadmin',
                'role' => 'pegawai', // dicoba turunkan lewat mapping (bukan toggle sendiri)
                'is_aktif' => '1',
            ])
            ->assertSessionHas('gagal');
        $this->assertSame('superadmin', $super->fresh()->role);
    }

    public function test_akses_login_hanya_superadmin(): void
    {
        $adminUnit = $this->buatUser(['role' => 'admin_unit']);
        $pegawai = $this->buatUser(['username' => 'p1', 'role' => 'pegawai']);

        // Halaman menu & aksi mapping hanya untuk superadmin
        $this->actingAs($adminUnit)->get(route('manajemen-user.index'))->assertForbidden();
        $this->actingAs($adminUnit)
            ->post(route('manajemen-user.update', $pegawai), [
                'username' => 'direbut', 'role' => 'superadmin', 'is_aktif' => '1',
            ])
            ->assertForbidden();
    }

    public function test_menu_manajemen_user_hanya_tampil_di_sidebar_superadmin(): void
    {
        $super = $this->buatUser(['username' => 'superadmin', 'role' => 'superadmin']);
        $pegawai = $this->buatUser(['username' => 'p1', 'role' => 'pegawai']);

        $this->actingAs($super)->get('/dashboard')->assertOk()->assertSee('Manajemen User');
        $this->actingAs($pegawai)->get('/dashboard')->assertOk()->assertDontSee('Manajemen User');
    }
}
