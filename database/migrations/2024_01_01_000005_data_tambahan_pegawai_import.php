<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kolom data kepegawaian tambahan hasil import Excel format Dashboard
     * OSDMRB/Digitrans (PNS/PPPK, identitas, pendidikan, dsb).
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('status_pegawai', 20)->nullable()->after('pangkat_golongan');   // PNS / PPPK
            $table->string('jenis_kelamin', 15)->nullable()->after('status_pegawai');
            $table->string('tempat_lahir', 60)->nullable()->after('jenis_kelamin');
            $table->date('tanggal_lahir')->nullable()->after('tempat_lahir');
            $table->string('agama', 20)->nullable()->after('tanggal_lahir');
            $table->string('alamat', 190)->nullable()->after('agama');
            $table->string('no_telp', 30)->nullable()->after('alamat');
            $table->string('pendidikan_terakhir', 190)->nullable()->after('no_telp');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'status_pegawai', 'jenis_kelamin', 'tempat_lahir', 'tanggal_lahir',
                'agama', 'alamat', 'no_telp', 'pendidikan_terakhir',
            ]);
        });
    }
};
