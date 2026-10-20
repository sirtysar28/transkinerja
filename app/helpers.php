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
