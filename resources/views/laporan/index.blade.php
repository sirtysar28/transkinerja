@extends('layouts.app')
@section('title', 'Laporan Kinerja')

@section('content')
@php($user = auth()->user())
<div class="flex flex-wrap items-end justify-between gap-3">
    <div>
        <h1 class="page-title">Laporan Kinerja</h1>
        <p class="page-sub">Rekapitulasi harian &amp; bulanan sesuai cakupan kewenangan Anda.</p>
    </div>
    <div class="flex flex-wrap gap-2 w-full sm:w-auto">
        <a href="{{ route('laporan.pdf', array_filter(['pegawai' => $pegawaiTerpilih, 'bulan' => $bulan, 'tahun' => $tahun])) }}"
           class="btn-danger w-full sm:w-auto"><x-icon name="download" class="w-4 h-4"/> Unduh PDF</a>
        <a href="{{ route('laporan.export', array_filter(['pegawai' => $pegawaiTerpilih, 'bulan' => $bulan, 'tahun' => $tahun])) }}"
           class="btn-success w-full sm:w-auto"><x-icon name="excel" class="w-4 h-4"/> Unduh Excel</a>
        <a href="{{ route('laporan.cetak', array_filter(['pegawai' => $pegawaiTerpilih, 'bulan' => $bulan, 'tahun' => $tahun])) }}"
           target="_blank" class="btn-secondary w-full sm:w-auto"><x-icon name="print" class="w-4 h-4"/> Pratinjau Cetak</a>
    </div>
</div>

{{-- Filter --}}
<div class="card p-4">
    <form method="GET" class="flex flex-wrap gap-3 items-end" data-no-loader>
        @if ($user->role !== 'pegawai')
            <div class="flex-1 min-w-[150px] sm:flex-none sm:min-w-0">
                <label class="label">Pegawai</label>
                <select name="pegawai" data-autosubmit class="select !w-full sm:!w-auto">
                    <option value="">Semua pegawai (cakupan saya)</option>
                    @foreach ($pegawaiOptions as $p)
                        <option value="{{ $p->id }}" @selected($p->id == $pegawaiTerpilih)>{{ $p->name }} — {{ $p->unitKerja?->kode }}</option>
                    @endforeach
                </select>
            </div>
        @endif
        <div class="flex-1 min-w-[130px] sm:flex-none sm:min-w-0">
            <label class="label">Bulan</label>
            <select name="bulan" data-autosubmit class="select !w-full sm:!w-auto">
                @foreach (range(1, 12) as $b)
                    <option value="{{ $b }}" @selected($b == $bulan)>{{ \App\Models\PeriodeLaporan::namaBulan($b) }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex-1 min-w-[100px] sm:flex-none sm:min-w-0">
            <label class="label">Tahun</label>
            <select name="tahun" data-autosubmit class="select !w-full sm:!w-auto">
                @foreach (range(now()->year + 1, 2024, -1) as $t)
                    <option value="{{ $t }}" @selected($t == $tahun)>{{ $t }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex-1 min-w-[150px] sm:flex-none sm:min-w-0">
            <label class="label">Jenis Tugas</label>
            <select name="jenis" data-autosubmit class="select !w-full sm:!w-auto">
                <option value="">Semua</option>
                @foreach ($jenisList as $j)
                    <option value="{{ $j->id }}" @selected($j->id == ($filter['jenis'] ?? null))>{{ $j->kode }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex-1 min-w-[150px] sm:flex-none sm:min-w-0">
            <label class="label">Output</label>
            <select name="output" data-autosubmit class="select !w-full sm:!w-auto">
                <option value="">Semua</option>
                @foreach ($outputList as $o)
                    <option value="{{ $o->id }}" @selected($o->id == ($filter['output'] ?? null))>{{ $o->nama }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex-1 min-w-[130px] sm:flex-none sm:min-w-0">
            <label class="label">Status</label>
            <select name="status" data-autosubmit class="select !w-full sm:!w-auto">
                <option value="">Semua</option>
                @foreach (\App\Models\LogKegiatan::STATUS_LABEL as $sk => $sl)
                    <option value="{{ $sk }}" @selected($sk == ($filter['status'] ?? null))>{{ $sl }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex-1 min-w-[180px]">
            <label class="label">Cari</label>
            <input name="q" value="{{ $filter['q'] ?? '' }}" placeholder="Kegiatan / pengguna…" class="input"/>
        </div>
        <button class="btn-primary w-full sm:w-auto">Terapkan</button>
    </form>
</div>

{{-- Ringkasan statistik --}}
<div class="grid grid-cols-2 lg:grid-cols-5 gap-3 sm:gap-4">
    <div class="card p-4 sm:p-5">
        <p class="stat-label">Total Kegiatan</p>
        <p class="stat-value">{{ $logs->total() }}</p>
    </div>
    <div class="card p-4 sm:p-5">
        <p class="stat-label">Disetujui</p>
        <p class="stat-value text-emerald-600">{{ $statStatus->get('disetujui', 0) }}</p>
    </div>
    <div class="card p-4 sm:p-5">
        <p class="stat-label">Diajukan</p>
        <p class="stat-value text-amber-600">{{ $statStatus->get('diajukan', 0) }}</p>
    </div>
    <div class="card p-4 sm:p-5">
        <p class="stat-label">Perlu Revisi</p>
        <p class="stat-value text-rose-600">{{ $statStatus->get('revisi', 0) }}</p>
    </div>
    <div class="card p-4 sm:p-5">
        <p class="stat-label">Total Durasi Tercatat</p>
        <p class="stat-value !text-lg sm:!text-2xl">{{ durasi_format($totalDurasi) }}</p>
    </div>
</div>

<div class="grid lg:grid-cols-2 gap-6">
    {{-- Rekap jenis tugas --}}
    <div class="card">
        <div class="card-header"><p class="card-title">Rekap Jenis Tugas — {{ $namaBulan }} {{ $tahun }}</p></div>
        <div class="px-5 pb-5">
            @php($maksJenis = max(1, $rekapJenis->max()))
            <div class="space-y-3">
                @foreach ($rekapJenis as $kode => $jumlah)
                    <div>
                        <div class="flex justify-between text-xs mb-1.5">
                            <span class="font-bold text-navy-800">{{ $kode }}</span>
                            <span class="text-slate-400">{{ $jumlah }} kegiatan</span>
                        </div>
                        <div class="h-2.5 rounded-full bg-slate-100 overflow-hidden">
                            <div class="h-full rounded-full bg-gradient-to-r from-navy-700 to-navy-400" style="width: {{ (int) ($jumlah / $maksJenis * 100) }}%"></div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- Rekap output --}}
    <div class="card">
        <div class="card-header"><p class="card-title">Rekap Output Pekerjaan</p></div>
        <div class="px-5 pb-5">
            @php($maksOut = max(1, $rekapOutput->max()))
            <div class="space-y-3">
                @foreach ($rekapOutput->filter(fn ($v) => $v > 0) as $nama => $jumlah)
                    <div>
                        <div class="flex justify-between text-xs mb-1.5">
                            <span class="font-bold text-navy-800">{{ $nama }}</span>
                            <span class="text-slate-400">{{ $jumlah }}</span>
                        </div>
                        <div class="h-2.5 rounded-full bg-slate-100 overflow-hidden">
                            <div class="h-full rounded-full bg-gradient-to-r from-gold-500 to-gold-400" style="width: {{ (int) ($jumlah / $maksOut * 100) }}%"></div>
                        </div>
                    </div>
                @endforeach
                @if ($rekapOutput->filter(fn ($v) => $v > 0)->isEmpty())
                    <p class="text-xs text-slate-400">Belum ada data.</p>
                @endif
            </div>
        </div>
    </div>
</div>

{{-- Tabel rinci --}}
<div class="card">
    <div class="card-header">
        <p class="card-title">Daftar Kegiatan {{ $pegawaiTerpilih ? '— '.($pegawaiOptions->firstWhere('id', $pegawaiTerpilih)?->name ?? '') : '' }}</p>
        <span class="text-xs text-slate-400">{{ $namaBulan }} {{ $tahun }}</span>
    </div>
    @if ($logs->isNotEmpty())
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th class="w-10">No</th><th>Tanggal</th><th>Pegawai</th><th>Nama Kegiatan</th><th>Output</th><th>Pengguna</th><th class="text-center">Jenis</th><th>Status</th></tr></thead>
                <tbody>
                @foreach ($logs as $i => $log)
                    <tr>
                        <td class="text-slate-400 text-xs">{{ $logs->firstItem() + $i }}</td>
                        <td class="whitespace-nowrap">{{ $log->tanggal->translatedFormat('d/m') }}</td>
                        <td class="text-xs font-semibold text-slate-700">{{ \Illuminate\Support\Str::before($log->user->name, ',') }}</td>
                        <td class="max-w-[280px]">{{ Str::limit($log->nama_kegiatan, 70) }}</td>
                        <td><span class="badge-draft">{{ $log->output->nama }}</span></td>
                        <td class="text-xs text-slate-500">{{ $log->pengguna }}</td>
                        <td class="text-center">{{ $log->taskTypes->pluck('kode')->implode(' · ') ?: '—' }}</td>
                        <td><x-status :status="$log->status"/></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <div class="px-4 sm:px-5 py-4">{{ $logs->links() }}</div>
    @else
        <x-empty icon="report" teks="Tidak ada data pada filter ini"/>
    @endif
</div>
@endsection
