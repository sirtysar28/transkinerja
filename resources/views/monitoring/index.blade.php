@extends('layouts.app')
@section('title', 'Monitoring & Analitik')

@section('content')
<div class="flex flex-wrap items-end justify-between gap-3">
    <div>
        <h1 class="page-title">Monitoring &amp; Analitik</h1>
        <p class="page-sub">Perbandingan aktivitas pegawai, kinerja antar unit, dan kelengkapan pelaporan — {{ $namaBulan }} {{ $tahun }}</p>
    </div>
    <x-period-filter :bulan="$bulan" :tahun="$tahun"/>
</div>

<div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
    <div class="card p-4 sm:p-5">
        <p class="stat-label">Pegawai Dipantau</p>
        <p class="stat-value">{{ $rekapPegawai->count() }}</p>
    </div>
    <div class="card p-4 sm:p-5">
        <p class="stat-label">Total Kegiatan</p>
        <p class="stat-value">{{ $rekapPegawai->sum(fn ($r) => $r->total) }}</p>
    </div>
    <div class="card p-4 sm:p-5">
        <p class="stat-label">Hari Kerja Berjalan</p>
        <p class="stat-value">{{ $hariKerja }} <span class="text-sm font-medium text-slate-400">hari</span></p>
    </div>
    <div class="card p-4 sm:p-5">
        <p class="stat-label">Pelaporan Belum Lengkap</p>
        <p class="stat-value {{ $belumLengkap->count() ? 'text-rose-600' : 'text-emerald-600' }}">{{ $belumLengkap->count() }}</p>
        <p class="text-xs text-slate-400 mt-0.5">pegawai dengan hari kosong</p>
    </div>
</div>

{{-- Rekap per pegawai --}}
<div class="card">
    <div class="card-header">
        <p class="card-title">Rekap Kinerja per Pegawai</p>
    </div>
    @if ($rekapPegawai->isNotEmpty())
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                <tr>
                    <th>Pegawai</th>
                    <th>Unit</th>
                    <th class="text-center">Kegiatan</th>
                    <th class="text-center">Durasi</th>
                    <th class="text-center">Draft</th>
                    <th class="text-center">Diajukan</th>
                    <th class="text-center">Revisi</th>
                    <th class="text-center">Disetujui</th>
                    <th class="w-40">Kelengkapan</th>
                </tr>
                </thead>
                <tbody>
                @foreach ($rekapPegawai as $r)
                    @php($persen = $hariKerja > 0 ? (int) round($r->hariTerisi / $hariKerja * 100) : 0)
                    <tr>
                        <td>
                            <p class="font-semibold text-slate-800 text-[13px]">{{ \Illuminate\Support\Str::before($r->p->name, ',') }}</p>
                            <p class="text-[11px] text-slate-400">{{ $r->p->jabatan }}</p>
                        </td>
                        <td class="text-xs text-slate-500">{{ $r->p->unitKerja?->kode }}</td>
                        <td class="text-center font-bold text-navy-700">{{ $r->total }}</td>
                        <td class="text-center text-xs text-slate-500">{{ durasi_format($r->durasi) }}</td>
                        <td class="text-center text-slate-400">{{ $r->draft }}</td>
                        <td class="text-center {{ $r->menunggu ? 'text-amber-600 font-bold' : 'text-slate-300' }}">{{ $r->menunggu }}</td>
                        <td class="text-center {{ $r->revisi ? 'text-rose-600 font-bold' : 'text-slate-300' }}">{{ $r->revisi }}</td>
                        <td class="text-center {{ $r->disetujui ? 'text-emerald-600 font-bold' : 'text-slate-300' }}">{{ $r->disetujui }}</td>
                        <td>
                            <div class="flex items-center gap-2">
                                <div class="flex-1 h-2 rounded-full bg-slate-100 overflow-hidden">
                                    <div class="h-full rounded-full {{ $persen >= 80 ? 'bg-emerald-500' : ($persen >= 50 ? 'bg-amber-400' : 'bg-rose-400') }}" style="width: {{ min(100, $persen) }}%"></div>
                                </div>
                                <span class="text-[10px] font-bold text-slate-500 w-8">{{ $persen }}%</span>
                            </div>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        @if ($rekapPegawai->hasPages())
            <div class="px-4 sm:px-5 py-4 border-t border-slate-100">{{ $rekapPegawai->links() }}</div>
        @endif
    @else
        <x-empty icon="users" teks="Belum ada pegawai dalam cakupan"/>
    @endif
</div>

<div class="grid lg:grid-cols-2 gap-6 items-start">
    {{-- Perbandingan antar unit --}}
    @if ($perUnit->isNotEmpty())
        <div class="card">
            <div class="card-header"><p class="card-title">Perbandingan Kinerja Antar Unit</p></div>
            @php($maks = max(1, $perUnit->max('total')))
            <div class="px-4 sm:px-5 pb-5 space-y-4">
                @foreach ($perUnit as $unit)
                    <div>
                        <div class="flex justify-between text-xs mb-1.5">
                            <span class="font-semibold text-slate-700">{{ $unit->nama }} <span class="text-slate-400">({{ $unit->jumlahPegawai }} pegawai)</span></span>
                            <span class="text-slate-400">{{ $unit->total }} kegiatan</span>
                        </div>
                        <div class="h-3 rounded-full bg-slate-100 overflow-hidden">
                            <div class="h-full rounded-full bg-gradient-to-r from-navy-800 via-navy-600 to-navy-400" style="width: {{ (int) ($unit->total / $maks * 100) }}%"></div>
                        </div>
                        <p class="text-[10px] text-slate-400 mt-1">{{ $unit->disetujui }} disetujui • {{ $unit->total - $unit->disetujui }} dalam proses</p>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Belum lengkap + presensi --}}
    <div class="space-y-6">
        <div class="card">
            <div class="card-header">
                <p class="card-title">⚠ Laporan Belum Lengkap</p>
                <span class="text-[11px] text-slate-400">hari kerja tanpa catatan log</span>
            </div>
            @if ($belumLengkap->isNotEmpty())
                <div class="px-4 sm:px-5 pb-5 space-y-2">
                    @foreach ($belumLengkap->take(6) as $r)
                        <div class="flex items-center gap-3 rounded-xl border {{ $r->hariKosong > 5 ? 'border-rose-100 bg-rose-50/50' : 'border-amber-100 bg-amber-50/40' }} px-4 py-2.5">
                            <span class="w-8 h-8 rounded-full bg-white flex items-center justify-center text-xs font-bold {{ $r->hariKosong > 5 ? 'text-rose-600' : 'text-amber-600' }}">{{ $r->hariKosong }}</span>
                            <div class="min-w-0 flex-1">
                                <p class="text-[13px] font-semibold text-slate-700 truncate">{{ $r->p->name }}</p>
                                <p class="text-[11px] text-slate-400">{{ $r->hariTerisi }}/{{ $hariKerja }} hari terisi</p>
                            </div>
                        </div>
                    @endforeach
                    @if ($belumLengkap->count() > 6)
                        <p class="text-[11px] text-slate-400 text-center pt-1">+{{ $belumLengkap->count() - 6 }} pegawai lainnya…</p>
                    @endif
                </div>
            @else
                <x-empty icon="check-badge" teks="Semua pegawai lengkap" sub="Tidak ada hari kerja kosong pada periode ini."/>
            @endif
        </div>

        <div class="card">
            <div class="card-header"><p class="card-title">Rekap Jam Kerja (Presensi)</p></div>
            @if ($rekapPresensi->isNotEmpty())
                <div class="px-4 sm:px-5 pb-5 space-y-2.5">
                    @foreach ($rekapPresensi->sortByDesc('total_menit') as $rp)
                        <div class="flex items-center justify-between gap-3 text-sm">
                            <span class="font-medium text-slate-700 truncate">{{ \Illuminate\Support\Str::before($rp->user->name ?? '-', ',') }}</span>
                            <span class="text-xs text-slate-400 whitespace-nowrap">{{ $rp->hari }} hari • <b class="text-navy-700">{{ durasi_format((int) $rp->total_menit) }}</b></span>
                        </div>
                    @endforeach
                </div>
            @else
                <x-empty icon="clock" teks="Belum ada data presensi"/>
            @endif
        </div>
    </div>
</div>
@endsection
