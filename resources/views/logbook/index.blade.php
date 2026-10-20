@extends('layouts.app')
@section('title', 'Log Book')

@section('content')
<div class="flex flex-wrap items-end justify-between gap-3">
    <div>
        <h1 class="page-title">Log Book</h1>
        <p class="page-sub">Pencatatan aktivitas harian — {{ $logs->total() }} kegiatan pada {{ \App\Models\PeriodeLaporan::namaBulan($bulan) }} {{ $tahun }}</p>
    </div>
    <div class="flex flex-wrap items-center gap-2 w-full sm:w-auto justify-end">
        <x-period-filter :bulan="$bulan" :tahun="$tahun" :extra="['status' => $status, 'q' => $q]"/>
        <a href="{{ route('logbook.create') }}" class="btn-primary w-full sm:w-auto justify-center"><x-icon name="plus" class="w-4 h-4"/> Tambah Kegiatan</a>
    </div>
</div>

{{-- Filter bar --}}
<div class="card p-4">
    <form method="GET" class="flex flex-wrap gap-3 items-end" data-no-loader>
        <div class="flex-1 min-w-[220px]">
            <label class="label">Cari kegiatan</label>
            <div class="relative">
                <x-icon name="search" class="w-4 h-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400"/>
                <input name="q" value="{{ $q }}" placeholder="Nama kegiatan atau pengguna…" class="input !pl-10"/>
            </div>
        </div>
        <div>
            <label class="label">Status</label>
            <select name="status" class="select !w-auto">
                <option value="">Semua status</option>
                @foreach ($statusOptions as $key => $label)
                    <option value="{{ $key }}" @selected($key === $status)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <input type="hidden" name="bulan" value="{{ $bulan }}"/>
        <input type="hidden" name="tahun" value="{{ $tahun }}"/>
        <button class="btn-primary w-full sm:w-auto">Terapkan</button>
        <a href="{{ route('logbook.index') }}" class="btn-ghost w-full sm:w-auto justify-center">Reset</a>
    </form>
</div>

{{-- Daftar kegiatan --}}
<div class="card">
    @if ($logs->hasPages())
        <div class="card-header !pb-0">
            <p class="card-title">Daftar Kegiatan</p>
        </div>
    @endif
    @if ($logs->isNotEmpty())
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                <tr>
                    <th class="w-10">#</th>
                    <th>Tanggal</th>
                    <th>Nama Kegiatan</th>
                    <th>Output</th>
                    <th>Pengguna</th>
                    <th class="text-center">Jenis</th>
                    <th class="text-center">Durasi</th>
                    <th>Bukti</th>
                    <th>Status</th>
                    <th class="text-right">Aksi</th>
                </tr>
                </thead>
                <tbody>
                @foreach ($logs as $i => $log)
                    <tr>
                        <td class="text-slate-400 text-xs">{{ $logs->firstItem() + $i }}</td>
                        <td class="whitespace-nowrap font-semibold text-navy-800">{{ $log->tanggal->translatedFormat('d M Y') }}</td>
                        <td class="max-w-[260px]">
                            <a href="{{ route('logbook.show', $log) }}" class="font-medium text-slate-800 hover:text-navy-700">{{ Str::limit($log->nama_kegiatan, 75) }}</a>
                            @if ($log->status === 'revisi' && $log->catatan_revisi)
                                <p class="text-[11px] text-rose-500 mt-0.5">↩ {{ Str::limit($log->catatan_revisi, 60) }}</p>
                            @endif
                        </td>
                        <td><span class="badge-draft">{{ $log->output->nama }}</span></td>
                        <td class="text-xs text-slate-500">{{ $log->pengguna }}</td>
                        <td class="text-center">
                            <div class="flex gap-1 justify-center">
                                @foreach ($log->taskTypes as $t)
                                    <span class="badge bg-navy-50 text-navy-700 border-navy-100" title="{{ $t->nama }}">{{ $t->kode }}</span>
                                @endforeach
                            </div>
                        </td>
                        <td class="text-center text-xs whitespace-nowrap text-slate-600">
                            {{ $log->jam_mulai && $log->jam_selesai ? $log->jam_mulai.'–'.$log->jam_selesai : '-' }}
                            @if($log->durasiMenit())<span class="block text-slate-400">{{ durasi_format($log->durasiMenit()) }}</span>@endif
                        </td>
                        <td class="text-center">
                            @if ($log->lampirans->isNotEmpty())
                                <span class="badge bg-navy-50 text-navy-600 border-navy-100"><x-icon name="file" class="w-3 h-3"/> {{ $log->lampirans->count() }}</span>
                            @else
                                <span class="text-slate-300 text-xs">—</span>
                            @endif
                        </td>
                        <td><x-status :status="$log->status"/></td>
                        <td>
                            <div class="flex items-center justify-end gap-1">
                                <a href="{{ route('logbook.show', $log) }}" class="btn-ghost !p-2" title="Detail"><x-icon name="eye" class="w-4 h-4"/></a>
                                @if ($log->bolehEdit())
                                    <a href="{{ route('logbook.edit', $log) }}" class="btn-ghost !p-2" title="Ubah"><x-icon name="edit" class="w-4 h-4"/></a>
                                    <form action="{{ route('logbook.ajukan', $log) }}" method="POST" data-confirm="Ajukan kegiatan ini kepada atasan untuk diverifikasi?">
                                        @csrf
                                        <button class="btn-ghost !p-2 text-emerald-600 hover:!bg-emerald-50" title="Ajukan"><x-icon name="check-badge" class="w-4 h-4"/></button>
                                    </form>
                                    <form action="{{ route('logbook.destroy', $log) }}" method="POST" data-confirm="Hapus kegiatan ini? Tindakan ini tidak dapat dibatalkan.">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn-ghost !p-2 text-rose-500 hover:!bg-rose-50" title="Hapus"><x-icon name="trash" class="w-4 h-4"/></button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <div class="px-4 sm:px-5 py-4">
            {{ $logs->links() }}
        </div>
    @else
        <x-empty icon="book" teks="Belum ada catatan kegiatan" sub="Catat aktivitas harian Anda, lengkapi bukti dukung, lalu ajukan ke atasan.">
            <a href="{{ route('logbook.create') }}" class="btn-primary btn-sm mt-4"><x-icon name="plus" class="w-4 h-4"/> Catat Kegiatan Pertama</a>
        </x-empty>
    @endif
</div>
@endsection
