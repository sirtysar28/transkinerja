@extends('layouts.app')
@section('title', 'Periksa Laporan')

@section('content')
@php($user = auth()->user())
<div class="flex flex-wrap items-end justify-between gap-3">
    <div>
        <nav class="text-xs text-slate-400 mb-1"><a href="{{ route('persetujuan.index') }}" class="hover:text-navy-600">Persetujuan</a> → Periksa</nav>
        <h1 class="page-title">Periksa &amp; Nilai Laporan</h1>
        <p class="page-sub">Periksa kelengkapan kegiatan, bukti dukung, dan kesesuaian waktu kerja.</p>
    </div>
    <a href="{{ route('persetujuan.index') }}" class="btn-secondary btn-sm"><x-icon name="chevron-left" class="w-4 h-4"/> Kembali ke antrean</a>
</div>

<div class="grid lg:grid-cols-3 gap-6 items-start">
    <div class="lg:col-span-2 space-y-6">
        <div class="card">
            <div class="card-header">
                <p class="card-title">Rincian Kegiatan</p>
                <x-status :status="$log->status"/>
            </div>
            <div class="px-4 sm:px-5 pb-5">
                <p class="text-base font-semibold text-slate-800 leading-relaxed">{{ $log->nama_kegiatan }}</p>
                <div class="grid sm:grid-cols-2 gap-x-8 gap-y-4 mt-5 text-sm">
                    <div><p class="label !mb-0.5">Tanggal</p><p class="font-medium text-slate-700">{{ $log->tanggal->translatedFormat('l, d F Y') }}</p></div>
                    <div><p class="label !mb-0.5">Output</p><p><span class="badge-draft">{{ $log->output->nama }}</span></p></div>
                    <div><p class="label !mb-0.5">Pengguna / Penerima</p><p class="font-medium text-slate-700">{{ $log->pengguna }}</p></div>
                    <div><p class="label !mb-0.5">Jam Kerja</p>
                        <p class="font-medium text-slate-700">{{ $log->jam_mulai ?: '--:--' }} – {{ $log->jam_selesai ?: '--:--' }}
                            @if ($log->durasiMenit())<span class="text-slate-400">({{ durasi_format($log->durasiMenit()) }})</span>@endif</p>
                    </div>
                    <div class="sm:col-span-2"><p class="label !mb-0.5">Jenis Tugas</p>
                        <div class="flex gap-2 flex-wrap">
                            @forelse ($log->taskTypes as $t)
                                <span class="badge bg-navy-50 text-navy-700 border-navy-100 py-1">{{ $t->kode }} — {{ $t->nama }}</span>
                            @empty <span class="text-slate-400">—</span> @endforelse
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><p class="card-title">Bukti Dukung ({{ $log->lampirans->count() }})</p></div>
            @if ($log->lampirans->isNotEmpty())
                <div class="px-4 sm:px-5 pb-5 space-y-2">
                    @foreach ($log->lampirans as $l)
                        <div class="flex items-center gap-3 rounded-xl border border-slate-200 px-4 py-3">
                            <x-icon name="file" class="w-4 h-4 text-navy-400 shrink-0"/>
                            <span class="text-sm text-slate-700 truncate flex-1">{{ $l->nama_asli }}</span>
                            <a href="{{ route('logbook.lampiran.download', $l) }}" class="btn-secondary btn-sm shrink-0 !px-3"><x-icon name="download" class="w-3.5 h-3.5"/> Unduh</a>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="px-5 pb-5"><div class="rounded-xl bg-amber-50 border border-amber-100 px-4 py-3 text-sm text-amber-700">⚠ Tidak ada bukti dukung dilampirkan. Pertimbangkan meminta revisi bila bukti diperlukan.</div></div>
            @endif
        </div>

        <div class="card">
            <div class="card-header"><p class="card-title">Riwayat</p></div>
            <div class="px-4 sm:px-5 pb-5">
                @forelse ($log->approvals as $a)
                    <div class="flex gap-3 py-2.5 border-b border-slate-50 last:border-0">
                        <span class="badge-draft shrink-0 mt-0.5">{{ $a->aksiLabel() }}</span>
                        <div class="min-w-0">
                            <p class="text-xs text-slate-500">{{ $a->aktor?->name ?? 'Sistem' }} • {{ $a->created_at->translatedFormat('d M Y H:i') }}</p>
                            @if ($a->catatan)<p class="text-xs text-slate-600 mt-0.5">{{ $a->catatan }}</p>@endif
                        </div>
                    </div>
                @empty
                    <p class="text-xs text-slate-400">—</p>
                @endforelse
            </div>
        </div>
    </div>

    {{-- Panel aksi penilaian --}}
    <div class="space-y-6 lg:sticky lg:top-24">
        <div class="card p-5">
            <p class="card-title mb-3">Pemilik Laporan</p>
            <div class="flex items-center gap-3">
                <span class="w-11 h-11 rounded-full bg-gradient-to-br from-navy-600 to-navy-800 text-white flex items-center justify-center font-bold">{{ strtoupper(substr($log->user->name, 0, 1)) }}</span>
                <div class="min-w-0">
                    <p class="text-sm font-bold text-navy-900">{{ $log->user->name }}</p>
                    <p class="text-xs text-slate-400">{{ $log->user->jabatan }} • {{ $log->user->unitKerja?->nama }}</p>
                </div>
            </div>
        </div>

        @if ($log->status === 'diajukan')
            <div class="card p-5">
                <p class="card-title mb-3">Keputusan</p>

                <form action="{{ route('persetujuan.setujui', $log) }}" method="POST" data-confirm="Setujui laporan ini?">
                    @csrf
                    <textarea name="catatan" rows="2" placeholder="Catatan persetujuan (opsional)" class="textarea mb-3"></textarea>
                    <button class="btn-success w-full"><x-icon name="check" class="w-4 h-4"/> Setujui Laporan</button>
                </form>

                <hr class="my-4 border-slate-100"/>

                <form action="{{ route('persetujuan.revisi', $log) }}" method="POST">
                    @csrf
                    <label class="label">Catatan revisi <span class="req">*</span></label>
                    <textarea name="catatan" rows="3" required placeholder="Tuliskan kekurangan yang harus diperbaiki pegawai…" class="textarea"></textarea>
                    <button class="btn-warning w-full mt-3"><x-icon name="arrow-uturn" class="w-4 h-4"/> Kembalikan untuk Revisi</button>
                </form>
            </div>
        @else
            <div class="card p-5">
                <p class="card-title mb-2">Status: <x-status :status="$log->status"/></p>
                <p class="text-xs text-slate-500">Laporan ini sudah diproses dan tidak lagi menunggu persetujuan.</p>
            </div>
        @endif

        @if ($user->isAdminPusat() && $log->status === 'disetujui')
            <div class="card p-5 border-amber-200">
                <p class="card-title mb-2 !text-amber-600">Mekanisme Koreksi (Superadmin)</p>
                <form action="{{ route('persetujuan.bukaKembali', $log) }}" method="POST" data-confirm="Buka kembali laporan yang sudah disetujui? Status akan menjadi Draft.">
                    @csrf
                    <label class="label">Alasan pembukaan kembali <span class="req">*</span></label>
                    <textarea name="catatan" rows="2" required class="textarea" placeholder="cth: koreksi data atas permintaan Biro SDM"></textarea>
                    <button class="btn-danger w-full mt-3"><x-icon name="unlock" class="w-4 h-4"/> Buka Kembali menjadi Draft</button>
                </form>
            </div>
        @endif
    </div>
</div>
@endsection
