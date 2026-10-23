<?php

namespace App\Console\Commands;

use App\Services\AuditService;
use App\Services\DigitransService;
use Illuminate\Console\Command;

class DigitransSyncCommand extends Command
{
    protected $signature = 'digitrans:sync';

    protected $description = 'Sinkronkan data pegawai dari database DigiTrans (Biro OSDMRB) ke TransKinerja';

    public function handle(): int
    {
        if (! DigitransService::aktif()) {
            $this->error('Integrasi DigiTrans belum aktif. Set DIGITRANS_AKTIF=true beserta DIGITRANS_DB_* di .env');

            return self::FAILURE;
        }

        if (! DigitransService::tesKoneksi()) {
            $this->error('Koneksi ke database DigiTrans gagal. Periksa DIGITRANS_DB_* di .env');

            return self::FAILURE;
        }

        $this->info('Terhubung ke database DigiTrans. Menarik data pegawai…');
        $stat = DigitransService::sinkronPegawai();

        $this->table(['Metrik', 'Jumlah'], [
            ['Baris diproses', $stat['diproses']],
            ['Akun baru dibuat', $stat['dibuat']],
            ['Akun lama diperbarui/ditautkan', $stat['diperbarui']],
            ['Dilewati (tanpa identitas)', $stat['dilewati']],
            ['Gagal (cek log)', $stat['gagal']],
        ]);

        if ($stat['gagal'] > 0) {
            $this->warn("{$stat['gagal']} baris gagal — detail di storage/logs/laravel.log (umum: duplikat NIP/email/username).");
        }

        AuditService::catat('digitrans-sync', 'Integrasi', sprintf(
            'Sinkronisasi DigiTrans: %d diproses, %d dibuat, %d diperbarui, %d gagal.',
            $stat['diproses'], $stat['dibuat'], $stat['diperbarui'], $stat['gagal']
        ));

        $this->info('Selesai. Catatan: role & atasan dikelola TransKinerja; NIP, data');
        $this->info('kepegawaian, dan status aktif akun otomatis mengikuti DigiTrans.');

        return self::SUCCESS;
    }
}
