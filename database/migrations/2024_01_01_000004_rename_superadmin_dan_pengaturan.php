<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Peran tertinggi "admin_pusat" kini bernama "superadmin" (label & nilai).
        DB::table('users')->where('role', 'admin_pusat')->update(['role' => 'superadmin']);
        DB::table('users')->where('username', 'adminpusat')->update(['username' => 'superadmin']);

        // Penyimpanan kunci-nilai pengaturan aplikasi (SMTP, notifikasi email, dll).
        Schema::create('pengaturans', function (Blueprint $table) {
            $table->string('kunci', 60)->primary();
            $table->text('nilai')->nullable();
            $table->foreignId('diubah_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('diubah_pada')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pengaturans');
        DB::table('users')->where('role', 'superadmin')->update(['role' => 'admin_pusat']);
        DB::table('users')->where('username', 'superadmin')->update(['username' => 'adminpusat']);
    }
};
