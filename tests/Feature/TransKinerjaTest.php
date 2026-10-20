<?php

namespace Tests\Feature;

use App\Mail\NotifikasiEmail;
use App\Models\ApprovalLog;
use App\Models\LogKegiatan;
use App\Models\OutputRef;
use App\Models\Pengaturan;
use App\Models\TaskType;
use App\Models\UnitKerja;
use App\Models\User;
use App\Services\NotifikasiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class TransKinerjaTest extends TestCase
{
    use RefreshDatabase;

    private function sesiCaptcha(): array
    {
        return ['captcha_code' => 'A7K2M', 'captcha_expiry' => time() + 600];
    }

    private function buatUnit(): int
    {
        return UnitKerja::firstOrCreate(['kode' => 'TEST'], ['nama' => 'Unit Testing'])->id;
    }

    public function test_halaman_login_dan_captcha_tersedia(): void
    {
        $this->get('/login')->assertOk()->assertSee('Masuk ke akun Anda');
        $this->get('/captcha')->assertOk()->assertHeader('Content-Type', 'image/png');
    }

    public function test_login_gagal_bila_captcha_salah(): void
    {
        User::factory()->create(['username' => 'budi', 'password' => 'rahasia']);

        $this->post('/login', [
            'login' => 'budi', 'password' => 'rahasia', 'captcha' => 'XXXXX',
        ])->assertSessionHasErrors('captcha');

        $this->assertGuest();
    }

    public function test_login_berhasil_dengan_username_atau_nip(): void
    {
        $user = User::factory()->create([
            'username' => 'anggit', 'nip' => '19901020201803 1001', 'password' => 'rahasia',
        ]);

        // via username
        $this->withSession($this->sesiCaptcha())
            ->post('/login', ['login' => 'anggit', 'password' => 'rahasia', 'captcha' => 'a7k2m'])
            ->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
        $this->post('/logout');

        // via NIP (tanpa spasi)
        $this->withSession($this->sesiCaptcha())
            ->post('/login', ['login' => '199010202018031001', 'password' => 'rahasia', 'captcha' => 'A7K2M'])
            ->assertRedirect('/dashboard');
        $this->assertAuthenticated();
    }

    public function test_dashboard_semua_peran(): void
    {
        $unit = $this->buatUnit();
        foreach (['pegawai', 'atasan', 'admin_unit', 'superadmin'] as $role) {
            $user = User::factory()->create(['role' => $role, 'unit_kerja_id' => $unit]);
            $this->actingAs($user)->get('/dashboard')->assertOk();
        }
    }

    public function test_alur_lengkap_logbook_sampai_disetujui(): void
    {
        $output = OutputRef::create(['nama' => 'Dokumen']);
        $kp = TaskType::create(['kode' => 'KP', 'nama' => 'KP — Kegiatan Penugasan']);
        $atasan = User::factory()->create(['role' => 'atasan']);
        $pegawai = User::factory()->create(['role' => 'pegawai', 'atasan_id' => $atasan->id]);

        // 1. Pegawai membuat log (draft)
        $res = $this->actingAs($pegawai)->post('/logbook', [
            'tanggal' => today()->toDateString(),
            'nama_kegiatan' => 'Menyusun ND kolaborasi HUT RI',
            'output_id' => $output->id,
            'pengguna' => 'Biro OSDM',
            'jam_mulai' => '08:00',
            'jam_selesai' => '16:00',
            'jenis_tugas' => [$kp->id],
            'aksi' => 'simpan',
        ]);
        $res->assertSessionHasNoErrors();

        $log = LogKegiatan::where('user_id', $pegawai->id)->first();
        $this->assertNotNull($log);
        $this->assertEquals('draft', $log->status);
        $this->assertEquals(480, $log->durasiMenit());

        // 2. Diajukan
        $this->actingAs($pegawai)->post("/logbook/{$log->id}/ajukan")->assertSessionHasNoErrors();
        $this->assertEquals('diajukan', $log->fresh()->status);

        // Notifikasi ke atasan
        $this->assertDatabaseHas('app_notifications', ['user_id' => $atasan->id]);

        // 3. Pegawai tidak bisa mengubah yang sudah diajukan
        $this->actingAs($pegawai)->put("/logbook/{$log->id}", [
            'tanggal' => today()->toDateString(),
            'nama_kegiatan' => 'Diubah',
            'output_id' => $output->id,
            'pengguna' => 'Biro OSDM',
            'jam_mulai' => '08:00',
            'jam_selesai' => '16:00',
            'jenis_tugas' => [$kp->id],
        ])->assertForbidden();

        // 4. Atasan mengembalikan untuk revisi + catatan
        $this->actingAs($atasan)->post("/persetujuan/{$log->id}/revisi", ['catatan' => 'Lengkapi bukti dukung.'])
            ->assertSessionHasNoErrors();
        $this->assertEquals('revisi', $log->fresh()->status);
        $this->assertEquals('Lengkapi bukti dukung.', $log->fresh()->catatan_revisi);

        // 5. Pegawai memperbaiki lalu mengajukan ulang
        $this->actingAs($pegawai)->put("/logbook/{$log->id}", [
            'tanggal' => today()->toDateString(),
            'nama_kegiatan' => 'Menyusun ND kolaborasi HUT RI (revisi)',
            'output_id' => $output->id,
            'pengguna' => 'Biro OSDM',
            'jam_mulai' => '08:00',
            'jam_selesai' => '16:00',
            'jenis_tugas' => [$kp->id],
        ])->assertSessionHasNoErrors();
        $this->actingAs($pegawai)->post("/logbook/{$log->id}/ajukan");

        // 6. Atasan menyetujui
        $this->actingAs($atasan)->post("/persetujuan/{$log->id}/setujui")->assertSessionHasNoErrors();
        $this->assertEquals('disetujui', $log->fresh()->status);

        // Riwayat persetujuan lengkap
        $aksi = ApprovalLog::where('log_kegiatan_id', $log->id)->pluck('aksi')->all();
        $this->assertEqualsCanonicalizing(['ajukan', 'revisi', 'ajukan', 'setujui'], $aksi);
    }

    public function test_laporan_disetujui_tidak_bisa_diedit_kecuali_superadmin(): void
    {
        $output = OutputRef::create(['nama' => 'Dokumen']);
        $kp = TaskType::create(['kode' => 'KP', 'nama' => 'KP']);
        $atasan = User::factory()->create(['role' => 'atasan']);
        $pegawai = User::factory()->create(['role' => 'pegawai', 'atasan_id' => $atasan->id]);
        $pusat = User::factory()->create(['role' => 'superadmin']);

        $log = LogKegiatan::create([
            'user_id' => $pegawai->id, 'tanggal' => today(),
            'nama_kegiatan' => 'Tes', 'output_id' => $output->id,
            'pengguna' => 'X', 'status' => 'disetujui',
        ]);
        $log->taskTypes()->attach($kp->id);

        $payload = [
            'tanggal' => today()->toDateString(), 'nama_kegiatan' => 'Ubah',
            'output_id' => $output->id, 'pengguna' => 'X',
            'jam_mulai' => null, 'jam_selesai' => null, 'jenis_tugas' => [$kp->id],
        ];

        $this->actingAs($pegawai)->put("/logbook/{$log->id}", $payload)->assertForbidden();
        $this->actingAs($atasan)->put("/logbook/{$log->id}", $payload)->assertForbidden();
        $this->actingAs($pusat)->put("/logbook/{$log->id}", $payload)->assertSessionHasNoErrors();
    }

    public function test_rbac_menu_terproteksi_di_backend(): void
    {
        $unit = $this->buatUnit();
        $pegawai = User::factory()->create(['role' => 'pegawai']);
        $atasan = User::factory()->create(['role' => 'atasan']);
        $adminUnit = User::factory()->create(['role' => 'admin_unit', 'unit_kerja_id' => $unit]);
        $pusat = User::factory()->create(['role' => 'superadmin', 'unit_kerja_id' => $unit]);

        // Pegawai tidak boleh kelola pegawai & persetujuan
        $this->actingAs($pegawai)->get('/pegawai')->assertForbidden();
        $this->actingAs($pegawai)->get('/persetujuan')->assertForbidden();
        $this->actingAs($pegawai)->get('/pengaturan/audit')->assertForbidden();

        // Atasan boleh persetujuan tapi tidak kelola pegawai
        $this->actingAs($atasan)->get('/persetujuan')->assertOk();
        $this->actingAs($atasan)->get('/pegawai')->assertForbidden();

        // Admin unit boleh pegawai, tidak boleh unit kerja & audit
        $this->actingAs($adminUnit)->get('/pegawai')->assertOk();
        $this->actingAs($adminUnit)->get('/unit-kerja')->assertForbidden();
        $this->actingAs($adminUnit)->get('/pengaturan/audit')->assertForbidden();

        // Admin pusat boleh semuanya
        $this->actingAs($pusat)->get('/unit-kerja')->assertOk();
        $this->actingAs($pusat)->get('/pengaturan?tab=audit')->assertOk();
        $this->actingAs($pusat)->get('/pengaturan?tab=referensi')->assertOk();
        $this->actingAs($pusat)->get('/pengaturan?tab=smtp')->assertOk();
        // URL lama dialihkan ke tab terkait
        $this->actingAs($pusat)->get('/pengaturan/audit')->assertRedirect();
    }

    public function test_halaman_utama_semua_modul(): void
    {
        $pusat = User::factory()->create(['role' => 'superadmin', 'unit_kerja_id' => $this->buatUnit()]);

        foreach ([
            '/dashboard', '/logbook', '/logbook/buat', '/presensi', '/laporan',
            '/laporan/cetak', '/persetujuan', '/persetujuan/riwayat', '/pegawai',
            '/pegawai/create', '/unit-kerja', '/monitoring', '/pengaturan',
            '/pengaturan?tab=otp', '/pengaturan?tab=smtp', '/pengaturan?tab=periode', '/pengaturan?tab=referensi', '/pengaturan?tab=audit',
            '/manajemen-user', '/manajemen-user?katakunci=a&status=aktif',
            '/notifikasi', '/pencarian?q=laporan',
        ] as $url) {
            $this->actingAs($pusat)->get($url)->assertOk()->assertSessionHasNoErrors();
        }

        // Unduh PDF (dompdf) — pastikan berkas PDF asli berlogo
        $this->actingAs($pusat)->get('/laporan/pdf')->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        // Ekspor Excel
        $this->actingAs($pusat)->get('/laporan/export')->assertOk()
            ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_paginasi_10_item_per_halaman(): void
    {
        $unitId = $this->buatUnit();
        $pegawai = User::factory()->create(['role' => 'pegawai', 'unit_kerja_id' => $unitId]);
        $pusat = User::factory()->create(['role' => 'superadmin', 'unit_kerja_id' => $unitId]);

        // 12 kegiatan dalam satu bulan → halaman 1 berisi 10 item, ada halaman 2
        $output = OutputRef::create(['nama' => 'Dokumen']);
        foreach (range(1, 12) as $i) {
            LogKegiatan::create([
                'user_id' => $pegawai->id,
                'tanggal' => sprintf('2026-08-%02d', $i),
                'nama_kegiatan' => "Kegiatan uji paginasi {$i}",
                'output_id' => $output->id,
                'pengguna' => 'Internal',
                'jam_mulai' => '08:00',
                'jam_selesai' => '09:00',
                'status' => 'draft',
            ]);
        }

        $r1 = $this->actingAs($pegawai)->get('/logbook?bulan=8&tahun=2026&page=1')->assertOk();
        $r1->assertSee('Menampilkan');
        $r1->assertSee('1–10');
        $r1->assertSee('dari');
        $r1->assertSee('12');

        $this->actingAs($pegawai)->get('/logbook?bulan=8&tahun=2026&page=2')->assertOk()
            ->assertSee('Kegiatan uji paginasi 2</', false)
            ->assertDontSee('Kegiatan uji paginasi 12');

        // Monitoring memakai paginasi koleksi (p_page) tanpa error
        $this->actingAs($pusat)->get('/monitoring?p_page=1')->assertOk();
        $this->actingAs($pusat)->get('/monitoring?p_page=2')->assertOk();
    }

    public function test_captcha_baru_lebih_besar_dan_jelas(): void
    {
        $res = $this->get('/captcha');
        $res->assertOk()->assertHeader('Content-Type', 'image/png');
        // Dimensi baru 220x64 (tampilan ikon pada login mengikuti ukuran ini)
        $bin = (string) $res->getContent();
        $this->assertEquals(220, unpack('N', substr($bin, 16, 4))[1]);
        $this->assertEquals(64, unpack('N', substr($bin, 20, 4))[1]);
    }

    public function test_superadmin_menyimpan_smtp_dan_email_tes(): void
    {
        Mail::fake();
        $pusat = User::factory()->create(['role' => 'superadmin', 'email' => 'sdm@transmigrasi.go.id', 'unit_kerja_id' => $this->buatUnit()]);
        $pegawai = User::factory()->create(['role' => 'pegawai', 'email' => 'asn@transmigrasi.go.id']);

        // Pegawai biasa tidak boleh menyentuh pengaturan SMTP
        $this->actingAs($pegawai)->post('/pengaturan/smtp', ['smtp_host' => 'x'])->assertForbidden();

        // Superadmin menyimpan konfigurasi SMTP
        $this->actingAs($pusat)->post('/pengaturan/smtp', [
            'smtp_host' => 'smtp.gmail.com',
            'smtp_port' => 587,
            'smtp_enkripsi' => 'tls',
            'smtp_username' => 'sdm@transmigrasi.go.id',
            'smtp_password' => 'app-password',
            'mail_from_address' => 'transkinerja@transmigrasi.go.id',
            'mail_from_name' => 'TransKinerja',
            'notifikasi_email_aktif' => '1',
        ])->assertSessionHasNoErrors()->assertSessionHas('sukses');

        $this->assertDatabaseHas('pengaturans', ['kunci' => 'smtp_host', 'nilai' => 'smtp.gmail.com']);
        $this->assertDatabaseHas('pengaturans', ['kunci' => 'notifikasi_email_aktif', 'nilai' => '1']);

        // Tombol kirim email tes → email HTML terkirim ke email admin
        $this->actingAs($pusat)->post('/pengaturan/smtp/test')->assertSessionHas('sukses');
        Mail::assertSent(NotifikasiEmail::class, fn ($m) => $m->envelope()->subject === '[TransKinerja] Email Uji Coba Berhasil');
    }

    public function test_notifikasi_aplikasi_juga_terkirim_ke_email_html(): void
    {
        Mail::fake();
        $asn = User::factory()->create(['role' => 'pegawai', 'email' => 'asn@transmigrasi.go.id']);
        Pengaturan::set('notifikasi_email_aktif', '1');

        NotifikasiService::kirim($asn->id, 'Log Book Disetujui', 'Kegiatan Anda telah disetujui atasan.', '/laporan');

        $this->assertDatabaseHas('app_notifications', ['user_id' => $asn->id, 'judul' => 'Log Book Disetujui']);
        Mail::assertSent(NotifikasiEmail::class, 1);
        Mail::assertSent(NotifikasiEmail::class, function ($mail) use ($asn) {
            return $mail->hasTo($asn->email) && str_contains($mail->render(), 'Buka Aplikasi TransKinerja');
        });

        // Bila dinonaktifkan → hanya notifikasi dalam aplikasi
        Pengaturan::set('notifikasi_email_aktif', '0');
        Mail::fake();
        NotifikasiService::kirim($asn->id, 'Judul Lain', 'Pesan.', null);
        Mail::assertNothingSent();
        $this->assertDatabaseHas('app_notifications', ['judul' => 'Judul Lain']);
    }

    public function test_presensi_clock_in_dan_out(): void
    {
        $pegawai = User::factory()->create(['role' => 'pegawai']);

        $this->actingAs($pegawai)->get('/presensi')->assertOk();
        $this->actingAs($pegawai)->post('/presensi/clock-in')->assertSessionHasNoErrors();
        $this->assertDatabaseHas('presensis', ['user_id' => $pegawai->id]);

        // Clock-in kedua ditolak
        $this->actingAs($pegawai)->post('/presensi/clock-in')->assertSessionHas('gagal');

        $this->actingAs($pegawai)->post('/presensi/clock-out')->assertSessionHasNoErrors();
    }
}
