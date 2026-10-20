@extends('layouts.app')
@section('title', 'Antrean Persetujuan')

@section('content')
@php($user = auth()->user())
<div class="flex flex-wrap items-end justify-between gap-3">
    <div>
        <h1 class="page-title">Persetujuan</h1>
        <p class="page-sub">
            @if ($user->role === 'atasan') Antrean pemeriksaan laporan bawahan @else Laporan menunggu verifikasi {{ $user->role === 'admin_unit' ? 'unit kerja Anda' : 'seluruh unit' }} @endif
            — {{ $antrean->total() }} laporan
        </p>
    </div>
    <div class="flex flex-wrap items-center gap-2 w-full sm:w-auto justify-end">
        <x-period-filter :bulan="$bulan" :tahun="$tahun" :extra="['q' => $q]"/>
        <a href="{{ route('persetujuan.riwayat') }}" class="btn-secondary w-full sm:w-auto justify-center"><x-icon name="clock" class="w-4 h-4"/> Riwayat</a>
    </div>
</div>

<div class="card p-4">
    <form method="GET" class="flex flex-wrap gap-3 items-end" data-no-loader>
        <div class="flex-1 min-w-[220px]">
            <label class="label">Cari laporan</label>
            <div class="relative">
                <x-icon name="search" class="w-4 h-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400"/>
                <input name="q" value="{{ $q }}" placeholder="Nama kegiatan atau nama pegawai…" class="input !pl-10"/>
            </div>
        </div>
        <input type="hidden" name="bulan" value="{{ $bulan }}"/>
        <input type="hidden" name="tahun" value="{{ $tahun }}"/>
        <button class="btn-primary w-full sm:w-auto">Cari</button>
    </form>
</div>

<div class="card">
    @if ($antrean->isNotEmpty())
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                <tr>
                    <th>Pegawai</th>
                    <th>Tanggal</th>
                    <th>Nama Kegiatan</th>
                    <th>Output</th>
                    <th>Pengguna</th>
                    <th class="text-center">Jenis</th>
                    <th>Bukti</th>
                    <th class="text-right">Aksi</th>
                </tr>
                </thead>
                <tbody>
                @foreach ($antrean as $log)
                    <tr>
                        <td>
                            <p class="font-semibold text-slate-800 text-[13px]">{{ \Illuminate\Support\Str::before($log->user->name, ',') }}</p>
                            <p class="text-[11px] text-slate-400">{{ $log->user->unitKerja?->nama }}</p>
                        </td>
                        <td class="whitespace-nowrap font-semibold text-navy-800">{{ $log->tanggal->translatedFormat('d M Y') }}</td>
                        <td class="max-w-[280px]">{{ Str::limit($log->nama_kegiatan, 80) }}</td>
                        <td><span class="badge-draft">{{ $log->output->nama }}</span></td>
                        <td class="text-xs text-slate-500">{{ $log->pengguna }}</td>
                        <td class="text-center">
                            <div class="flex gap-1 justify-center">
                                @foreach ($log->taskTypes as $t)
                                    <span class="badge bg-navy-50 text-navy-700 border-navy-100">{{ $t->kode }}</span>
                                @endforeach
                            </div>
                        </td>
                        <td class="text-center text-xs">{{ $log->lampirans_count ?? $log->lampirans->count() ?: '—' }}</td>
                        <td><a href="{{ route('persetujuan.show', $log) }}" class="btn-primary btn-sm whitespace-nowrap">Periksa</a></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <div class="px-4 sm:px-5 py-4">{{ $antrean->links() }}</div>
    @else
        <x-empty icon="check-badge" teks="Antrean persetujuan bersih" sub="Tidak ada laporan berstatus Diajukan pada periode ini."/>
    @endif
</div>
@endsection
