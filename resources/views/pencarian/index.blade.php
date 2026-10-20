@extends('layouts.app')
@section('title', 'Pencarian Global')

@section('content')
<div>
    <h1 class="page-title">Pencarian Global</h1>
    <p class="page-sub">Hasil pencarian "{{ $q }}" sesuai izin akses Anda.</p>
</div>

<form method="GET" class="card p-4 flex flex-wrap gap-3 items-end" data-no-loader>
    <div class="flex-1 min-w-[220px]">
        <label class="label">Kata kunci</label>
        <div class="relative">
            <x-icon name="search" class="w-4 h-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400"/>
            <input name="q" value="{{ $q }}" placeholder="Cari pegawai, kegiatan, unit kerja…" class="input !pl-10" autofocus/>
        </div>
    </div>
    <button class="btn-primary">Cari</button>
</form>

@if (mb_strlen($q) < 2)
    <x-empty icon="search" teks="Masukkan minimal 2 karakter untuk mulai mencari"/>
@else
    @if ($hasil->kegiatan->isEmpty() && $hasil->pegawai->isEmpty() && $hasil->unit->isEmpty())
        <x-empty icon="search" teks="Tidak ditemukan hasil untuk &quot;{{ $q }}&quot;" sub="Coba kata kunci lain, misalnya nama kegiatan atau nama pegawai."/>
    @else
        <div class="space-y-6">
            {{-- Kegiatan --}}
            @if ($hasil->kegiatan->isNotEmpty())
                <div class="card">
                    <div class="card-header"><p class="card-title">Kegiatan ({{ $hasil->kegiatan->count() }})</p></div>
                    <div class="px-4 sm:px-5 pb-5 divide-y divide-slate-100">
                        @foreach ($hasil->kegiatan as $k)
                            <a href="{{ route('logbook.show', $k) }}" class="flex items-center gap-3 py-3 group">
                                <x-icon name="book" class="w-4 h-4 text-navy-300 shrink-0"/>
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-semibold text-slate-800 group-hover:text-navy-700 truncate">{{ $k->nama_kegiatan }}</p>
                                    <p class="text-[11px] text-slate-400 truncate">{{ $k->user->name }} • {{ $k->tanggal->translatedFormat('d M Y') }} • {{ $k->output->nama }}</p>
                                </div>
                                <span class="shrink-0"><x-status :status="$k->status"/></span>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Pegawai --}}
            @if ($hasil->pegawai->isNotEmpty())
                <div class="card">
                    <div class="card-header"><p class="card-title">Pegawai ({{ $hasil->pegawai->count() }})</p></div>
                    <div class="px-4 sm:px-5 pb-5 divide-y divide-slate-100">
                        @foreach ($hasil->pegawai as $p)
                            <div class="flex items-center gap-3 py-3">
                                <span class="w-9 h-9 rounded-full bg-gradient-to-br from-navy-600 to-navy-800 text-white flex items-center justify-center text-xs font-bold shrink-0">{{ strtoupper(substr($p->name, 0, 1)) }}</span>
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-semibold text-slate-800 truncate">{{ $p->name }}</p>
                                    <p class="text-[11px] text-slate-400 truncate">{{ $p->nip ?: $p->username }} • {{ $p->jabatan }} • {{ $p->unitKerja?->nama }}</p>
                                </div>
                                <a href="{{ route('laporan.index', ['pegawai' => $p->id]) }}" class="btn-secondary btn-sm shrink-0 whitespace-nowrap hidden min-[420px]:inline-flex">Lihat Laporan</a>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Unit --}}
            @if ($hasil->unit->isNotEmpty())
                <div class="card">
                    <div class="card-header"><p class="card-title">Unit Kerja ({{ $hasil->unit->count() }})</p></div>
                    <div class="px-4 sm:px-5 pb-5 divide-y divide-slate-100">
                        @foreach ($hasil->unit as $u)
                            <div class="flex items-center gap-3 py-3">
                                <x-icon name="building" class="w-4 h-4 text-navy-300 shrink-0"/>
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-semibold text-slate-800 truncate">{{ $u->nama }}</p>
                                    <p class="text-[11px] text-slate-400 truncate">Kode: {{ $u->kode }}</p>
                                </div>
                                <a href="{{ route('monitoring.index') }}" class="btn-secondary btn-sm shrink-0 whitespace-nowrap hidden min-[420px]:inline-flex">Monitoring</a>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    @endif
@endif
@endsection
