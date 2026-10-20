<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Referensi jenis tugas (KS, KP, KT)
        Schema::create('task_types', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 10)->unique();
            $table->string('nama', 100);
            $table->string('keterangan', 255)->nullable();
            $table->boolean('is_aktif')->default(true);
            $table->timestamps();
        });

        // Referensi output pekerjaan (Dokumen, Dokumentasi, Media, ...)
        Schema::create('output_refs', function (Blueprint $table) {
            $table->id();
            $table->string('nama', 100)->unique();
            $table->boolean('is_aktif')->default(true);
            $table->timestamps();
        });

        // Periode pelaporan bulanan
        Schema::create('periode_laporans', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('bulan');
            $table->unsignedSmallInteger('tahun');
            $table->date('tanggal_buka');
            $table->date('tanggal_tutup');
            $table->boolean('is_aktif')->default(false);
            $table->timestamps();
            $table->unique(['bulan', 'tahun']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('periode_laporans');
        Schema::dropIfExists('output_refs');
        Schema::dropIfExists('task_types');
    }
};
