@extends('layouts.app')
@section('title', 'Presensi & Jam Kerja')

@section('content')
<div class="flex flex-wrap items-end justify-between gap-3">
    <div>
        <h1 class="page-title">Presensi &amp; Jam Kerja</h1>
        <p class="page-sub">Catatan clock-in / clock-out harian dan rekap total jam kerja.</p>
    </div>
    <x-period-filter :bulan="$bulan" :tahun="$tahun"/>
</div>

<div class="grid md:grid-cols-3 gap-4">
    <div class="card p-6 md:col-span-1 flex flex-col items-center justify-center text-center bg-gradient-to-br from-navy-900 to-navy-800 !border-0">
        <p class="text-navy-200 text-xs font-semibold uppercase tracking-widest">{{ now()->translatedFormat('l') }}</p>
        <p class="text-4xl font-extrabold text-white mt-1">{{ now()->format('H:i') }}</p>
        <p class="text-navy-300 text-xs mt-1">{{ now()->translatedFormat('d F Y') }}</p>

        <div class="mt-6 w-full space-y-2.5">
            <div class="flex justify-between text-xs bg-white/10 rounded-xl px-4 py-2.5">
                <span class="text-navy-200">Clock-in</span>
                <span class="font-bold {{ $hariIni->clock_in ? 'text-emerald-300' : 'text-white/50' }}">{{ $hariIni->clock_in?->format('H:i:s') ?? 'belum' }}</span>
            </div>
            <div class="flex justify-between text-xs bg-white/10 rounded-xl px-4 py-2.5">
                <span class="text-navy-200">Clock-out</span>
                <span class="font-bold {{ $hariIni->clock_out ? 'text-emerald-300' : 'text-white/50' }}">{{ $hariIni->clock_out?->format('H:i:s') ?? 'belum' }}</span>
            </div>
        </div>

        <div class="mt-5 w-full space-y-2">
            @if (! $hariIni->clock_in)
                <form action="{{ route('presensi.clockIn') }}" method="POST" class="w-full">
                    @csrf
                    <button class="btn-success w-full"><x-icon name="clock" class="w-4 h-4"/> Clock In</button>
                </form>
            @elseif (! $hariIni->clock_out)
                <form action="{{ route('presensi.clockOut') }}" method="POST" class="w-full">
                    @csrf
                    <button class="btn-warning w-full"><x-icon name="clock" class="w-4 h-4"/> Clock Out</button>
                </form>
            @else
                <div class="rounded-xl bg-emerald-400/10 border border-emerald-300/30 text-emerald-300 text-xs font-semibold px-4 py-3 flex items-center justify-center gap-2">
                    <x-icon name="check" class="w-4 h-4"/> Presensi lengkap — {{ durasi_format($hariIni->durasiMenit()) }}
                </div>
            @endif
        </div>
    </div>

    <div class="card p-5 flex items-center justify-between">
        <div>
            <p class="stat-label">Hari Hadir Bulan Ini</p>
            <p class="stat-value">{{ $rekap->total() }}</p>
            <p class="text-xs text-slate-400 mt-0.5">dari catatan presensi</p>
        </div>
        <x-icon name="calendar" class="w-9 h-9 text-navy-200"/>
    </div>
    <div class="card p-5 flex items-center justify-between">
        <div>
            <p class="stat-label">Total Jam Kerja</p>
            <p class="stat-value">{{ durasi_format($totalMenit) }}</p>
            <p class="text-xs text-slate-400 mt-0.5">akumulasi clock-in → clock-out</p>
        </div>
        <x-icon name="clock" class="w-9 h-9 text-navy-200"/>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <p class="card-title">Riwayat Presensi — {{ \App\Models\PeriodeLaporan::namaBulan($bulan) }} {{ $tahun }}</p>
    </div>
    @if ($rekap->isNotEmpty())
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>Tanggal</th><th>Hari</th><th class="text-center">Clock In</th><th class="text-center">Clock Out</th><th class="text-center">Durasi</th><th>Status</th></tr></thead>
                <tbody>
                @foreach ($rekap as $p)
                    <tr>
                        <td class="font-semibold text-navy-800">{{ $p->tanggal->translatedFormat('d M Y') }}</td>
                        <td class="text-xs text-slate-500">{{ $p->tanggal->translatedFormat('l') }}</td>
                        <td class="text-center {{ $p->clock_in?->format('H:i') > '08:00' ? 'text-amber-600 font-semibold' : 'text-emerald-600 font-semibold' }}">{{ $p->clock_in?->format('H:i') ?? '—' }}</td>
                        <td class="text-center text-slate-600">{{ $p->clock_out?->format('H:i') ?? '—' }}</td>
                        <td class="text-center font-semibold text-navy-700">{{ durasi_format($p->durasiMenit()) }}</td>
                        <td>
                            @if ($p->clock_in && $p->clock_out)
                                <span class="badge-disetujui">Lengkap</span>
                            @elseif ($p->clock_in)
                                <span class="badge-diajukan">Sedang Bekerja</span>
                            @else
                                <span class="badge-draft">Tanpa Presensi</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <div class="px-4 sm:px-5 py-4">{{ $rekap->links() }}</div>
    @else
        <x-empty icon="clock" teks="Belum ada presensi bulan ini" sub="Gunakan tombol Clock In saat mulai bekerja."/>
    @endif
</div>
@endsection
