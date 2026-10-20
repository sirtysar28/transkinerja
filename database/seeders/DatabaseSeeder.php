<?php

namespace Database\Seeders;

use App\Models\AppNotification;
use App\Models\ApprovalLog;
use App\Models\LogKegiatan;
use App\Models\LogLampiran;
use App\Models\OutputRef;
use App\Models\PeriodeLaporan;
use App\Models\Presensi;
use App\Models\TaskType;
use App\Models\UnitKerja;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // ---------- Referensi ----------
        $taskTypes = [
            ['kode' => 'KS', 'nama' => 'KS — Kegiatan Sasaran', 'keterangan' => 'Definisi resmi menunggu konfirmasi Biro SDM.'],
            ['kode' => 'KP', 'nama' => 'KP — Kegiatan Penugasan', 'keterangan' => 'Definisi resmi menunggu konfirmasi Biro SDM.'],
            ['kode' => 'KT', 'nama' => 'KT — Kegiatan Tambahan', 'keterangan' => 'Definisi resmi menunggu konfirmasi Biro SDM.'],
        ];
        foreach ($taskTypes as $t) {
            TaskType::updateOrCreate(['kode' => $t['kode']], $t + ['is_aktif' => true]);
        }

        $outputs = ['Dokumen', 'Dokumentasi', 'Media', 'Laporan', 'Data', 'Materi'];
        foreach ($outputs as $o) {
            OutputRef::updateOrCreate(['nama' => $o], ['is_aktif' => true]);
        }

        // ---------- Unit kerja ----------
        $setjen = UnitKerja::updateOrCreate(['kode' => 'SETJEN'], [
            'nama' => 'Sekretariat Jenderal — Biro Organisasi dan SDM (Biro OSDM)',
            'is_aktif' => true,
        ]);
        $bppmt = UnitKerja::updateOrCreate(['kode' => 'BPPMT-BJM'], [
            'nama' => 'BPPMT Banjarmasin',
            'parent_id' => $setjen->id,
            'is_aktif' => true,
        ]);
        $pusdatin = UnitKerja::updateOrCreate(['kode' => 'PDN'], [
            'nama' => 'Pusat Data dan Teknologi Informasi (Pusdatin)',
            'is_aktif' => true,
        ]);
        $ditjenT = UnitKerja::updateOrCreate(['kode' => 'DJ-TRANS'], [
            'nama' => 'Direktorat Jenderal Transmigrasi',
            'is_aktif' => true,
        ]);

        // ---------- Pengguna ----------
        $adminPusat = User::updateOrCreate(['username' => 'superadmin'], [
            'name' => 'Dr. Ridwan Hakim, M.Si',
            'nip' => '197501012005011001',
            'email' => 'superadmin@transmigrasi.go.id',
            'password' => 'password',
            'role' => User::ROLE_SUPERADMIN,
            'pangkat_golongan' => 'Pembina Tingkat I / IVb',
            'jabatan' => 'Kepala Biro Organisasi dan SDM',
            'unit_kerja_id' => $setjen->id,
            'is_aktif' => true,
        ]);

        $adminUnit = User::updateOrCreate(['username' => 'adminbppmt'], [
            'name' => 'Dra. Siti Rahmawati, M.M.',
            'nip' => '198003152006042005',
            'email' => 'admin.bppmt@transmigrasi.go.id',
            'password' => 'password',
            'role' => User::ROLE_ADMIN_UNIT,
            'pangkat_golongan' => 'Penata Tingkat I / IIId',
            'jabatan' => 'Administratur BPPMT Banjarmasin',
            'unit_kerja_id' => $bppmt->id,
            'atasan_id' => $adminPusat->id,
            'is_aktif' => true,
        ]);

        $atasan = User::updateOrCreate(['username' => 'atasan'], [
            'name' => 'Budi Santoso, S.T., M.T.',
            'nip' => '198504102010011003',
            'email' => 'atasan@transmigrasi.go.id',
            'password' => 'password',
            'role' => User::ROLE_ATASAN,
            'pangkat_golongan' => 'Penata Tingkat I / IIId',
            'jabatan' => 'PSM Ahli Madya',
            'unit_kerja_id' => $bppmt->id,
            'atasan_id' => $adminUnit->id,
            'is_aktif' => true,
        ]);

        // Pegawai contoh sesuai lampiran Lapkin Agustus 2026
        $anggit = User::updateOrCreate(['username' => 'anggit'], [
            'name' => 'Anggit Tinarbuka AW, S.K.M, S.E',
            'nip' => '199010202018031001',
            'email' => 'anggit@transmigrasi.go.id',
            'password' => 'password',
            'role' => User::ROLE_PEGAWAI,
            'pangkat_golongan' => 'Penata / IIIc',
            'jabatan' => 'PSM Ahli Muda',
            'unit_kerja_id' => $bppmt->id,
            'atasan_id' => $atasan->id,
            'is_aktif' => true,
        ]);

        $pegawai2 = User::updateOrCreate(['username' => 'dewi'], [
            'name' => 'Dewi Lestari, S.E.',
            'nip' => '199203152022012004',
            'email' => 'dewi@transmigrasi.go.id',
            'password' => 'password',
            'role' => User::ROLE_PEGAWAI,
            'pangkat_golongan' => 'Penata Muda / IIIa',
            'jabatan' => 'Analis Kebijakan',
            'unit_kerja_id' => $bppmt->id,
            'atasan_id' => $atasan->id,
            'is_aktif' => true,
        ]);

        $pegawai3 = User::updateOrCreate(['username' => 'fajar'], [
            'name' => 'Fajar Nugroho, S.Kom.',
            'nip' => '199407222023011005',
            'email' => 'fajar@transmigrasi.go.id',
            'password' => 'password',
            'role' => User::ROLE_PEGAWAI,
            'pangkat_golongan' => 'Penata Muda / IIIa',
            'jabatan' => 'Pranata Komputer Ahli Pertama',
            'unit_kerja_id' => $pusdatin->id,
            'atasan_id' => $adminPusat->id,
            'is_aktif' => true,
        ]);

        // ---------- Periode pelaporan ----------
        PeriodeLaporan::updateOrCreate(['bulan' => 8, 'tahun' => 2026], [
            'tanggal_buka' => '2026-08-01', 'tanggal_tutup' => '2026-09-10', 'is_aktif' => false,
        ]);
        PeriodeLaporan::updateOrCreate(['bulan' => 9, 'tahun' => 2026], [
            'tanggal_buka' => '2026-09-01', 'tanggal_tutup' => '2026-10-10', 'is_aktif' => false,
        ]);
        PeriodeLaporan::updateOrCreate(['bulan' => 10, 'tahun' => 2026], [
            'tanggal_buka' => '2026-10-01', 'tanggal_tutup' => '2026-11-10', 'is_aktif' => true,
        ]);

        // ---------- Log Book contoh (dari file Lapkin Agustus 2026) ----------
        $outDok = OutputRef::where('nama', 'Dokumen')->first();
        $outDokumentasi = OutputRef::where('nama', 'Dokumentasi')->first();
        $outMedia = OutputRef::where('nama', 'Media')->first();
        $ks = TaskType::where('kode', 'KS')->first();
        $kp = TaskType::where('kode', 'KP')->first();
        $kt = TaskType::where('kode', 'KT')->first();

        $sampleAgustus = [
            // [tanggal, kegiatan, output, pengguna, jenis[], status]
            ['2026-08-03', 'Menyusun ND penyampaian kolaborasi HUT RI Ke-81', $outDok, 'Biro OSDM', [$kp], 'disetujui'],
            ['2026-08-03', 'Mengikuti rapat pembahasan Pemerintah Digital', $outDokumentasi, 'Pusdatin', [$kp], 'disetujui'],
            ['2026-08-04', 'Mengikuti ujian CACT BKN', $outDokumentasi, 'Biro OSDM', [$ks, $kp], 'disetujui'],
            ['2026-08-04', 'Melengkapi bukti dukung penilaian Pemerintah Digital', $outDok, 'Pusdatin', [$kp], 'disetujui'],
            ['2026-08-05', 'Menyusun revisi SK Kolaborasi Perayaan HUT RI', $outDok, 'Biro OSDM', [$kp], 'disetujui'],
            ['2026-08-06', 'Mengurus surat undangan rapat zoom kolaborasi perayaan HUT RI', $outDok, 'Biro OSDM', [$kp], 'disetujui'],
            ['2026-08-07', 'Mengikuti rapat zoom kolaborasi perayaan HUT RI', $outDokumentasi, 'Kementerian Transmigrasi', [$kp], 'disetujui'],
            ['2026-08-10', 'Mengikuti senam bersama Kick Off Perayaan HUT RI', $outDokumentasi, 'Kementerian Transmigrasi', [$kp], 'disetujui'],
            ['2026-08-11', 'Membuat revisi desain flyer', $outMedia, 'Kementerian Transmigrasi', [$kp], 'disetujui'],
            ['2026-08-12', 'Menyusun laporan Zoom Kick Off Pemagangan Nasional', $outDok, 'Biro OSDM', [$kp], 'disetujui'],
            ['2026-08-17', 'Mengikuti Upacara Kemerdekaan RI', $outDokumentasi, 'Kementerian Transmigrasi', [$kt], 'disetujui'],
            ['2026-08-18', 'Melaksanakan update postingan media sosial', $outMedia, 'BPPMT Banjarmasin', [$kp], 'disetujui'],
            ['2026-08-25', 'Melaksanakan koordinasi pembuatan aplikasi dasbor kepegawaian', $outDokumentasi, 'Biro OSDM', [$kp], 'disetujui'],
        ];

        foreach ($sampleAgustus as $i => [$tgl, $kegiatan, $output, $pengguna, $jenis, $status]) {
            $log = LogKegiatan::updateOrCreate([
                'user_id' => $anggit->id,
                'tanggal' => $tgl,
                'nama_kegiatan' => $kegiatan,
            ], [
                'output_id' => $output->id,
                'pengguna' => $pengguna,
                'jam_mulai' => '08:00',
                'jam_selesai' => '16:00',
                'status' => $status,
            ]);
            $log->taskTypes()->sync(collect($jenis)->pluck('id'));
            if ($status === 'disetujui') {
                ApprovalLog::firstOrCreate([
                    'log_kegiatan_id' => $log->id,
                    'aksi' => ApprovalLog::AKSI_AJUKAN,
                ], ['aktor_id' => $anggit->id]);
                ApprovalLog::firstOrCreate([
                    'log_kegiatan_id' => $log->id,
                    'aksi' => ApprovalLog::AKSI_SETUJU,
                ], ['aktor_id' => $atasan->id, 'catatan' => 'Sesuai.']);
            }
        }

        // Contoh bulan berjalan: draft, diajukan, dan perlu revisi
        $contohBulanIni = [
            [now()->startOfMonth()->addDays(2)->toDateString(), 'Menyusun ND persiapan koordinasi pemagangan nasional', $outDok, 'Biro OSDM', [$kp], 'disetujui'],
            [now()->startOfMonth()->addDays(4)->toDateString(), 'Mengikuti zoom rapat pembahasan Pemerintah Digital', $outDokumentasi, 'Pusdatin', [$kp], 'diajukan'],
            [now()->startOfMonth()->addDays(6)->toDateString(), 'Melaksanakan update postingan media sosial', $outMedia, 'BPPMT Banjarmasin', [$kp], 'revisi'],
            [now()->startOfMonth()->addDays(8)->toDateString(), 'Menyusun bahan tayang konsolidasi program pemagangan nasional', $outDok, 'Biro OSDM', [$kp], 'draft'],
            [now()->startOfMonth()->addDays(9)->toDateString(), 'Mengikuti zoom simulasi CAT BKN', $outDokumentasi, 'Biro OSDM', [$ks], 'diajukan'],
        ];
        foreach ($contohBulanIni as [$tgl, $kegiatan, $output, $pengguna, $jenis, $status]) {
            if (strtotime($tgl) > now()->getTimestamp()) {
                continue; // jangan buat data tanggal masa depan
            }
            $log = LogKegiatan::updateOrCreate([
                'user_id' => $anggit->id,
                'tanggal' => $tgl,
                'nama_kegiatan' => $kegiatan,
            ], [
                'output_id' => $output->id,
                'pengguna' => $pengguna,
                'jam_mulai' => '08:00',
                'jam_selesai' => '16:00',
                'status' => $status,
                'catatan_revisi' => $status === 'revisi' ? 'Mohon lengkapi bukti dukung berupa tautan/tangkapan layar postingan.' : null,
            ]);
            $log->taskTypes()->sync(collect($jenis)->pluck('id'));

            if (in_array($status, ['diajukan', 'disetujui', 'revisi'], true)) {
                ApprovalLog::firstOrCreate([
                    'log_kegiatan_id' => $log->id,
                    'aksi' => ApprovalLog::AKSI_AJUKAN,
                ], ['aktor_id' => $anggit->id]);
            }
            if ($status === 'disetujui') {
                ApprovalLog::firstOrCreate([
                    'log_kegiatan_id' => $log->id,
                    'aksi' => ApprovalLog::AKSI_SETUJU,
                ], ['aktor_id' => $atasan->id]);
            }
            if ($status === 'revisi') {
                ApprovalLog::firstOrCreate([
                    'log_kegiatan_id' => $log->id,
                    'aksi' => ApprovalLog::AKSI_REVISI,
                ], ['aktor_id' => $atasan->id, 'catatan' => $log->catatan_revisi]);
            }
        }

        // Contoh log milik pegawai lain
        $logDewi = LogKegiatan::updateOrCreate([
            'user_id' => $pegawai2->id,
            'tanggal' => now()->startOfMonth()->addDays(3)->toDateString(),
            'nama_kegiatan' => 'Menyusun laporan keuangan kegiatan HUT RI',
        ], [
            'output_id' => $outDok->id,
            'pengguna' => 'Biro OSDM',
            'jam_mulai' => '08:00',
            'jam_selesai' => '15:30',
            'status' => 'diajukan',
        ]);
        $logDewi->taskTypes()->sync([$kp->id]);

        $logFajar = LogKegiatan::updateOrCreate([
            'user_id' => $pegawai3->id,
            'tanggal' => now()->startOfMonth()->addDays(5)->toDateString(),
            'nama_kegiatan' => 'Pengembangan modul dashboard kinerja',
        ], [
            'output_id' => $outMedia->id,
            'pengguna' => 'Pusdatin',
            'jam_mulai' => '09:00',
            'jam_selesai' => '16:00',
            'status' => 'disetujui',
        ]);
        $logFajar->taskTypes()->sync([$ks->id]);

        // ---------- Presensi contoh (10 hari terakhir) ----------
        foreach ([$anggit, $pegawai2, $pegawai3] as $p) {
            for ($i = 9; $i >= 0; $i--) {
                $tgl = today()->subDays($i);
                if ($tgl->isWeekend()) {
                    continue;
                }
                Presensi::updateOrCreate(['user_id' => $p->id, 'tanggal' => $tgl->toDateString()], [
                    'clock_in' => $tgl->copy()->setTime(7, 45, rand(0, 55))->format('Y-m-d H:i:s'),
                    'clock_out' => $tgl->copy()->setTime(16, rand(0, 45), rand(0, 55))->format('Y-m-d H:i:s'),
                ]);
            }
        }

        // ---------- Notifikasi contoh ----------
        AppNotification::firstOrCreate([
            'user_id' => $anggit->id,
            'judul' => 'Perlu Revisi',
        ], [
            'pesan' => 'Kegiatan "Melaksanakan update postingan media sosial" dikembalikan dengan catatan: lengkapi bukti dukung.',
            'tautan' => route('logbook.index'),
        ]);

        $this->command->info('Seeder selesai. Login contoh: superadmin / atasan / adminbppmt / anggit — password: password');
    }
}
