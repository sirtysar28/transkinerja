<?php

namespace App\Console\Commands;

use App\Services\DigitransService;
use Illuminate\Console\Command;

class DigitransCekCommand extends Command
{
    protected $signature = 'digitrans:cek';

    protected $description = 'Cek koneksi, skema tabel & validasi mapping integrasi DigiTrans';

    public function handle(): int
    {
        $this->table(['Pengaturan', 'Nilai'], [
            ['Integrasi aktif', config('digitrans.aktif') ? 'YA' : 'TIDAK'],
            ['Login via DigiTrans', config('digitrans.login_dari_digitrans') ? 'YA' : 'TIDAK (password lokal)'],
            ['Auto-provision akun', config('digitrans.auto_provision') ? 'YA' : 'TIDAK'],
            ['Sinkron data pegawai', config('digitrans.sinkron_data') ? 'YA' : 'TIDAK'],
            ['Database', (string) config('database.connections.digitrans.database')],
            ['Tabel', (string) config('digitrans.tabel')],
        ]);

        if (! DigitransService::aktif()) {
            $this->warn('Integrasi NONAKTIF — set DIGITRANS_AKTIF=true bila ingin dipakai.');

            return self::SUCCESS;
        }

        if (! DigitransService::tesKoneksi()) {
            $this->error('❌ Koneksi GAGAL — periksa DIGITRANS_DB_* di .env (cek log untuk detail).');

            return self::FAILURE;
        }

        $this->info('✅ Koneksi ke database DigiTrans BERHASIL.');

        // Tampilkan kolom asli + validasi mapping
        $kolom = DigitransService::kolomTersedia();
        if ($kolom !== []) {
            $this->newLine();
            $this->line('Kolom pada tabel <info>' . config('digitrans.tabel') . '</info>:');
            $this->line('  ' . implode(', ', $kolom));

            $this->newLine();
            $peta = [
                ['ID', 'id'], ['Username', 'username'], ['NIP', 'nip'],
                ['Nama', 'nama'], ['Email', 'email'], ['Password', 'password'],
                ['Jabatan', 'jabatan'], ['Unit kerja', 'unit'], ['Status aktif', 'aktif'],
            ];
            $baris = [];
            foreach ($peta as [$label, $kunci]) {
                $dipakai = DigitransService::pakaiKolom($kunci);
                $baris[] = [
                    $label,
                    $dipakai ?: '-',
                    $dipakai ? 'OK' : (config("digitrans.kolom.{$kunci}") ? 'KOLOM TIDAK DITEMUKAN → fitur dilewati' : 'tidak dipakai'),
                ];
            }
            $this->table(['Peran', 'Kolom dipakai', 'Status'], $baris);

            $this->newLine();
            $this->line('Bila ada mapping "TIDAK DITEMUKAN": buka phpMyAdmin → tabel DigiTrans,');
            $this->line('lalu set nama kolom aslinya di .env, contoh:');
            $this->line('  DIGITRANS_KOLOM_NAMA=full_name   (ganti sesuai kolom sebenarnya)');
        } else {
            $this->warn('Skema tabel tidak terbaca (information_schema tidak dapat diakses).');
            $this->warn('Integrasi tetap mencoba memakai mapping .env apa adanya.');
        }

        return self::SUCCESS;
    }
}
