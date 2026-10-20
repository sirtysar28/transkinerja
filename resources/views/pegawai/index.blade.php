@extends('layouts.app')
@section('title', 'Pegawai & Unit Kerja')

@section('content')
@php($isPusat = auth()->user()->isAdminPusat())
@php($digitrans = \App\Services\DigitransService::aktif())

@if ($digitrans)
    <div class="card border-navy-200 bg-navy-50/60">
        <div class="px-4 sm:px-5 py-4 flex flex-wrap items-center gap-3">
            <span class="w-10 h-10 rounded-xl bg-gradient-to-br from-navy-700 to-navy-500 text-white flex items-center justify-center shrink-0"><x-icon name="shield" class="w-5 h-5"/></span>
            <div class="flex-1 min-w-[220px]">
                <p class="text-sm font-bold text-navy-900">Sumber data terpadu: DigiTrans Biro OSDMRB</p>
                <p class="text-xs text-navy-700/80 mt-0.5">Data pegawai, identitas login &amp; status akun dikelola terpusat di DigiTrans. Sinkron otomatis tiap 15 menit — di sini hanya peran, atasan &amp; atribut kinerja yang diatur.</p>
            </div>
            <form action="{{ route('pegawai.sinkronDigitrans') }}" method="POST" data-no-loader>
                @csrf
                <button class="btn-primary btn-sm"><x-icon name="refresh" class="w-4 h-4"/> Sinkron DigiTrans</button>
            </form>
        </div>
    </div>
@endif

<div class="flex flex-wrap items-end justify-between gap-3">
    <div>
        <h1 class="page-title">Data Pegawai</h1>
        <p class="page-sub">{{ $isPusat ? 'Manajemen ASN lintas unit kerja' : 'Manajemen ASN unit kerja Anda' }} — {{ $pegawai->total() }} pegawai aktif</p>
    </div>
    <div class="flex flex-wrap gap-2 w-full sm:w-auto">
        <button type="button" data-modal-target="modal-import" class="btn-secondary w-full sm:w-auto justify-center"><x-icon name="upload" class="w-4 h-4"/> Import Excel</button>
        @if ($digitrans)
            <span class="btn-primary cursor-default opacity-60 w-full sm:w-auto justify-center" title="Akun baru dibuat di DigiTrans"><x-icon name="plus" class="w-4 h-4"/> Tambah via DigiTrans</span>
        @else
            <a href="{{ route('pegawai.create') }}" class="btn-primary w-full sm:w-auto justify-center"><x-icon name="plus" class="w-4 h-4"/> Tambah Pegawai</a>
        @endif
    </div>
</div>

{{-- Panel hasil import --}}
@if (session('hasil_import'))
    @php($h = session('hasil_import'))
    <div class="card p-5 mb-6 border {{ $h['total_gagal'] ? 'border-amber-200' : 'border-emerald-200' }}">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                <span class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center">
                    <x-icon name="check-badge" class="w-5 h-5"/>
                </span>
                <div>
                    <p class="font-bold text-navy-900 text-sm">Import "{{ $h['berkas'] }}" selesai</p>
                    <p class="text-xs text-slate-500 mt-0.5">
                        <b class="text-emerald-600">{{ $h['tambah'] }} pegawai baru</b> ·
                        <b class="text-navy-700">{{ $h['perbarui'] }} diperbarui</b> ·
                        <b class="{{ $h['total_gagal'] ? 'text-rose-500' : 'text-slate-400' }}">{{ $h['total_gagal'] }} gagal</b>
                    </p>
                </div>
            </div>
            <p class="text-[11px] text-slate-400">Akun baru: username = NIP, password awal: <code>password</code></p>
        </div>
        @if ($h['gagal'])
            <div class="mt-3 rounded-xl bg-amber-50 border border-amber-100 px-4 py-3 text-xs text-amber-800 max-h-40 overflow-auto">
                @foreach ($h['gagal'] as $g)
                    <p>• {{ $g }}</p>
                @endforeach
                @if ($h['total_gagal'] > count($h['gagal']))
                    <p class="mt-1 italic">… dan {{ $h['total_gagal'] - count($h['gagal']) }} lainnya</p>
                @endif
            </div>
        @endif
    </div>
@endif

{{-- Modal import Excel --}}
<div id="modal-import" class="modal-root hidden">
    <div class="modal-backdrop" data-modal-close></div>
    <div class="modal-box">
        <div class="flex items-start justify-between gap-4">
            <div>
                <p class="font-bold text-navy-900 text-lg">Import Pegawai dari Excel</p>
                <p class="text-xs text-slate-500 mt-1">Format sama dengan aplikasi <b>Digitrans / Data Dashboard</b> (kolom NAMA, NIP, STATUS, Jabatan, Es. II/I/III, PANGKAT, GOLONGAN, EMAIL, dll).</p>
            </div>
            <button type="button" data-modal-close class="text-slate-400 hover:text-slate-600"><x-icon name="x" class="w-5 h-5"/></button>
        </div>
        <div class="mt-4 space-y-3 text-sm">
            <div class="rounded-xl bg-navy-50/70 border border-navy-100 px-4 py-3 text-xs text-navy-800/80 space-y-1.5">
                <p>• Berkas <b>“Data Dashboard”</b> instansi dapat diunggah langsung — format kolomnya sudah didukung.</p>
                <p>• NIP yang sudah terdaftar akan <b>diperbarui</b>, NIP baru akan <b>ditambahkan</b> sebagai Pegawai.</p>
                <p>• Unit kerja diambil dari kolom <b>Es. II</b> (lalu Es. I, Es. III) dan dibuat otomatis bila belum ada.</p>
                <p>• Master diisi nama/kode: STATUS (PNS/CPNS/PPPK), PANGKAT &amp; GOLONGAN, LEVEL PENDIDIKAN (SD–S3), Es. II / Es. I (unit baru otomatis dibuat).</p>
                <p>• Format tanggal fleksibel: <code>12/1/2029</code>, <code>01 April 2028</code>, <code>2024-01-31</code>.</p>
                <p>• <b>BATAS USIA PENSIUN</b> kosong dihitung otomatis dari BUP (60 th Eselon I/II &amp; Fungsional Madya, selain itu 58 th).</p>
                <p>• Kolom tambahan didukung: <b>KEMAMPUAN RENANG</b> &amp; <b>KEMAMPUAN BAHASA INGGRIS</b> (Tidak Bisa / Pemula / Menengah / Mahir).</p>
                <p>• Baris tidak valid dilewati &amp; dilaporkan; baris lain tetap diproses.</p>
                <p>• Akun baru: username = <b>NIP</b>, password awal = <code>password</code> (ingatkan untuk diganti).</p>
                @unless($isPusat)
                    <p>• Sebagai Admin Unit, seluruh pegawai import akan masuk ke <b>unit kerja Anda</b>.</p>
                @endunless
            </div>
            <a href="{{ route('pegawai.template') }}" class="btn-secondary btn-sm w-full !justify-center">
                <x-icon name="download" class="w-4 h-4"/> Unduh Template Excel
            </a>
            <form action="{{ route('pegawai.import') }}" method="POST" enctype="multipart/form-data"
                  data-confirm="Mulai import sekarang? Pastikan format kolom sesuai template.">
                @csrf
                <label class="label">Pilih Berkas (.xlsx / .xls / .csv, maks 10 MB) <span class="req">*</span></label>
                <input type="file" name="berkas" required accept=".xlsx,.xls,.csv"
                       class="block w-full text-sm text-slate-600 file:mr-3 file:py-2.5 file:px-4 file:rounded-xl file:border-0 file:text-sm file:font-semibold file:bg-navy-700 file:text-white hover:file:bg-navy-800 file:cursor-pointer"/>
                @error('berkas')<p class="error-text mt-1">{{ $message }}</p>@enderror
                <button class="btn-primary w-full mt-4"><x-icon name="upload" class="w-4 h-4"/> Import Sekarang</button>
            </form>
        </div>
    </div>
</div>

<div class="card p-4">
    <form method="GET" class="flex flex-wrap gap-2.5 items-end" data-no-loader>
        <div class="flex-1 min-w-[200px]">
            <label class="label">Cari</label>
            <div class="relative">
                <x-icon name="search" class="w-4 h-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400"/>
                <input name="q" value="{{ $filter['q'] ?? '' }}" placeholder="Nama, NIP, username, jabatan…" class="input !pl-10"/>
            </div>
        </div>

        @if ($isPusat)
            <div>
                <label class="label">Unit Kerja</label>
                <x-filter-check name="unit" label="Unit Kerja" :options="$unitList" :selected="$filter['unit'] ?? []" searchable/>
            </div>
        @endif
        <div>
            <label class="label">Status</label>
            <x-filter-check name="status" label="Status" lebar="w-44" :options="$statusOptions" :selected="$filter['status'] ?? []"/>
        </div>
        <div>
            <label class="label">Peran</label>
            <x-filter-check name="role" label="Peran" lebar="w-52" :options="$roleList" :selected="$filter['role'] ?? []"/>
        </div>
        <div>
            <label class="label">Jenis Kelamin</label>
            <x-filter-check name="jk" label="Jenis Kelamin" lebar="w-44" :options="$jkOptions" :selected="$filter['jk'] ?? []"/>
        </div>
        <div>
            <label class="label">Pendidikan</label>
            <x-filter-check name="pendidikan" label="Pendidikan" lebar="w-44" :options="$pendidikanOptions" :selected="$filter['pendidikan'] ?? []"/>
        </div>
        <div>
            <label class="label">Kemampuan Renang</label>
            <x-filter-check name="renang" label="Renang" lebar="w-44" :options="$renangOptions" :selected="$filter['renang'] ?? []"/>
        </div>
        <div>
            <label class="label">Kemampuan Bahasa Inggris</label>
            <x-filter-check name="inggris" label="B. Inggris" lebar="w-44" :options="$inggrisOptions" :selected="$filter['inggris'] ?? []"/>
        </div>

        <button class="btn-primary w-full sm:w-auto"><x-icon name="search" class="w-4 h-4"/> Terapkan</button>
        <a href="{{ route('pegawai.index') }}" class="btn-ghost w-full sm:w-auto justify-center">Reset</a>
    </form>
</div>

<div class="card">
    @if ($pegawai->isNotEmpty())
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                <tr>
                    <th>Nama / NIP</th>
                    <th>Jabatan</th>
                    <th>Pangkat / Gol.</th>
                    <th>Unit Kerja</th>
                    <th>Status / Peran</th>
                    <th>BUP</th>
                    <th>Atasan</th>
                    <th class="text-right">Aksi</th>
                </tr>
                </thead>
                <tbody>
                @foreach ($pegawai as $p)
                    <tr>
                        <td>
                            <div class="flex items-center gap-3">
                                <span class="w-9 h-9 rounded-full bg-gradient-to-br from-navy-600 to-navy-800 text-white flex items-center justify-center text-xs font-bold shrink-0">{{ strtoupper(substr($p->name, 0, 1)) }}</span>
                                <div>
                                    <p class="font-semibold text-slate-800 text-[13px]">{{ $p->name }}</p>
                                    <p class="text-[11px] text-slate-400">{{ $p->nip ?: $p->username }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="text-xs text-slate-600 max-w-[160px]">{{ $p->jabatan ?? '—' }}</td>
                        <td class="text-xs text-slate-600">{{ $p->pangkat_golongan ?? '—' }}</td>
                        <td class="text-xs text-slate-600">{{ $p->unitKerja?->nama ?? '—' }}</td>
                        <td>
                            <span class="badge {{ $p->role === 'superadmin' ? 'bg-navy-800 text-white border-navy-800' : ($p->role === 'admin_unit' ? 'bg-navy-100 text-navy-800 border-navy-200' : ($p->role === 'atasan' ? 'bg-gold-400/20 text-yellow-800 border-gold-400/50' : 'bg-slate-100 text-slate-600 border-slate-200')) }}">
                                {{ $p->roleLabel() }}
                            </span>
                            @if ($p->status_pegawai)
                                @php($stBadge = strtoupper(trim((string) $p->status_pegawai)))
                                <span class="badge mt-1 {{ $stBadge === 'PNS' ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : ($stBadge === 'CPNS' ? 'bg-amber-50 text-amber-700 border-amber-200' : 'bg-sky-50 text-sky-700 border-sky-200') }}">{{ $p->status_pegawai }}</span>
                            @endif
                        </td>
                        <td class="text-xs text-slate-600">{{ $p->batas_usia_pensiun?->format('d M Y') ?? '—' }}</td>
                        <td class="text-xs text-slate-500">{{ $p->atasan?->name ?? '—' }}</td>
                        <td>
                            <div class="flex justify-end gap-1">
                                <a href="{{ route('pegawai.edit', $p) }}" class="btn-ghost !p-2" title="Ubah"><x-icon name="edit" class="w-4 h-4"/></a>
                                @if (! $digitrans || ! $p->digitrans_id)
                                    <form action="{{ route('pegawai.resetPassword', $p) }}" method="POST" data-confirm="Reset password {{ $p->name }} menjadi 'password'?">
                                        @csrf
                                        <button class="btn-ghost !p-2 text-amber-500 hover:!bg-amber-50" title="Reset password"><x-icon name="key" class="w-4 h-4"/></button>
                                    </form>
                                @endif
                                @if ($p->id !== auth()->id() && (! $digitrans || ! $p->digitrans_id))
                                    <form action="{{ route('pegawai.destroy', $p) }}" method="POST" data-confirm="Nonaktifkan akun {{ $p->name }}? Riwayat data tetap tersimpan.">
                                        @csrf @method('DELETE')
                                        <button class="btn-ghost !p-2 text-rose-500 hover:!bg-rose-50" title="Nonaktifkan"><x-icon name="trash" class="w-4 h-4"/></button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <div class="px-4 sm:px-5 py-4">{{ $pegawai->links() }}</div>
    @else
        <x-empty icon="users" teks="Belum ada pegawai" sub="Tambahkan data ASN untuk mulai menggunakan Log Book."/>
    @endif
</div>
@endsection
