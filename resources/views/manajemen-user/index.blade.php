@extends('layouts.app')
@section('title', 'Manajemen User')

@section('content')
<div class="flex flex-wrap items-end justify-between gap-3">
    <div>
        <h1 class="page-title">Manajemen User</h1>
        <p class="page-sub">Siapa saja yang punya akses login — mapping peran, username, email, dan status akses.</p>
    </div>
    @if ($otpAktif)
        <span class="badge bg-emerald-50 text-emerald-700 border-emerald-200 !px-3 !py-1.5">
            <x-icon name="shield" class="w-4 h-4"/> Verifikasi OTP saat login: AKTIF
        </span>
    @else
        <span class="badge bg-slate-100 text-slate-500 border-slate-200 !px-3 !py-1.5">
            <x-icon name="shield" class="w-4 h-4"/> Verifikasi OTP saat login: nonaktif
        </span>
    @endif
</div>

{{-- Ringkasan status akses --}}
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
    <div class="card p-4 flex items-center gap-3">
        <span class="w-10 h-10 rounded-xl bg-navy-50 text-navy-700 flex items-center justify-center shrink-0"><x-icon name="users" class="w-5 h-5"/></span>
        <div><p class="text-xl font-extrabold text-navy-900">{{ number_format($aksesStat['total'], 0, ',', '.') }}</p><p class="text-[11px] text-slate-500">Total akun terdaftar</p></div>
    </div>
    <div class="card p-4 flex items-center gap-3">
        <span class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center shrink-0"><x-icon name="check-badge" class="w-5 h-5"/></span>
        <div><p class="text-xl font-extrabold text-emerald-600">{{ number_format($aksesStat['aktif'], 0, ',', '.') }}</p><p class="text-[11px] text-slate-500">Punya akses login (aktif)</p></div>
    </div>
    <div class="card p-4 flex items-center gap-3">
        <span class="w-10 h-10 rounded-xl bg-rose-50 text-rose-500 flex items-center justify-center shrink-0"><x-icon name="lock" class="w-5 h-5"/></span>
        <div><p class="text-xl font-extrabold text-rose-500">{{ number_format($aksesStat['nonaktif'], 0, ',', '.') }}</p><p class="text-[11px] text-slate-500">Akses dicabut (nonaktif)</p></div>
    </div>
    <div class="card p-4 flex items-center gap-3 {{ $aksesStat['tanpaEmail'] ? 'border-amber-200' : '' }}">
        <span class="w-10 h-10 rounded-xl {{ $aksesStat['tanpaEmail'] ? 'bg-amber-50 text-amber-600' : 'bg-slate-50 text-slate-400' }} flex items-center justify-center shrink-0"><x-icon name="mail" class="w-5 h-5"/></span>
        <div><p class="text-xl font-extrabold {{ $aksesStat['tanpaEmail'] ? 'text-amber-600' : 'text-slate-400' }}">{{ number_format($aksesStat['tanpaEmail'], 0, ',', '.') }}</p><p class="text-[11px] text-slate-500">Aktif tapi tanpa email{{ $aksesStat['tanpaEmail'] && $otpAktif ? ' (tidak bisa login)' : '' }}</p></div>
    </div>
</div>

{{-- Filter --}}
<div class="card p-4">
    <form method="GET" action="{{ route('manajemen-user.index') }}" class="flex flex-wrap gap-2.5 items-end" data-no-loader>
        <div class="flex-1 min-w-[220px]">
            <label class="label">Cari</label>
            <div class="relative">
                <x-icon name="search" class="w-4 h-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400"/>
                <input name="katakunci" value="{{ $filter['katakunci'] ?? '' }}" placeholder="Nama, NIP, username, email…" class="input !pl-10"/>
            </div>
        </div>
        <div>
            <label class="label">Peran (Mapping Akses)</label>
            <x-filter-check name="peran" label="Peran" :options="$roleList" :selected="$filter['peran'] ?? []"/>
        </div>
        <div>
            <label class="label">Unit Kerja</label>
            <x-filter-check name="unit" label="Unit Kerja" :options="$unitList" :selected="$filter['unit'] ?? []" searchable/>
        </div>
        <div>
            <label class="label">Status Akses</label>
            <select name="status" class="select !w-auto min-w-[140px]">
                <option value="semua">Semua</option>
                <option value="aktif" @selected(($filter['status'] ?? 'semua') === 'aktif')>Aktif — punya akses</option>
                <option value="nonaktif" @selected(($filter['status'] ?? '') === 'nonaktif')>Nonaktif — dicabut</option>
            </select>
        </div>
        <button class="btn-primary w-full sm:w-auto"><x-icon name="search" class="w-4 h-4"/> Terapkan</button>
        <a href="{{ route('manajemen-user.index') }}" class="btn-ghost w-full sm:w-auto justify-center">Reset</a>
    </form>
</div>

{{-- Tabel daftar akses --}}
@php($digitrans = \App\Services\DigitransService::aktif())
@if ($digitrans)
    <div class="card border-navy-200 bg-navy-50/60 mb-6">
        <div class="px-4 sm:px-5 py-4 flex flex-wrap items-center gap-3">
            <span class="w-10 h-10 rounded-xl bg-gradient-to-br from-navy-700 to-navy-500 text-white flex items-center justify-center shrink-0"><x-icon name="shield" class="w-5 h-5"/></span>
            <p class="flex-1 min-w-[220px] text-xs text-navy-800">
                <b class="text-sm">Login terpadu DigiTrans.</b>
                Username, email, password &amp; status akun pegawai bersumber dari DigiTrans (badge «DigiTrans») dan tersinkron otomatis tiap 15 menit. Di sini hanya <b>peran/level akses</b> yang diatur.
            </p>
        </div>
    </div>
@endif
<div class="card">
    @if ($akses->isNotEmpty())
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                <tr>
                    <th>Pegawai / Akun</th>
                    <th>Username Login</th>
                    <th>Email (Tujuan OTP)</th>
                    <th>Peran / Mapping Akses</th>
                    <th>Status Akses</th>
                    <th>Login Terakhir</th>
                    <th class="text-right">Aksi</th>
                </tr>
                </thead>
                <tbody>
                @foreach ($akses as $a)
                    @php($tanpaEmail = ! $a->email)
                    <tr class="{{ $a->is_aktif ? '' : 'opacity-60' }}">
                        <td>
                            <div class="flex items-center gap-3">
                                <span class="w-9 h-9 rounded-full bg-gradient-to-br from-navy-600 to-navy-800 text-white flex items-center justify-center text-xs font-bold shrink-0">{{ strtoupper(substr($a->name, 0, 1)) }}</span>
                                <div>
                                    <p class="font-semibold text-slate-800 text-[13px]">{{ $a->name }} @if($a->id === auth()->id())<span class="badge bg-gold-400/20 text-yellow-800 border-gold-400/50 ml-1">Anda</span>@endif @if($a->digitrans_id)<span class="badge bg-navy-50 text-navy-700 border-navy-200 ml-1" title="Identitas & login dari DigiTrans">DigiTrans</span>@endif</p>
                                    <p class="text-[11px] text-slate-400">{{ $a->nip ?: 'tanpa NIP' }} · {{ $a->unitKerja?->nama ?? 'tanpa unit' }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="text-xs font-semibold text-navy-800">{{ $a->username }}</td>
                        <td class="text-xs {{ $tanpaEmail ? 'text-amber-600 font-semibold' : 'text-slate-600' }}">
                            @if ($tanpaEmail)
                                <span class="inline-flex items-center gap-1" title="Wajib diisi bila OTP diaktifkan"><x-icon name="x" class="w-3.5 h-3.5"/> belum ada</span>
                            @else
                                <span class="truncate inline-block max-w-[180px]" title="{{ $a->email }}">{{ $a->email }}</span>
                            @endif
                        </td>
                        <td>
                            <span class="badge {{ $a->role === 'superadmin' ? 'bg-navy-800 text-white border-navy-800' : ($a->role === 'admin_unit' ? 'bg-navy-100 text-navy-800 border-navy-200' : ($a->role === 'atasan' ? 'bg-gold-400/20 text-yellow-800 border-gold-400/50' : 'bg-slate-100 text-slate-600 border-slate-200')) }}">
                                {{ $a->roleLabel() }}
                            </span>
                        </td>
                        <td>
                            @if ($a->is_aktif)
                                <span class="badge bg-emerald-50 text-emerald-700 border-emerald-200"><span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Aktif</span>
                            @else
                                <span class="badge bg-rose-50 text-rose-600 border-rose-200"><span class="w-1.5 h-1.5 rounded-full bg-rose-400"></span> Nonaktif</span>
                            @endif
                        </td>
                        <td class="text-xs text-slate-500">{{ $a->last_login_at?->timezone(config('app.timezone'))->translatedFormat('d M Y H:i') ?? '—' }}</td>
                        <td>
                            <div class="flex justify-end gap-1">
                                <button type="button" data-modal-target="modal-akses-{{ $a->id }}" class="btn-ghost !p-2" title="Mapping akses login">
                                    <x-icon name="key" class="w-4 h-4"/>
                                </button>
                                @if ($a->id !== auth()->id() && ! ($digitrans && $a->digitrans_id))
                                    <form action="{{ route('manajemen-user.toggle', $a) }}" method="POST"
                                          data-confirm="{{ $a->is_aktif ? "Cabut akses login {$a->name}? Akun tidak bisa masuk sampai diaktifkan kembali." : "Aktifkan kembali akses login {$a->name}?" }}">
                                        @csrf
                                        <button class="btn-ghost !p-2 {{ $a->is_aktif ? 'text-rose-500 hover:!bg-rose-50' : 'text-emerald-600 hover:!bg-emerald-50' }}"
                                                title="{{ $a->is_aktif ? 'Cabut akses login' : 'Aktifkan akses login' }}">
                                            <x-icon name="{{ $a->is_aktif ? 'lock' : 'unlock' }}" class="w-4 h-4"/>
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <div class="px-4 sm:px-5 py-4">{{ $akses->links() }}</div>
    @else
        <x-empty icon="key" teks="Tidak ada akun yang cocok" sub="Ubah kata kunci / filter, atau tambahkan pegawai baru lewat modul Pegawai."/>
    @endif
</div>

{{-- Modal mapping akses per user --}}
@foreach ($akses as $a)
    @php($diriSendiri = $a->id === auth()->id())
    @php($dariDigi = $digitrans && $a->digitrans_id)
    <div id="modal-akses-{{ $a->id }}" class="modal-root hidden">
        <div class="modal-backdrop" data-modal-close></div>
        <div class="modal-box">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p class="font-bold text-navy-900 text-lg">Mapping Akses Login</p>
                    <p class="text-xs text-slate-500 mt-1">{{ $a->name }} — {{ $a->nip ?: $a->username }}</p>
                </div>
                <button type="button" data-modal-close class="text-slate-400 hover:text-slate-600"><x-icon name="x" class="w-5 h-5"/></button>
            </div>
            <form action="{{ route('manajemen-user.update', $a) }}" method="POST" class="mt-4 space-y-4">
                @csrf
                <div class="grid sm:grid-cols-2 gap-4">
                    <div>
                        <label class="label">Username Login <span class="req">*</span></label>
                        <input name="username" required value="{{ old('username', $a->username) }}" class="input {{ $errors->has('username') ? 'input-error' : '' }} {{ $dariDigi ? 'bg-slate-50 text-slate-500' : '' }}" placeholder="cth: anggit / NIP" {{ $dariDigi ? 'readonly' : '' }}/>
                        @error('username')<p class="error-text">{{ $message }}</p>@enderror
                        @if ($dariDigi)<p class="help-text">Dikelola DigiTrans</p>@endif
                    </div>
                    <div>
                        <label class="label">Email <span class="text-[10px] font-normal text-slate-400">(tujuan kode OTP)</span></label>
                        <input type="email" name="email" value="{{ old('email', $a->email) }}" class="input {{ $errors->has('email') ? 'input-error' : '' }} {{ $dariDigi ? 'bg-slate-50 text-slate-500' : '' }}" placeholder="nama@kementrans.go.id" {{ $dariDigi ? 'readonly' : '' }}/>
                        @error('email')<p class="error-text">{{ $message }}</p>@enderror
                        @if ($dariDigi)<p class="help-text">Dikelola DigiTrans</p>@endif
                    </div>
                </div>
                <div class="grid sm:grid-cols-2 gap-4">
                    <div>
                        <label class="label">Password Baru</label>
                        <input type="password" name="password" autocomplete="new-password" placeholder="{{ $dariDigi ? 'dikelola DigiTrans' : 'kosongkan bila tidak diubah' }}" class="input {{ $errors->has('password') ? 'input-error' : '' }} {{ $dariDigi ? 'bg-slate-50 text-slate-500' : '' }}" {{ $dariDigi ? 'readonly' : '' }}/>
                        @error('password')<p class="error-text">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="label">Peran / Level Akses <span class="req">*</span></label>
                        @if ($diriSendiri)
                            <input type="hidden" name="role" value="{{ $a->role }}"/>
                        @endif
                        <select name="role" class="select" @disabled($diriSendiri)>
                            @foreach ($roleList as $nilai => $label)
                                <option value="{{ $nilai }}" @selected(old('role', $a->role) === $nilai)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @if ($diriSendiri)
                            <p class="help-text">Peran akun sendiri tidak dapat diubah (anti-lockout).</p>
                        @endif
                    </div>
                </div>
                <label class="flex items-start gap-3 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 {{ $dariDigi ? '' : 'cursor-pointer' }}">
                    @if ($diriSendiri || $dariDigi)
                        <input type="hidden" name="is_aktif" value="{{ $diriSendiri ? '1' : ($a->is_aktif ? '1' : '0') }}"/>
                    @endif
                    <input type="checkbox" name="is_aktif" value="1" class="mt-0.5 rounded border-slate-300 text-emerald-600 focus:ring-emerald-400"
                           @checked(old('is_aktif', $a->is_aktif)) @disabled($diriSendiri || $dariDigi)/>
                    <span class="text-sm text-slate-600">
                        <b class="text-slate-800">Punya akses login</b> — akun dapat masuk ke aplikasi.<br/>
                        @if ($dariDigi)
                            <span class="text-xs text-navy-600">Mengikuti status akun di DigiTrans (sinkron otomatis tiap 15 menit).</span>
                        @else
                            <span class="text-xs text-slate-400">Dicabut = akun nonaktif, tidak bisa login, tetapi data &amp; riwayat tetap tersimpan.</span>
                        @endif
                    </span>
                </label>
                <button class="btn-primary w-full"><x-icon name="check" class="w-4 h-4"/> Simpan Mapping Akses</button>
            </form>
        </div>
    </div>
@endforeach
@endsection
