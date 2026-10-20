<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Sinkronisasi pegawai & login dari DigiTrans (master tunggal) — otomatis tiap 15 menit.
// Di server cPanel pastikan cron Laravel scheduler aktif:
//   * * * * * /usr/local/bin/php /home/USER/transkinerja/artisan schedule:run >> /dev/null 2>&1
Schedule::command('digitrans:sync')
    ->everyFifteenMinutes()
    ->when(fn () => (bool) config('digitrans.aktif'))
    ->withoutOverlapping();
