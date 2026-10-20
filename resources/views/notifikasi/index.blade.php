@extends('layouts.app')
@section('title', 'Notifikasi')

@section('content')
<div class="flex flex-wrap items-end justify-between gap-3">
    <div>
        <h1 class="page-title">Notifikasi</h1>
        <p class="page-sub">Pengingat pengisian, pengajuan, revisi, dan persetujuan.</p>
    </div>
    <form action="{{ route('notifikasi.bacaSemua') }}" method="POST">
        @csrf
        <button class="btn-secondary btn-sm"><x-icon name="check" class="w-4 h-4"/> Tandai semua terbaca</button>
    </form>
</div>

<div class="card">
    @if ($notifikasi->isNotEmpty())
        <div class="divide-y divide-slate-100">
            @foreach ($notifikasi as $n)
                <a href="{{ $n->terbaca() ? ($n->tautan ?: '#') : route('notifikasi.baca', $n) }}"
                   class="flex items-start gap-3 sm:gap-4 px-4 sm:px-5 py-4 hover:bg-navy-50/40 transition {{ $n->terbaca() ? '' : 'bg-navy-50/30' }}">
                    <span class="w-2.5 h-2.5 rounded-full mt-2 shrink-0 {{ $n->terbaca() ? 'bg-slate-200' : 'bg-navy-500' }}"></span>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-semibold {{ $n->terbaca() ? 'text-slate-600' : 'text-navy-900' }}">{{ $n->judul }}</p>
                        <p class="text-xs text-slate-500 mt-0.5">{{ $n->pesan }}</p>
                        <p class="text-[10px] text-slate-400 mt-1">{{ $n->created_at->translatedFormat('l, d F Y • H:i') }}</p>
                    </div>
                    @if (! $n->terbaca())
                        <span class="badge-diajukan shrink-0">Baru</span>
                    @endif
                </a>
            @endforeach
        </div>
        <div class="px-4 sm:px-5 py-4">{{ $notifikasi->links() }}</div>
    @else
        <x-empty icon="bell" teks="Belum ada notifikasi" sub="Notifikasi pengajuan dan persetujuan akan muncul di sini."/>
    @endif
</div>
@endsection
