<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Log Book: catatan aktivitas harian ASN
        Schema::create('log_kegiatans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('tanggal')->index();
            $table->string('nama_kegiatan');
            $table->foreignId('output_id')->constrained('output_refs')->restrictOnDelete();
            $table->string('pengguna', 150);
            $table->time('jam_mulai')->nullable();
            $table->time('jam_selesai')->nullable();
            // draft | diajukan | disetujui | revisi
            $table->string('status', 20)->default('draft')->index();
            $table->text('catatan_revisi')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'tanggal']);
        });

        // Pivot log kegiatan <-> jenis tugas (KS/KP/KP multi pilihan seperti formulir)
        Schema::create('log_task_type', function (Blueprint $table) {
            $table->foreignId('log_kegiatan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('task_type_id')->constrained()->cascadeOnDelete();
            $table->primary(['log_kegiatan_id', 'task_type_id']);
        });

        // Bukti dukung / lampiran
        Schema::create('log_lampirans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('log_kegiatan_id')->constrained()->cascadeOnDelete();
            $table->string('nama_asli');
            $table->string('path');
            $table->string('mime', 100)->nullable();
            $table->unsignedInteger('ukuran')->nullable();
            $table->timestamps();
        });

        // Riwayat alur persetujuan (ajukan, revisi, setujui, buka kembali)
        Schema::create('approval_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('log_kegiatan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('aktor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('aksi', 30);
            $table->text('catatan')->nullable();
            $table->timestamps();
        });

        // Presensi / jam kerja (clock-in & clock-out)
        Schema::create('presensis', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('tanggal');
            $table->timestamp('clock_in')->nullable();
            $table->timestamp('clock_out')->nullable();
            $table->unique(['user_id', 'tanggal']);
        });

        // Audit trail aplikasi
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('aktor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('aksi', 40);
            $table->string('modul', 60);
            $table->string('entitas', 60)->nullable();
            $table->unsignedBigInteger('entitas_id')->nullable();
            $table->text('deskripsi')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->nullable()->index();
        });

        // Notifikasi in-app
        Schema::create('app_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('judul', 150);
            $table->text('pesan');
            $table->string('tautan')->nullable();
            $table->timestamp('dibaca_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_notifications');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('presensis');
        Schema::dropIfExists('approval_logs');
        Schema::dropIfExists('log_lampirans');
        Schema::dropIfExists('log_task_type');
        Schema::dropIfExists('log_kegiatans');
    }
};
