<?php

if (! function_exists('durasi_format')) {
    /** Format menit menjadi "X j Y mnt". */
    function durasi_format(?int $menit): string
    {
        if ($menit === null) {
            return '-';
        }
        $j = intdiv((int) $menit, 60);
        $m = ((int) $menit) % 60;

        return $j > 0 ? "{$j} j {$m} mnt" : "{$m} mnt";
    }
}

if (! function_exists('nip_format')) {
    /** Format NIP 18 digit menjadi "XXXXXXXX XXXXXX X XXX" (format baku SIPD). */
    function nip_format(?string $nip): string
    {
        $nip = preg_replace('/\D+/', '', (string) $nip);
        if (strlen($nip) !== 18) {
            return (string) $nip ?: '-';
        }

        return substr($nip, 0, 8).' '.substr($nip, 8, 6).' '.substr($nip, 14, 1).' '.substr($nip, 15, 3);
    }
}

if (! function_exists('ukuran_format')) {
    function ukuran_format(?int $bytes): string
    {
        if (! $bytes) {
            return '-';
        }
        $unit = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        $val = $bytes;
        while ($val >= 1024 && $i < count($unit) - 1) {
            $val /= 1024;
            $i++;
        }

        return round($val, 1).' '.$unit[$i];
    }
}
