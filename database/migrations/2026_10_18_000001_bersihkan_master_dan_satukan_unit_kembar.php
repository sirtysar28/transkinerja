<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Bereskan data hasil import lama:
     *
     * 1) Normalisasi ULANG nilai master (jenis kelamin, status, pendidikan, renang,
     *    b. Inggris). Migrasi "rapikan_master_pegawai" sebelumnya berjalan SEBELUM
     *    Excel diimport, sehingga data hasil import ("pns", "Laki-Laki", "S-1", dst.)
     *    belum pernah dibersihkan — filter & form select jadi tak cocok.
     *
     * 2) Satukan unit kerja kembar yang tercipta karena beda kapitalisasi nama
     *    ("…Di Kawasan…" vs "…di Kawasan…"): pegawai dipindahkan ke unit dengan
     *    anggota terbanyak, unit kembar sisanya dinonaktifkan (riwayat tetap aman).
     */
    public function up(): void
    {
        // ---------- 1) Normalisasi nilai master (idempoten) ----------
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

        // ---------- 2) Satukan unit kerja kembar ----------
        $kunci = fn ($nama) => mb_strtolower(preg_replace('/\s+/u', ' ', trim((string) $nama)));

        $kelompok = [];
        foreach (DB::table('unit_kerja')->orderBy('id')->get() as $u) {
            $kelompok[$kunci($u->nama)][] = $u;
        }

        // Jumlah pegawai tiap unit (satu query, dipakai memilih unit "utama").
        $jumlah = DB::table('users')
            ->select('unit_kerja_id', DB::raw('COUNT(*) AS jml'))
            ->groupBy('unit_kerja_id')
            ->pluck('jml', 'unit_kerja_id');

        foreach ($kelompok as $grup) {
            if (count($grup) < 2) {
                continue;
            }

            // Pertahankan unit dengan pegawai terbanyak, aktif, id terkecil.
            usort($grup, fn ($a, $b) => [
                (int) ($jumlah[$b->id] ?? 0), (int) $b->is_aktif, -$b->id,
            ] <=> [(int) ($jumlah[$a->id] ?? 0), (int) $a->is_aktif, -$a->id]);

            $utama = array_shift($grup);
            DB::table('unit_kerja')->where('id', $utama->id)->update(['is_aktif' => true]);

            foreach ($grup as $kembar) {
                DB::table('users')->where('unit_kerja_id', $kembar->id)
                    ->update(['unit_kerja_id' => $utama->id]);
                DB::table('unit_kerja')->where('id', $kembar->id)->update(['is_aktif' => false]);
            }
        }
    }

    public function down(): void
    {
        // Pembersihan & penggabungan data — tidak dapat dibalik.
    }
};
