<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Modul OTP login (verifikasi 2 langkah via email) + penunjang manajemen akses login:
     * - otp_codes  : kode OTP disimpan sebagai HASH (bukan kode asli), sekali pakai, berbatas percobaan.
     * - last_login : catatan login terakhir tiap user, tampil pada tab Pengaturan → Akses Login.
     */
    public function up(): void
    {
        Schema::create('otp_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('kode', 255); // hash SHA-256 dari kode asli
            $table->timestamp('kedaluwarsa_at');
            $table->unsignedTinyInteger('percobaan')->default(0);
            $table->timestamp('dipakai_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'dipakai_at']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('last_login_at')->nullable()->after('is_aktif');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('otp_codes');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('last_login_at');
        });
    }
};
