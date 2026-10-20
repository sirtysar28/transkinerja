@extends('layouts.app')
@section('title', 'Unit Kerja')

@section('content')
<div class="flex flex-wrap items-end justify-between gap-3">
    <div>
        <h1 class="page-title">Unit Kerja</h1>
        <p class="page-sub">Referensi organisasi — {{ $unit->total() }} unit terdaftar.</p>
    </div>
</div>

<div class="grid lg:grid-cols-3 gap-6 items-start">
    <div class="card lg:col-span-2">
        <div class="card-header"><p class="card-title">Daftar Unit Kerja</p></div>
        @if ($unit->isNotEmpty())
            <div class="table-wrap">
                <table class="data-table">
                    <thead><tr><th>Kode</th><th>Nama Unit</th><th>Induk</th><th class="text-center">Pegawai</th><th>Status</th><th class="text-right">Aksi</th></tr></thead>
                    <tbody>
                    @foreach ($unit as $u)
                        <tr>
                            <td><span class="badge bg-navy-50 text-navy-700 border-navy-100 font-mono">{{ $u->kode }}</span></td>
                            <td class="font-semibold text-slate-800">{{ $u->nama }}</td>
                            <td class="text-xs text-slate-500">{{ $u->parent?->kode ?? '—' }}</td>
                            <td class="text-center font-bold text-navy-700">{{ $u->pegawai_count }}</td>
                            <td>
                                @if ($u->is_aktif)<span class="badge-disetujui">Aktif</span>@else<span class="badge-draft">Nonaktif</span>@endif
                            </td>
                            <td>
                                <div class="flex justify-end">
                                    <button type="button" data-modal-target="modal-unit-{{ $u->id }}" class="btn-ghost !p-2" title="Ubah"><x-icon name="edit" class="w-4 h-4"/></button>
                                </div>
                            </td>
                        </tr>

                        {{-- Modal ubah unit --}}
                        <div id="modal-unit-{{ $u->id }}" class="modal-root hidden">
                            <div class="modal-backdrop"></div>
                            <div class="modal-box">
                                <p class="font-bold text-navy-900 mb-4">Ubah Unit Kerja</p>
                                <form action="{{ route('unit-kerja.update', $u) }}" method="POST" class="space-y-4">
                                    @csrf @method('PUT')
                                    <div>
                                        <label class="label">Kode <span class="req">*</span></label>
                                        <input name="kode" required value="{{ $u->kode }}" class="input"/>
                                    </div>
                                    <div>
                                        <label class="label">Nama <span class="req">*</span></label>
                                        <input name="nama" required value="{{ $u->nama }}" class="input"/>
                                    </div>
                                    <div>
                                        <label class="label">Unit Induk</label>
                                        <select name="parent_id" class="select">
                                            <option value="">— Tidak ada —</option>
                                            @foreach ($parentList->where('id', '!=', $u->id) as $p)
                                                <option value="{{ $p->id }}" @selected($u->parent_id == $p->id)>{{ $p->nama }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <label class="flex items-center gap-2 text-sm text-slate-600">
                                        <input type="checkbox" name="is_aktif" value="1" @checked($u->is_aktif) class="rounded border-slate-300 text-navy-600"/>
                                        Unit aktif
                                    </label>
                                    <div class="flex justify-end gap-2 pt-2">
                                        <button type="button" data-modal-close class="btn-secondary btn-sm">Batal</button>
                                        <button class="btn-primary btn-sm">Simpan</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <div class="px-4 sm:px-5 py-4">{{ $unit->links() }}</div>
        @else
            <x-empty icon="building" teks="Belum ada unit kerja"/>
        @endif
    </div>

    {{-- Form tambah --}}
    <div class="card p-6 lg:sticky lg:top-24">
        <p class="card-title mb-4">Tambah Unit Kerja</p>
        <form action="{{ route('unit-kerja.store') }}" method="POST" class="space-y-4">
            @csrf
            <div>
                <label class="label">Kode <span class="req">*</span></label>
                <input name="kode" required placeholder="cth: BPPMT-PLG" class="input {{ $errors->has('kode') ? 'input-error' : '' }}"/>
                @error('kode')<p class="error-text">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="label">Nama Unit <span class="req">*</span></label>
                <input name="nama" required placeholder="cth: BPPMT Palembang" class="input {{ $errors->has('nama') ? 'input-error' : '' }}"/>
                @error('nama')<p class="error-text">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="label">Unit Induk</label>
                <select name="parent_id" class="select">
                    <option value="">— Tidak ada —</option>
                    @foreach ($parentList as $p)
                        <option value="{{ $p->id }}">{{ $p->nama }}</option>
                    @endforeach
                </select>
            </div>
            <button class="btn-primary w-full"><x-icon name="plus" class="w-4 h-4"/> Tambah Unit</button>
        </form>
    </div>
</div>
@endsection
