<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Update 23 Oktober 2026 — kategori pekerjaan (KS/KP/KT) hanya boleh satu per kegiatan.
 * Data lama yang memiliki lebih dari satu kategori dirapikan: dipertahankan satu
 * kategori dengan urutan kode paling awal (KS > KP > KT).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('log_task_type')) {
            return;
        }

        $ganda = DB::table('log_task_type')
            ->select('log_kegiatan_id', DB::raw('count(*) as total'))
            ->groupBy('log_kegiatan_id')
            ->having('total', '>', 1)
            ->pluck('log_kegiatan_id');

        foreach ($ganda as $logId) {
            $pertahankan = DB::table('log_task_type')
                ->join('task_types', 'task_types.id', '=', 'log_task_type.task_type_id')
                ->where('log_kegiatan_id', $logId)
                ->orderBy('task_types.kode')
                ->orderBy('task_types.id')
                ->value('log_task_type.task_type_id');

            if ($pertahankan) {
                DB::table('log_task_type')
                    ->where('log_kegiatan_id', $logId)
                    ->where('task_type_id', '!=', $pertahankan)
                    ->delete();
            }
        }
    }

    public function down(): void
    {
        // Pemangkasan kategori tidak dapat dibalik.
    }
};
