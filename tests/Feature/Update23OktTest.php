<?php

namespace Tests\Feature;

use App\Models\OutputRef;
use App\Models\TaskType;
use App\Models\UnitKerja;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Update23OktTest extends TestCase
{
    use RefreshDatabase;

    private function pegawai(): User
    {
        $unit = UnitKerja::firstOrCreate(['kode' => 'TEST'], ['nama' => 'Unit Testing']);

        return User::factory()->create(['role' => 'pegawai', 'unit_kerja_id' => $unit]);
    }

    public function test_filter_rentang_tanggal_logbook(): void
    {
        $user = $this->pegawai();
        $jenis = TaskType::firstOrCreate(['kode' => 'KS'], ['nama' => 'Kegiatan Strategis']);
        $output = OutputRef::firstOrCreate(['nama' => 'Dokumen']);

        foreach (['2026-08-03', '2026-08-04', '2026-09-10'] as $tgl) {
            $user->logKegiatans()->create([
                'tanggal' => $tgl, 'nama_kegiatan' => 'Kegiatan '.$tgl,
                'output_id' => $output->id, 'pengguna' => 'Biro', 'status' => 'draft',
            ])->taskTypes()->attach($jenis->id);
        }

        // rentang Agustus saja
        $r = $this->actingAs($user)->get('/logbook?dari=2026-08-01&sampai=2026-08-31');
        $r->assertOk()->assertSee('Kegiatan 2026-08-03')->assertSee('Kegiatan 2026-08-04');
        $this->assertFalse(false !== strpos($r->getContent(), 'Kegiatan 2026-09-10'));

        // hanya "dari" — s.d. akhir bulan terpilih (September)
        $r2 = $this->actingAs($user)->get('/logbook?dari=2026-08-04&bulan=9&tahun=2026');
        $r2->assertOk()->assertSee('Kegiatan 2026-09-10');
        $this->assertFalse(false !== strpos($r2->getContent(), 'Kegiatan 2026-08-03'));
    }

    public function test_kategori_pekerjaan_hanya_satu(): void
    {
        $user = $this->pegawai();
        $ks = TaskType::firstOrCreate(['kode' => 'KS'], ['nama' => 'Kegiatan Strategis']);
        $kp = TaskType::firstOrCreate(['kode' => 'KP'], ['nama' => 'Kegiatan Pokok']);
        $output = OutputRef::firstOrCreate(['nama' => 'Dokumen']);

        // dua kategori -> ditolak
        $this->actingAs($user)->post('/logbook', [
            'tanggal' => today()->toDateString(),
            'nama_kegiatan' => 'Uji dua kategori',
            'output_id' => $output->id,
            'pengguna' => 'Biro',
            'jenis_tugas' => [$ks->id, $kp->id],
        ])->assertSessionHasErrors('jenis_tugas');

        // satu kategori -> tersimpan
        $this->actingAs($user)->post('/logbook', [
            'tanggal' => today()->toDateString(),
            'nama_kegiatan' => 'Uji satu kategori',
            'output_id' => $output->id,
            'pengguna' => 'Biro',
            'jenis_tugas' => [$ks->id],
        ])->assertRedirect(route('logbook.index', ['bulan' => today()->month, 'tahun' => today()->year]));
        $this->assertDatabaseHas('log_kegiatans', ['nama_kegiatan' => 'Uji satu kategori']);
    }

    public function test_pimpinan_lihat_laporan_semua_pegawai(): void
    {
        $unit = UnitKerja::firstOrCreate(['kode' => 'TEST'], ['nama' => 'Unit Testing']);
        $lain = UnitKerja::firstOrCreate(['kode' => 'LAIN'], ['nama' => 'Unit Lain']);
        $pegawai = User::factory()->create(['role' => 'pegawai', 'unit_kerja_id' => $lain]);
        $pimpinan = User::factory()->create(['role' => 'pimpinan', 'unit_kerja_id' => $unit]);

        // pilih pegawai di unit lain — pimpinan tetap boleh
        $this->actingAs($pimpinan)->get('/laporan?pegawai='.$pegawai->id)->assertOk();
        $this->actingAs($pimpinan)->get('/monitoring')->assertOk();

        // pimpinan tidak masuk menu persetujuan
        $this->actingAs($pimpinan)->get('/persetujuan')->assertForbidden();
    }
}
