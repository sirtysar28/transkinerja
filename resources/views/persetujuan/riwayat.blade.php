@extends('layouts.app')
@section('title', 'Riwayat Persetujuan')

@section('content')
<div class="flex flex-wrap items-end justify-between gap-3">
    <div>
        <h1 class="page-title">Riwayat Persetujuan</h1>
        <p class="page-sub">Jejak seluruh tindakan pengajuan, revisi, dan persetujuan pada cakupan Anda.</p>
    </div>
    <div class="flex items-center gap-2">
        <x-period-filter :bulan="$bulan" :tahun="$tahun"/>
        <a href="{{ route('persetujuan.index') }}" class="btn-secondary w-full sm:w-auto justify-center"><x-icon name="check-badge" class="w-4 h-4"/> Antrean</a>
    </div>
</div>

<div class="card">
    @if ($riwayat->isNotEmpty())
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>Waktu</th><th>Aksi</th><th>Pegawai</th><th>Kegiatan</th><th>Oleh</th><th>Catatan</th></tr></thead>
                <tbody>
                @foreach ($riwayat as $r)
                    <tr>
                        <td class="whitespace-nowrap text-xs text-slate-500">{{ $r->created_at->translatedFormat('d M Y H:i') }}</td>
                        <td>
                            @if ($r->aksi === 'setujui')<span class="badge-disetujui">Disetujui</span>
                            @elseif ($r->aksi === 'revisi')<span class="badge-revisi">Revisi</span>
                            @elseif ($r->aksi === 'buka_kembali')<span class="badge-diajukan">Dibuka Kembali</span>
                            @else<span class="badge-draft">Diajukan</span>@endif
                        </td>
                        <td class="text-[13px] font-semibold text-slate-700">{{ \Illuminate\Support\Str::before($r->logKegiatan->user->name ?? '-', ',') }}</td>
                        <td class="max-w-[240px]">
                            @if ($r->logKegiatan)
                                <a href="{{ route('persetujuan.show', $r->logKegiatan) }}" class="text-[13px] hover:text-navy-700">{{ Str::limit($r->logKegiatan->nama_kegiatan, 60) }}</a>
                            @endif
                        </td>
                        <td class="text-xs text-slate-500">{{ $r->aktor?->name ?? 'Sistem' }}</td>
                        <td class="text-xs text-slate-500 max-w-[200px]">{{ $r->catatan ? Str::limit($r->catatan, 60) : '—' }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <div class="px-4 sm:px-5 py-4">{{ $riwayat->links() }}</div>
    @else
        <x-empty icon="clock" teks="Belum ada riwayat persetujuan" sub="Riwayat akan muncul setelah ada pengajuan atau penilaian."/>
    @endif
</div>
@endsection
