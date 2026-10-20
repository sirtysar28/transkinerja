@extends('layouts.app')
@section('title', 'Detail Kegiatan')

@section('content')
<div class="flex flex-wrap items-end justify-between gap-3">
    <div>
        <nav class="text-xs text-slate-400 mb-1"><a href="{{ route('logbook.index') }}" class="hover:text-navy-600">Log Book</a> → Detail</nav>
        <h1 class="page-title">Detail Kegiatan</h1>
        <p class="page-sub">Dicatat {{ $log->created_at->translatedFormat('d M Y, H:i') }} @if($log->updated_at != $log->created_at)• terakhir diubah {{ $log->updated_at->translatedFormat('d M Y, H:i') }}@endif</p>
    </div>
    <div class="flex gap-2">
        <a href="{{ route('logbook.index', ['bulan' => $log->tanggal->month, 'tahun' => $log->tanggal->year]) }}" class="btn-secondary btn-sm"><x-icon name="chevron-left" class="w-4 h-4"/> Kembali</a>
        @can('update', $log)
            <a href="{{ route('logbook.edit', $log) }}" class="btn-primary btn-sm"><x-icon name="edit" class="w-4 h-4"/> Ubah</a>
        @endcan
    </div>
</div>

<div class="grid lg:grid-cols-3 gap-6 items-start">
    <div class="lg:col-span-2 space-y-6">
        {{-- Detail kegiatan --}}
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
                        <p class="font-medium text-slate-700">
                            {{ $log->jam_mulai ?: '--:--' }} – {{ $log->jam_selesai ?: '--:--' }}
                            @if ($log->durasiMenit()) <span class="text-slate-400">({{ durasi_format($log->durasiMenit()) }})</span>@endif
                        </p>
                    </div>
                    <div class="sm:col-span-2"><p class="label !mb-0.5">Jenis Tugas</p>
                        <div class="flex flex-wrap gap-2">
                            @forelse ($log->taskTypes as $t)
                                <span class="badge bg-navy-50 text-navy-700 border-navy-100 py-1">{{ $t->kode }} — {{ $t->nama }}</span>
                            @empty
                                <span class="text-slate-400">—</span>
                            @endforelse
                        </div>
                    </div>
                </div>

                @if ($log->status === 'revisi' && $log->catatan_revisi)
                    <div class="mt-5 rounded-xl bg-rose-50 border border-rose-100 px-4 py-3">
                        <p class="text-xs font-bold text-rose-600 uppercase tracking-wide">Catatan Revisi</p>
                        <p class="text-sm text-rose-700 mt-1">{{ $log->catatan_revisi }}</p>
                    </div>
                @endif
            </div>
        </div>

        {{-- Bukti dukung --}}
        <div class="card">
            <div class="card-header"><p class="card-title">Bukti Dukung ({{ $log->lampirans->count() }})</p></div>
            @if ($log->lampirans->isNotEmpty())
                <div class="px-4 sm:px-5 pb-5 space-y-2">
                    @foreach ($log->lampirans as $l)
                        <div class="flex items-center gap-3 rounded-xl border border-slate-200 px-4 py-3 hover:border-navy-200 transition">
                            <x-icon name="file" class="w-4 h-4 text-navy-400 shrink-0"/>
                            <span class="text-sm text-slate-700 truncate flex-1">{{ $l->nama_asli }}</span>
                            <span class="text-[10px] text-slate-400 whitespace-nowrap shrink-0 hidden min-[420px]:inline">{{ ukuran_format($l->ukuran) }}</span>
                            <a href="{{ route('logbook.lampiran.download', $l) }}" class="btn-secondary btn-sm shrink-0 !px-3"><x-icon name="download" class="w-3.5 h-3.5"/> Unduh</a>
                        </div>
                    @endforeach
                </div>
            @else
                <x-empty icon="file" teks="Tidak ada lampiran"/>
            @endif
        </div>

        {{-- Riwayat persetujuan --}}
        <div class="card">
            <div class="card-header"><p class="card-title">Riwayat Persetujuan &amp; Perubahan</p></div>
            @if ($log->approvals->isNotEmpty())
                <div class="px-5 pb-6">
                    <div class="space-y-0">
                        @foreach ($log->approvals as $a)
                            <div class="flex gap-4">
                                <div class="flex flex-col items-center">
                                    <span class="w-8 h-8 rounded-full flex items-center justify-center shrink-0
                                        {{ $a->aksi === 'setujui' ? 'bg-emerald-100 text-emerald-600' : ($a->aksi === 'revisi' ? 'bg-rose-100 text-rose-600' : ($a->aksi === 'buka_kembali' ? 'bg-amber-100 text-amber-600' : 'bg-navy-100 text-navy-600')) }}">
                                        @if ($a->aksi === 'setujui')
                                            <x-icon name="check" class="w-4 h-4"/>
                                        @elseif ($a->aksi === 'revisi')
                                            <x-icon name="arrow-uturn" class="w-4 h-4"/>
                                        @elseif ($a->aksi === 'buka_kembali')
                                            <x-icon name="unlock" class="w-4 h-4"/>
                                        @else
                                            <x-icon name="upload" class="w-4 h-4"/>
                                        @endif
                                    </span>
                                    @if (! $loop->last)<span class="w-px flex-1 bg-slate-200 my-1"></span>@endif
                                </div>
                                <div class="pb-6 min-w-0">
                                    <p class="text-sm font-semibold text-slate-800">{{ $a->aksiLabel() }}</p>
                                    <p class="text-xs text-slate-400 mt-0.5">
                                        {{ $a->aktor?->name ?? 'Sistem' }} • {{ $a->created_at->translatedFormat('d M Y, H:i') }}
                                    </p>
                                    @if ($a->catatan)
                                        <p class="text-xs text-slate-500 mt-1.5 bg-slate-50 rounded-lg px-3 py-2 border border-slate-100">{{ $a->catatan }}</p>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @else
                <x-empty icon="clock" teks="Belum ada riwayat pengajuan" sub="Kegiatan masih berupa draft — ajukan untuk memulai alur persetujuan."/>
            @endif
        </div>
    </div>

    {{-- Panel pegawai & aksi --}}
    <div class="space-y-6 lg:sticky lg:top-24">
        <div class="card p-5">
            <p class="card-title mb-3">Pemilik Laporan</p>
            <div class="flex items-center gap-3">
                <span class="w-11 h-11 rounded-full bg-gradient-to-br from-navy-600 to-navy-800 text-white flex items-center justify-center font-bold">{{ strtoupper(substr($log->user->name, 0, 1)) }}</span>
                <div class="min-w-0">
                    <p class="text-sm font-bold text-navy-900">{{ $log->user->name }}</p>
                    <p class="text-xs text-slate-400">{{ $log->user->nip ? 'NIP '.$log->user->nip : $log->user->username }}</p>
                </div>
            </div>
            <div class="mt-4 pt-4 border-t border-slate-100 space-y-2 text-xs">
                <p class="flex justify-between"><span class="text-slate-400">Jabatan</span><span class="font-medium text-slate-600 text-right">{{ $log->user->jabatan ?? '-' }}</span></p>
                <p class="flex justify-between"><span class="text-slate-400">Pangkat/Gol.</span><span class="font-medium text-slate-600 text-right">{{ $log->user->pangkat_golongan ?? '-' }}</span></p>
                <p class="flex justify-between gap-4"><span class="text-slate-400">Unit Kerja</span><span class="font-medium text-slate-600 text-right">{{ $log->user->unitKerja?->nama ?? '-' }}</span></p>
            </div>
        </div>

        @if ($log->user_id === auth()->id() && $log->bolehEdit())
            <div class="card p-5">
                <p class="card-title mb-3">Tindakan</p>
                <form action="{{ route('logbook.ajukan', $log) }}" method="POST" data-confirm="Ajukan kegiatan ini kepada atasan untuk diverifikasi?">
                    @csrf
                    <button class="btn-primary w-full"><x-icon name="check-badge" class="w-4 h-4"/> {{ $log->status === 'revisi' ? 'Ajukan Ulang setelah Revisi' : 'Ajukan ke Atasan' }}</button>
                </form>
                <form action="{{ route('logbook.destroy', $log) }}" method="POST" class="mt-2" data-confirm="Hapus kegiatan ini secara permanen?">
                    @csrf @method('DELETE')
                    <button class="btn-ghost w-full text-rose-500 hover:!bg-rose-50"><x-icon name="trash" class="w-4 h-4"/> Hapus</button>
                </form>
            </div>
        @endif

        @if ($log->status === 'disetujui')
            <div class="card p-5 bg-emerald-50/60 border-emerald-200">
                <div class="flex gap-3">
                    <x-icon name="check-badge" class="w-6 h-6 text-emerald-500 shrink-0"/>
                    <p class="text-xs text-emerald-700 leading-relaxed">
                        Laporan ini <b>telah disetujui</b> dan tidak dapat diubah langsung. Perubahan hanya melalui mekanisme koreksi/pembukaan kembali oleh pejabat berwenang (Superadmin/Biro SDM).
                    </p>
                </div>
            </div>
        @endif
    </div>
</div>
@endsection
