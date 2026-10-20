<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kolom kepegawaian lanjutan (format Data Dashboard / Digitrans):
     * TMT jabatan, kenaikan pangkat, batas usia pensiun (BUP), level pendidikan,
     * serta kemampuan renang & kemampuan bahasa Inggris.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('level_pendidikan', 10)->nullable()->after('pendidikan_terakhir'); // SD s.d. S3
            $table->date('tmt_jabatan')->nullable()->after('jabatan');
            $table->date('kenaikan_pangkat')->nullable()->after('pangkat_golongan');
            $table->date('batas_usia_pensiun')->nullable()->after('tanggal_lahir');
            $table->string('kemampuan_renang', 20)->nullable()->after('no_telp');
            $table->string('kemampuan_inggris', 20)->nullable()->after('kemampuan_renang');
        });

        // Rapikan varian ejaan jenis kelamin hasil import Excel (Laki-Laki → Laki-laki)
        DB::table('users')->whereNotNull('jenis_kelamin')->update(['jenis_kelamin' => DB::raw("CASE WHEN LOWER(jenis_kelamin) LIKE 'l%' THEN 'Laki-laki' WHEN LOWER(jenis_kelamin) LIKE 'p%' THEN 'Perempuan' ELSE jenis_kelamin END")]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'level_pendidikan', 'tmt_jabatan', 'kenaikan_pangkat',
                'batas_usia_pensiun', 'kemampuan_renang', 'kemampuan_inggris',
            ]);
        });
    }
};
