<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('unit_kerja', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 30)->unique();
            $table->string('nama');
            $table->foreignId('parent_id')->nullable()->constrained('unit_kerja')->nullOnDelete();
            $table->boolean('is_aktif')->default(true);
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('nip', 30)->nullable()->unique()->after('name');
            $table->string('username', 60)->unique()->after('nip');
            $table->string('role', 20)->default('pegawai')->index()->after('username');
            $table->string('pangkat_golongan', 80)->nullable()->after('role');
            $table->string('jabatan', 150)->nullable()->after('pangkat_golongan');
            $table->foreignId('unit_kerja_id')->nullable()->constrained('unit_kerja')->nullOnDelete()->after('jabatan');
            $table->foreignId('atasan_id')->nullable()->constrained('users')->nullOnDelete()->after('unit_kerja_id');
            $table->boolean('is_aktif')->default(true)->after('atasan_id');
            $table->string('email')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['unit_kerja_id']);
            $table->dropForeign(['atasan_id']);
            $table->dropColumn([
                'nip', 'username', 'role', 'pangkat_golongan',
                'jabatan', 'unit_kerja_id', 'atasan_id', 'is_aktif',
            ]);
        });
        Schema::dropIfExists('unit_kerja');
    }
};
