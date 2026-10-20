@extends('layouts.app')
@section('title', $aksi === 'create' ? 'Tambah Kegiatan' : 'Ubah Kegiatan')

@section('content')
<div class="flex items-end justify-between gap-3">
    <div>
        <h1 class="page-title">{{ $aksi === 'create' ? 'Catat Kegiatan Baru' : 'Ubah Kegiatan' }}</h1>
        <p class="page-sub">Lengkapi rincian aktivitas harian sesuai formulir laporan kinerja ASN.</p>
    </div>
    <a href="{{ route('logbook.index') }}" class="btn-secondary btn-sm"><x-icon name="chevron-left" class="w-4 h-4"/> Kembali</a>
</div>

<form method="POST"
      action="{{ $aksi === 'create' ? route('logbook.store') : route('logbook.update', $log) }}"
      enctype="multipart/form-data"
      class="grid lg:grid-cols-3 gap-6 items-start">
    @csrf
    @if ($aksi === 'edit') @method('PUT') @endif

    <div class="card p-6 lg:col-span-2 space-y-5">
        @if ($aksi === 'edit' && $log->status === 'revisi')
            <div class="rounded-xl bg-rose-50 border border-rose-100 px-4 py-3 text-sm">
                <p class="font-bold text-rose-700">⚠ Laporan ini dikembalikan untuk direvisi</p>
                <p class="text-rose-600 mt-0.5">Catatan atasan: {{ $log->catatan_revisi }}</p>
            </div>
        @endif

        <div class="grid sm:grid-cols-2 gap-5">
            <div>
                <label for="tanggal" class="label">Tanggal Kegiatan <span class="req">*</span></label>
                <input type="date" id="tanggal" name="tanggal" required max="{{ today()->toDateString() }}"
                       value="{{ old('tanggal', $log->tanggal?->toDateString()) }}" class="input {{ $errors->has('tanggal') ? 'input-error' : '' }}"/>
                @error('tanggal')<p class="error-text">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="output_id" class="label">Output <span class="req">*</span></label>
                <select id="output_id" name="output_id" required class="select {{ $errors->has('output_id') ? 'input-error' : '' }}">
                    <option value="">— Pilih output pekerjaan —</option>
                    @foreach ($outputs as $o)
                        <option value="{{ $o->id }}" @selected(old('output_id', $log->output_id) == $o->id)>{{ $o->nama }}</option>
                    @endforeach
                </select>
                @error('output_id')<p class="error-text">{{ $message }}</p>@enderror
            </div>
        </div>

        <div>
            <label for="nama_kegiatan" class="label">Nama Kegiatan <span class="req">*</span></label>
            <textarea id="nama_kegiatan" name="nama_kegiatan" required rows="2" placeholder="cth: Menyusun ND penyampaian kolaborasi HUT RI Ke-81"
                      class="textarea {{ $errors->has('nama_kegiatan') ? 'input-error' : '' }}">{{ old('nama_kegiatan', $log->nama_kegiatan) }}</textarea>
            @error('nama_kegiatan')<p class="error-text">{{ $message }}</p>@enderror
        </div>

        <div class="grid sm:grid-cols-3 gap-5">
            <div class="sm:col-span-1">
                <label for="pengguna" class="label">Pengguna / Penerima <span class="req">*</span></label>
                <input type="text" id="pengguna" name="pengguna" required placeholder="cth: Biro OSDM"
                       value="{{ old('pengguna', $log->pengguna) }}" class="input {{ $errors->has('pengguna') ? 'input-error' : '' }}"/>
                @error('pengguna')<p class="error-text">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="jam_mulai" class="label">Jam Mulai</label>
                <input type="time" id="jam_mulai" name="jam_mulai" value="{{ old('jam_mulai', $log->jam_mulai) }}"
                       class="input {{ $errors->has('jam_mulai') ? 'input-error' : '' }}"/>
                @error('jam_mulai')<p class="error-text">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="jam_selesai" class="label">Jam Selesai</label>
                <input type="time" id="jam_selesai" name="jam_selesai" value="{{ old('jam_selesai', $log->jam_selesai) }}"
                       class="input {{ $errors->has('jam_selesai') ? 'input-error' : '' }}"/>
                @error('jam_selesai')<p class="error-text">{{ $message }}</p>@enderror
                <p class="help-text">Durasi dihitung otomatis dari jam mulai–selesai.</p>
            </div>
        </div>

        <div>
            <span class="label">Jenis Tugas <span class="req">*</span> <span class="normal-case font-normal text-slate-400">(boleh lebih dari satu)</span></span>
            <div class="grid sm:grid-cols-3 gap-3">
                @foreach ($taskTypes as $t)
                    <label class="flex items-center gap-3 rounded-xl border border-slate-200 px-4 py-3 cursor-pointer hover:border-navy-300 hover:bg-navy-50/50 transition has-[:checked]:border-navy-500 has-[:checked]:bg-navy-50">
                        <input type="checkbox" name="jenis_tugas[]" value="{{ $t->id }}"
                               @checked(in_array($t->id, old('jenis_tugas', $log->taskTypes->pluck('id')->toArray())))
                               class="w-4 h-4 rounded border-slate-300 text-navy-600 focus:ring-navy-400"/>
                        <span>
                            <span class="block text-sm font-bold text-navy-800">{{ $t->kode }}</span>
                            <span class="block text-[11px] text-slate-400">{{ $t->nama }}</span>
                        </span>
                    </label>
                @endforeach
            </div>
            @error('jenis_tugas')<p class="error-text">{{ $message }}</p>@enderror
        </div>

        <div>
            <label for="lampiran" class="label">Bukti Dukung <span class="normal-case font-normal text-slate-400">(opsional, bisa lebih dari satu)</span></label>
            <div class="rounded-xl border-2 border-dashed border-slate-200 hover:border-navy-300 transition p-5 text-center bg-slate-50/50">
                <x-icon name="upload" class="w-7 h-7 text-navy-300 mx-auto mb-2"/>
                <input type="file" id="lampiran" name="lampiran[]" multiple
                       accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.zip"
                       class="block w-full text-sm text-slate-500 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-navy-700 file:text-white hover:file:bg-navy-800 file:cursor-pointer"/>
                <p class="help-text mt-2">PDF, gambar, dokumen Office, atau ZIP — maks 5 MB per berkas.</p>
            </div>
            @error('lampiran.*')<p class="error-text">{{ $message }}</p>@enderror

            @if ($aksi === 'edit' && $log->lampirans->isNotEmpty())
                <div class="mt-3 space-y-2">
                    @foreach ($log->lampirans as $l)
                        <div class="flex items-center gap-3 rounded-xl border border-slate-200 px-4 py-2.5 bg-white">
                            <x-icon name="file" class="w-4 h-4 text-navy-400 shrink-0"/>
                            <span class="text-sm text-slate-700 truncate flex-1">{{ $l->nama_asli }}</span>
                            <span class="text-[10px] text-slate-400">{{ ukuran_format($l->ukuran) }}</span>
                            <a href="{{ route('logbook.lampiran.download', $l) }}" class="btn-ghost !p-1.5" title="Unduh"><x-icon name="download" class="w-4 h-4"/></a>
                            <button type="button" data-modal-target="modal-hapus-{{ $l->id }}" class="btn-ghost !p-1.5 text-rose-500" title="Hapus">
                                <x-icon name="trash" class="w-4 h-4"/>
                            </button>
                        </div>
                        {{-- Modal hapus lampiran --}}
                        <div id="modal-hapus-{{ $l->id }}" class="modal-root hidden">
                            <div class="modal-backdrop"></div>
                            <div class="modal-box">
                                <p class="font-bold text-navy-900">Hapus lampiran?</p>
                                <p class="text-sm text-slate-500 mt-2">Berkas <span class="font-semibold">{{ $l->nama_asli }}</span> akan dihapus permanen.</p>
                                <div class="flex justify-end gap-2 mt-6">
                                    <button type="button" data-modal-close class="btn-secondary btn-sm">Batal</button>
                                    <span onclick="event.stopPropagation()">
                                        <form action="{{ route('logbook.lampiran.hapus', $l) }}" method="POST">
                                            @csrf @method('DELETE')
                                            <button class="btn-danger btn-sm">Ya, hapus</button>
                                        </form>
                                    </span>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    {{-- Panel aksi --}}
    <div class="card p-6 space-y-4 lg:sticky lg:top-24">
        <p class="card-title">Simpan & Ajukan</p>
        <div class="text-xs text-slate-500 leading-relaxed bg-navy-50/70 rounded-xl p-4">
            <p class="font-semibold text-navy-800 mb-1">Alur persetujuan:</p>
            <ol class="space-y-1 list-decimal list-inside">
                <li>Simpan sebagai <b>Draft</b>, atau langsung <b>Ajukan</b>.</li>
                <li>Atasan memeriksa kelengkapan &amp; bukti dukung.</li>
                <li>Jika kurang, status menjadi <b>Perlu Revisi</b> + catatan.</li>
                <li>Laporan <b>Disetujui</b> tidak dapat diubah langsung.</li>
            </ol>
        </div>
        <button type="submit" name="aksi" value="simpan" class="btn-secondary w-full"><x-icon name="file" class="w-4 h-4"/> Simpan sebagai Draft</button>
        <button type="submit" name="aksi" value="ajukan" class="btn-primary w-full"><x-icon name="check-badge" class="w-4 h-4"/> Simpan &amp; Ajukan ke Atasan</button>
        <a href="{{ route('logbook.index') }}" class="btn-ghost w-full">Batal</a>
    </div>
</form>
@endsection
