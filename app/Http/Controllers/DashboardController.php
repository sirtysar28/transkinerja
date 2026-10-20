<?php

namespace App\Http\Controllers;

use App\Models\LogKegiatan;
use App\Models\PeriodeLaporan;
use App\Models\Presensi;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        [$bulan, $tahun] = $this->periodeAktif($request);

        $data = match ($user->role) {
            User::ROLE_PEGAWAI => $this->dashboardPegawai($user, $bulan, $tahun),
            User::ROLE_ATASAN => $this->dashboardAtasan($user, $bulan, $tahun),
            User::ROLE_ADMIN_UNIT => $this->dashboardAdminUnit($user, $bulan, $tahun),
            default => $this->dashboardAdminPusat($user, $bulan, $tahun),
        };

        return view('dashboard', array_merge($data, [
            'user' => $user,
            'bulan' => (int) $bulan,
            'tahun' => (int) $tahun,
            'namaBulan' => PeriodeLaporan::namaBulan((int) $bulan),
        ]));
    }

    private function periodeAktif(Request $request): array
    {
        $bulan = (int) $request->query('bulan', session('periode_bulan', now()->month));
        $tahun = (int) $request->query('tahun', session('periode_tahun', now()->year));
        session(['periode_bulan' => $bulan, 'periode_tahun' => $tahun]);

        return [$bulan, $tahun];
    }

    // ---------- Pegawai (Level 1) ----------
    private function dashboardPegawai(User $user, int $bulan, int $tahun): array
    {
        $awalBulan = now()->create($tahun, $bulan, 1)->startOfDay();
        $akhirBulan = $awalBulan->copy()->endOfMonth();

        $statusCounts = LogKegiatan::where('user_id', $user->id)
            ->whereBetween('tanggal', [$awalBulan, $akhirBulan])
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $kegiatanHariIni = LogKegiatan::with('output', 'taskTypes')
            ->where('user_id', $user->id)
            ->whereDate('tanggal', today())
            ->orderBy('jam_mulai')
            ->get();

        $durasiHariIni = $kegiatanHariIni->sum(fn ($k) => $k->durasiMenit() ?? 0);

        $presensi = Presensi::firstOrCreate(
            ['user_id' => $user->id, 'tanggal' => today()],
        );

        // Tren 14 hari terakhir
        $tren = LogKegiatan::where('user_id', $user->id)
            ->whereBetween('tanggal', [today()->subDays(13), today()])
            ->select('tanggal', DB::raw('count(*) as total'))
            ->groupBy('tanggal')
            ->pluck('total', 'tanggal');

        $hariKerja = $this->hariKerja($awalBulan, min($akhirBulan, today()));
        $hariTerisi = LogKegiatan::where('user_id', $user->id)
            ->whereBetween('tanggal', [$awalBulan, min($akhirBulan, today())])
            ->distinct('tanggal')
            ->count();

        return [
            'tipe' => 'pegawai',
            'statusCounts' => $statusCounts,
            'kegiatanHariIni' => $kegiatanHariIni,
            'durasiHariIni' => $durasiHariIni,
            'presensiHariIni' => $presensi,
            'tren' => $tren,
            'hariKerja' => $hariKerja,
            'hariTerisi' => $hariTerisi,
            'perluRevisi' => LogKegiatan::with('output')
                ->where('user_id', $user->id)->where('status', 'revisi')
                ->orderBy('tanggal')->get(),
        ];
    }

    // ---------- Atasan (Level 2) ----------
    private function dashboardAtasan(User $user, int $bulan, int $tahun): array
    {
        $awalBulan = now()->create($tahun, $bulan, 1)->startOfDay();
        $akhirBulan = $awalBulan->copy()->endOfMonth();

        $bawahan = User::where('atasan_id', $user->id)->where('is_aktif', true)->with('unitKerja')->get();
        $idsBawahan = $bawahan->pluck('id');

        $antrean = LogKegiatan::with(['user', 'output', 'taskTypes'])
            ->whereIn('user_id', $idsBawahan)
            ->where('status', 'diajukan')
            ->orderBy('tanggal')
            ->limit(8)
            ->get();

        $totalAntrean = LogKegiatan::whereIn('user_id', $idsBawahan)
            ->where('status', 'diajukan')->count();

        // status pelaporan tim
        $rekapTim = $bawahan->map(function ($b) use ($awalBulan, $akhirBulan) {
            $logs = LogKegiatan::where('user_id', $b->id)
                ->whereBetween('tanggal', [$awalBulan, $akhirBulan]);
            $total = (clone $logs)->count();
            $disetujui = (clone $logs)->where('status', 'disetujui')->count();
            $menunggu = (clone $logs)->where('status', 'diajukan')->count();
            $revisi = (clone $logs)->where('status', 'revisi')->count();
            $draft = (clone $logs)->where('status', 'draft')->count();

            return (object) [
                'pegawai' => $b,
                'total' => $total,
                'disetujui' => $disetujui,
                'menunggu' => $menunggu,
                'revisi' => $revisi,
                'draft' => $draft,
                'terisi' => (clone $logs)->distinct('tanggal')->count('tanggal'),
            ];
        });

        return [
            'tipe' => 'atasan',
            'bawahan' => $bawahan,
            'antrean' => $antrean,
            'totalAntrean' => $totalAntrean,
            'rekapTim' => $rekapTim,
            'totalKegiatanTim' => $rekapTim->sum(fn ($r) => $r->total),
            'aktivitasTerbaru' => LogKegiatan::with('user', 'output')
                ->whereIn('user_id', $idsBawahan->push($user->id))
                ->whereBetween('tanggal', [$awalBulan, $akhirBulan])
                ->latest('updated_at')->limit(6)->get(),
        ];
    }

    // ---------- Admin Unit (Level 3) ----------
    private function dashboardAdminUnit(User $user, int $bulan, int $tahun): array
    {
        $awalBulan = now()->create($tahun, $bulan, 1)->startOfDay();
        $akhirBulan = $awalBulan->copy()->endOfMonth();
        $unit = $user->unitKerja;

        $pegawai = User::where('unit_kerja_id', $user->unit_kerja_id)
            ->where('is_aktif', true)->with('unitKerja')->get();
        $ids = $pegawai->pluck('id');

        $logQuery = LogKegiatan::whereIn('user_id', $ids)->whereBetween('tanggal', [$awalBulan, $akhirBulan]);
        $totalKegiatan = (clone $logQuery)->count();
        $menunggu = (clone $logQuery)->where('status', 'diajukan')->count();
        $disetujui = (clone $logQuery)->where('status', 'disetujui')->count();
        $revisi = (clone $logQuery)->where('status', 'revisi')->count();

        $belumMengisi = $pegawai->filter(fn ($p) => ! LogKegiatan::where('user_id', $p->id)
            ->whereBetween('tanggal', [$awalBulan, min($akhirBulan, today())])->exists());

        // Tren kegiatan harian sepanjang bulan (untuk grafik dashboard)
        $trenHarian = LogKegiatan::whereIn('user_id', $ids)
            ->whereBetween('tanggal', [$awalBulan, $akhirBulan])
            ->select('tanggal', DB::raw('count(*) as total'))
            ->groupBy('tanggal')
            ->pluck('total', 'tanggal');

        return [
            'tipe' => 'admin_unit',
            'unit' => $unit,
            'pegawai' => $pegawai,
            'totalKegiatan' => $totalKegiatan,
            'menunggu' => $menunggu,
            'disetujui' => $disetujui,
            'revisi' => $revisi,
            'belumMengisi' => $belumMengisi,
            'trenHarian' => $trenHarian,
            'aktivitasTerbaru' => LogKegiatan::with('user', 'output')
                ->whereIn('user_id', $ids)->whereBetween('tanggal', [$awalBulan, $akhirBulan])
                ->latest('updated_at')->limit(6)->get(),
        ];
    }

    // ---------- Superadmin (Level 4) ----------
    private function dashboardAdminPusat(User $user, int $bulan, int $tahun): array
    {
        $awalBulan = now()->create($tahun, $bulan, 1)->startOfDay();
        $akhirBulan = $awalBulan->copy()->endOfMonth();

        $totalPegawai = User::where('is_aktif', true)->whereNot('role', User::ROLE_SUPERADMIN)->count();
        $totalKegiatan = LogKegiatan::whereBetween('tanggal', [$awalBulan, $akhirBulan])->count();
        $menunggu = LogKegiatan::where('status', 'diajukan')->whereBetween('tanggal', [$awalBulan, $akhirBulan])->count();
        $disetujui = LogKegiatan::where('status', 'disetujui')->whereBetween('tanggal', [$awalBulan, $akhirBulan])->count();

        $perUnit = \App\Models\UnitKerja::where('is_aktif', true)->withCount([
            'pegawai' => fn ($q) => $q->where('is_aktif', true),
        ])->get()->map(function ($unit) use ($awalBulan, $akhirBulan) {
            $unit->total_kegiatan = LogKegiatan::whereHas('user', fn ($q) => $q->where('unit_kerja_id', $unit->id))
                ->whereBetween('tanggal', [$awalBulan, $akhirBulan])->count();
            $unit->disetujui = LogKegiatan::whereHas('user', fn ($q) => $q->where('unit_kerja_id', $unit->id))
                ->whereBetween('tanggal', [$awalBulan, $akhirBulan])->where('status', 'disetujui')->count();

            return $unit;
        })->sortByDesc('total_kegiatan');

        $periodeAktif = PeriodeLaporan::where('is_aktif', true)->first();

        // Tren kegiatan harian global sepanjang bulan (untuk grafik dashboard)
        $trenHarian = LogKegiatan::whereBetween('tanggal', [$awalBulan, $akhirBulan])
            ->select('tanggal', DB::raw('count(*) as total'))
            ->groupBy('tanggal')
            ->pluck('total', 'tanggal');

        return [
            'tipe' => 'superadmin',
            'totalPegawai' => $totalPegawai,
            'totalKegiatan' => $totalKegiatan,
            'menunggu' => $menunggu,
            'disetujui' => $disetujui,
            'perUnit' => $perUnit,
            'periodeAktif' => $periodeAktif,
            'trenHarian' => $trenHarian,
            'revisiGlobal' => LogKegiatan::where('status', 'revisi')->whereBetween('tanggal', [$awalBulan, $akhirBulan])->count(),
            'aktivitasTerbaru' => LogKegiatan::with('user', 'output', 'user.unitKerja')
                ->whereBetween('tanggal', [$awalBulan, $akhirBulan])
                ->latest('updated_at')->limit(8)->get(),
        ];
    }

    private function hariKerja(CarbonInterface $mulai, CarbonInterface $selesai): int
    {
        $hari = 0;
        $cursor = $mulai->copy();
        while ($cursor->lte($selesai)) {
            if (! $cursor->isWeekend()) {
                $hari++;
            }
            $cursor->addDay();
        }

        return $hari;
    }
}
