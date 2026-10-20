<?php

namespace App\Http\Controllers;

use App\Models\Presensi;
use App\Services\AuditService;
use Illuminate\Http\Request;

class PresensiController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $bulan = (int) $request->query('bulan', now()->month);
        $tahun = (int) $request->query('tahun', now()->year);

        $hariIni = Presensi::firstOrCreate(
            ['user_id' => $user->id, 'tanggal' => today()],
        );

        $rekap = Presensi::where('user_id', $user->id)
            ->whereYear('tanggal', $tahun)
            ->whereMonth('tanggal', $bulan)
            ->orderByDesc('tanggal')
            ->paginate(10)->withQueryString()
            ->withQueryString();

        $totalMenit = Presensi::where('user_id', $user->id)
            ->whereYear('tanggal', $tahun)
            ->whereMonth('tanggal', $bulan)
            ->whereNotNull('clock_out')
            ->get()
            ->sum(fn ($p) => $p->durasiMenit());

        return view('presensi.index', [
            'hariIni' => $hariIni,
            'rekap' => $rekap,
            'bulan' => $bulan,
            'tahun' => $tahun,
            'totalMenit' => $totalMenit,
        ]);
    }

    public function clockIn(Request $request)
    {
        $presensi = Presensi::firstOrCreate(
            ['user_id' => $request->user()->id, 'tanggal' => today()],
        );

        if ($presensi->clock_in) {
            return back()->with('gagal', 'Anda sudah melakukan clock-in hari ini pada '.$presensi->clock_in->format('H:i').'.');
        }

        $presensi->update(['clock_in' => now()]);
        AuditService::catat('clock_in', 'Presensi', 'Clock-in pada '.now()->format('H:i').'.', Presensi::class, $presensi->id);

        return back()->with('sukses', 'Clock-in tercatat pukul '.now()->format('H:i:s').'.');
    }

    public function clockOut(Request $request)
    {
        $presensi = Presensi::where('user_id', $request->user()->id)
            ->whereDate('tanggal', today())->first();

        if (! $presensi || ! $presensi->clock_in) {
            return back()->with('gagal', 'Lakukan clock-in terlebih dahulu.');
        }
        if ($presensi->clock_out) {
            return back()->with('gagal', 'Anda sudah melakukan clock-out hari ini pada '.$presensi->clock_out->format('H:i').'.');
        }

        $presensi->update(['clock_out' => now()]);
        AuditService::catat('clock_out', 'Presensi', 'Clock-out pada '.now()->format('H:i').'.', Presensi::class, $presensi->id);

        $durasi = $presensi->durasiMenit();
        $jam = intdiv($durasi, 60);
        $menit = $durasi % 60;

        return back()->with('sukses', "Clock-out tercatat pukul ".now()->format('H:i:s').". Total jam kerja: {$jam} jam {$menit} menit.");
    }
}
