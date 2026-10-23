<?php

namespace App\Http\Controllers;

use App\Models\LogKegiatan;
use App\Models\PeriodeLaporan;
use App\Models\Presensi;
use App\Models\UnitKerja;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class MonitoringController extends Controller
{
    /** Monitoring & analitik: perbandingan aktivitas pegawai & antar unit. */
    public function index(Request $request)
    {
        $user = $request->user();
        $bulan = (int) $request->query('bulan', now()->month);
        $tahun = (int) $request->query('tahun', now()->year);
        $awal = now()->create($tahun, $bulan, 1)->startOfDay();
        $akhir = $awal->copy()->endOfMonth();
        $batas = min($akhir, today());

        $pegawai = $user->cakupanPegawai()->where('is_aktif', true)
            ->with('unitKerja')->orderBy('name')->get();

        // Hari kerja efektif sampai hari ini (untuk % kelengkapan)
        $hariKerja = $this->hitungHariKerja($awal, $batas);

        $rekapPegawai = $pegawai->map(function ($p) use ($awal, $akhir, $batas) {
            $q = LogKegiatan::where('user_id', $p->id)->whereBetween('tanggal', [$awal, $akhir]);
            $total = (clone $q)->count();
            $disetujui = (clone $q)->where('status', 'disetujui')->count();
            $menunggu = (clone $q)->where('status', 'diajukan')->count();
            $revisi = (clone $q)->where('status', 'revisi')->count();
            $draft = (clone $q)->where('status', 'draft')->count();
            $hariTerisi = (clone $q)->where('tanggal', '<=', $batas)->distinct('tanggal')->count('tanggal');
            $durasi = (clone $q)->get()->sum(fn ($l) => $l->durasiMenit() ?? 0);

            return (object) compact('p', 'total', 'disetujui', 'menunggu', 'revisi', 'draft', 'hariTerisi', 'durasi');
        });

        // Perbandingan antar unit (pimpinan, admin unit & superadmin)
        $perUnit = collect();
        if (in_array($user->role, [User::ROLE_ADMIN_UNIT, User::ROLE_PIMPINAN, User::ROLE_SUPERADMIN], true)) {
            $unitQuery = UnitKerja::where('is_aktif', true);
            if ($user->role === User::ROLE_ADMIN_UNIT) {
                $unitQuery->where('id', $user->unit_kerja_id);
            }
            $perUnit = $unitQuery->get()->map(function ($unit) use ($awal, $akhir) {
                $q = LogKegiatan::whereHas('user', fn ($u) => $u->where('unit_kerja_id', $unit->id))
                    ->whereBetween('tanggal', [$awal, $akhir]);
                $unit->total = (clone $q)->count();
                $unit->disetujui = (clone $q)->where('status', 'disetujui')->count();
                $unit->jumlahPegawai = User::where('unit_kerja_id', $unit->id)->where('is_aktif', true)->count();

                return $unit;
            })->sortByDesc('total');
        }

        // Pegawai dengan pelaporan belum lengkap (hari kerja tanpa log)
        $belumLengkap = $rekapPegawai->filter(fn ($r) => $r->hariTerisi < $hariKerja)
            ->map(function ($r) use ($hariKerja) {
                $r->hariKosong = $hariKerja - $r->hariTerisi;

                return $r;
            })->sortByDesc('hariKosong')->values();

        // Rekap presensi (jam kerja) bulan ini pada cakupan user
        $idsPegawai = $pegawai->pluck('id');
        $presensiRows = Presensi::with('user:id,name')
            ->whereIn('user_id', $idsPegawai)
            ->whereBetween('tanggal', [$awal, $akhir])
            ->whereNotNull('clock_out')
            ->get();
        $rekapPresensi = $presensiRows->groupBy('user_id')->map(function ($rows, $userId) {
            return (object) [
                'user' => $rows->first()->user,
                'hari' => $rows->count(),
                'total_menit' => $rows->sum(fn ($r) => $r->durasiMenit()),
            ];
        })->sortByDesc('total_menit')->take(10)->values();

        // Tabel rekap pegawai dibatasi 10 baris per halaman (paginasi koleksi)
        $halaman = max(1, (int) $request->query('p_page', 1));
        $rekapPegawaiPage = new LengthAwarePaginator(
            $rekapPegawai->forPage($halaman, 10)->values(),
            $rekapPegawai->count(),
            10,
            $halaman,
            ['path' => $request->url(), 'query' => $request->query(), 'pageName' => 'p_page']
        );

        return view('monitoring.index', [
            'rekapPegawai' => $rekapPegawaiPage,
            'perUnit' => $perUnit,
            'belumLengkap' => $belumLengkap,
            'rekapPresensi' => $rekapPresensi,
            'hariKerja' => $hariKerja,
            'bulan' => $bulan,
            'tahun' => $tahun,
            'namaBulan' => PeriodeLaporan::namaBulan($bulan),
        ]);
    }

    private function hitungHariKerja($mulai, $selesai): int
    {
        $n = 0;
        for ($d = $mulai->copy(); $d->lte($selesai); $d->addDay()) {
            if (! $d->isWeekend()) {
                $n++;
            }
        }

        return $n;
    }
}
