<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Rapikan nilai master kepegawaian hasil import versi lama yang tidak seragam
     * (mis. jenis kelamin: "Laki-Laki" / "L" / "P" / "LAKI-LAKI" → "Laki-laki";
     * status: "pns" → "PNS"; pendidikan: "S-1" → "S1"; kemampuan: "mahir sekali" → "Mahir").
     * Normalisasi memakai helper di App\Models\User agar konsisten dengan import & filter.
     */
    public function up(): void
    {
        $kolom = [
            'jenis_kelamin' => fn ($v) => \App\Models\User::normJenisKelamin($v),
            'status_pegawai' => fn ($v) => \App\Models\User::normStatusPegawai($v),
            'level_pendidikan' => fn ($v) => \App\Models\User::normLevelPendidikan($v),
            'kemampuan_renang' => fn ($v) => \App\Models\User::normKemampuan($v),
            'kemampuan_inggris' => fn ($v) => \App\Models\User::normKemampuan($v),
        ];

        DB::table('users')->orderBy('id')->chunkById(500, function ($baris) use ($kolom) {
            foreach ($baris as $p) {
                $ubah = [];
                foreach ($kolom as $nama => $norm) {
                    $baru = $norm($p->{$nama});
                    if ($baru !== $p->{$nama}) {
                        $ubah[$nama] = $baru;
                    }
                }
                if ($ubah !== []) {
                    DB::table('users')->where('id', $p->id)->update($ubah);
                }
            }
        });
    }

    public function down(): void
    {
        // Pembersihan data — tidak dapat dibalik.
    }
};
