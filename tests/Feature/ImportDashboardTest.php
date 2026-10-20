<?php

namespace Tests\Feature;

use App\Models\UnitKerja;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImportDashboardTest extends TestCase
{
    use RefreshDatabase;

    /** Uji import berkas Data Dashboard.xlsx asli instansi. */
    public function test_import_data_dashboard_asli(): void
    {
        UnitKerja::create(['kode' => 'PUSAT', 'nama' => 'Sekretariat Jenderal Kementerian Transmigrasi']);
        $admin = User::create([
            'name' => 'Super Admin', 'username' => 'superadmin', 'password' => 'password',
            'role' => 'superadmin', 'unit_kerja_id' => 1,
        ]);

        $berkas = '/Users/permatabank/Desktop/tysar/a Project/TRIJAYA SOLUTION/APLIKASI TRANSKINERJA KEMENTERIAN TRANSMIGRASI/Data Dashboard.xlsx';
        $unggah = new \Illuminate\Http\UploadedFile(
            $berkas, 'Data Dashboard.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true
        );

        $respon = $this->actingAs($admin)->post(route('pegawai.import'), ['berkas' => $unggah]);
        $respon->assertRedirect();

        $h = session('hasil_import');
        echo "\n=== HASIL IMPORT ===\n";
        echo "Tambah: {$h['tambah']} | Perbarui: {$h['perbarui']} | Gagal: {$h['total_gagal']}\n";
        foreach (array_slice($h['gagal'], 0, 5) as $g) {
            echo " - $g\n";
        }

        // Baris 2: Sekretaris Jenderal — kolom BATAS USIA PENSIUN terisi pada berkas (47453 → 2029-12-01)
        $contoh = User::where('nip', '196911161996031001')->first();
        $this->assertNotNull($contoh, 'Pegawai baris 2 tidak terimport');
        echo "\n=== BARIS 2 (Eselon I) ===\n";
        echo "status={$contoh->status_pegawai} | pangkat={$contoh->pangkat_golongan} | tmt_jabatan={$contoh->tmt_jabatan?->format('Y-m-d')}\n";
        echo "kenaikan_pangkat={$contoh->kenaikan_pangkat?->format('Y-m-d')} | bup={$contoh->batas_usia_pensiun?->format('Y-m-d')} (kolom berkas: 2029-12-01)\n";
        echo "level_pendidikan={$contoh->level_pendidikan} | jk={$contoh->jenis_kelamin} | lahir={$contoh->tanggal_lahir?->format('Y-m-d')}\n";
        echo "unit={$contoh->unitKerja?->nama}\n";
        $this->assertSame('PNS', $contoh->status_pegawai);
        $this->assertSame('Pembina Utama Madya (IV.d)', $contoh->pangkat_golongan);
        $this->assertSame('2029-12-01', $contoh->batas_usia_pensiun?->format('Y-m-d'));
        $this->assertSame('S3', $contoh->level_pendidikan);

        // Baris 6: Fungsional Madya — BUP dari berkas (49218), auto-hitung hanya bila kolom kosong
        $fung = User::where('nip', '197409291993111001')->first();
        echo "\n=== BARIS 6 (Fungsional Madya) === bup={$fung?->batas_usia_pensiun?->format('Y-m-d')}\n";
        $this->assertNotNull($fung?->batas_usia_pensiun);

        // Kolom baru tersedia (berkas belum memuat kolom kemampuan → harus null, tidak error)
        $this->assertNull($contoh->kemampuan_renang);
        $this->assertNull($contoh->kemampuan_inggris);

        // Import ulang → tidak ada lagi tambahan baru (semua diperbarui)
        $unggah2 = new \Illuminate\Http\UploadedFile(
            $berkas, 'Data Dashboard.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true
        );
        $this->actingAs($admin)->post(route('pegawai.import'), ['berkas' => $unggah2]);
        $h2 = session('hasil_import');
        echo "\n=== IMPORT ULANG === tambah: {$h2['tambah']} | perbarui: {$h2['perbarui']} | gagal: {$h2['total_gagal']}\n";
        $this->assertSame(0, $h2['tambah']);
    }

    /** Uji file buatan: kolom kemampuan baru, tanggal teks fleksibel, BUP kosong → dihitung otomatis. */
    public function test_import_kolom_baru_dan_bup_otomatis(): void
    {
        UnitKerja::create(['kode' => 'PUSAT', 'nama' => 'Sekretariat Jenderal Kementerian Transmigrasi']);
        $admin = User::create([
            'name' => 'Super Admin', 'username' => 'superadmin', 'password' => 'password',
            'role' => 'superadmin', 'unit_kerja_id' => 1,
        ]);

        $sheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $ws = $sheet->getActiveSheet();
        $ws->fromArray([
            ['NAMA', 'NIP', 'STATUS', 'Es. II', 'Level Eselon', 'Level Fungsional', 'TMT JABATAN', 'TANGGAL LAHIR', 'BATAS USIA PENSIUN', 'LEVEL PENDIDIKAN', 'JENIS KELAMIN', 'KEMAMPUAN RENANG', 'KEMAMPUAN BAHASA INGGRIS'],
            ['Budi Eselon II', '198001012005011001', 'PNS', 'Biro Hukum', 'II', '-', '01 April 2028', '1980-01-01', '', 'S2', 'L', 'mahir sekali', 'Intermediate'],
            ['Sari Fungsional Madya', '199002032020122001', 'PPPK', 'Biro Hukum', '-', 'Madya', '12/1/2029', '5 Mei 1990', null, 'S-1', 'perempuan', 'tidak bisa renang', 'pemula'],
            ['Row Invalid', '123', 'PNS', 'Biro Hukum', '', '', '', '', '', '', '', '', ''],
        ], null, 'A1');
        $tmp = storage_path('app/test-import-baru.xlsx');
        (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($sheet))->save($tmp);

        $unggah = new \Illuminate\Http\UploadedFile($tmp, 'test-import-baru.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
        $this->actingAs($admin)->post(route('pegawai.import'), ['berkas' => $unggah]);

        $budi = User::where('nip', '198001012005011001')->first();
        echo "\n[Budi] status={$budi->status_pegawai} | tmt_jabatan={$budi->tmt_jabatan?->format('Y-m-d')} (harap 2028-04-01) | bup={$budi->batas_usia_pensiun?->format('Y-m-d')} (harap 2040-01-01, Eselon II → 60 th)\n";
        echo "[Budi] renang={$budi->kemampuan_renang} | inggris={$budi->kemampuan_inggris} | jk={$budi->jenis_kelamin}\n";
        $this->assertSame('2028-04-01', $budi->tmt_jabatan?->format('Y-m-d'));
        $this->assertSame('2040-01-01', $budi->batas_usia_pensiun?->format('Y-m-d'));
        $this->assertSame('Mahir', $budi->kemampuan_renang);
        $this->assertSame('Menengah', $budi->kemampuan_inggris);
        $this->assertSame('Laki-laki', $budi->jenis_kelamin);

        $sari = User::where('nip', '199002032020122001')->first();
        echo "[Sari] tmt_jabatan={$sari->tmt_jabatan?->format('Y-m-d')} (harap 2029-01-12) | bup={$sari->batas_usia_pensiun?->format('Y-m-d')} (harap 2050-05-05, Fungs. Madya → 60 th) | pend={$sari->level_pendidikan} | renang={$sari->kemampuan_renang}\n";
        $this->assertSame('2029-01-12', $sari->tmt_jabatan?->format('Y-m-d'));
        $this->assertSame('2050-05-05', $sari->batas_usia_pensiun?->format('Y-m-d'));
        $this->assertSame('S1', $sari->level_pendidikan);
        $this->assertSame('Tidak Bisa', $sari->kemampuan_renang);
        $this->assertSame('Pemula', $sari->kemampuan_inggris);

        $h = session('hasil_import');
        echo "[Laporan] tambah={$h['tambah']} perbarui={$h['perbarui']} gagal={$h['total_gagal']} → ".implode('; ', $h['gagal'])."\n";
        $this->assertSame(2, $h['tambah']);
        $this->assertSame(1, $h['total_gagal']); // baris NIP '123' dilewati & dilaporkan

        unlink($tmp);
    }
}
