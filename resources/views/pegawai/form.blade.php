@extends('layouts.app')
@section('title', $pegawai->exists ? 'Ubah Pegawai' : 'Tambah Pegawai')

@section('content')
<div class="flex items-end justify-between gap-3">
    <div>
        <nav class="text-xs text-slate-400 mb-1"><a href="{{ route('pegawai.index') }}" class="hover:text-navy-600">Pegawai</a> → {{ $pegawai->exists ? 'Ubah' : 'Tambah' }}</nav>
        <h1 class="page-title">{{ $pegawai->exists ? 'Ubah Data Pegawai' : 'Tambah Pegawai Baru' }}</h1>
        <p class="page-sub">Data identitas ASN digunakan pada Formulir Laporan Bulanan.</p>
    </div>
    <a href="{{ route('pegawai.index') }}" class="btn-secondary btn-sm"><x-icon name="chevron-left" class="w-4 h-4"/> Kembali</a>
</div>

<form method="POST" action="{{ $pegawai->exists ? route('pegawai.update', $pegawai) : route('pegawai.store') }}"
      class="grid lg:grid-cols-3 gap-6 items-start">
    @csrf
    @if ($pegawai->exists) @method('PUT') @endif

    {{-- =================== KOLOM UTAMA =================== --}}
    <div class="card p-6 lg:col-span-2 space-y-6">

        {{-- ---------- Bagian 1 · Identitas ---------- --}}
        <div>
            <div class="flex items-center gap-2.5 mb-4">
                <span class="w-7 h-7 rounded-lg bg-navy-50 border border-navy-100 text-navy-600 flex items-center justify-center shrink-0"><x-icon name="user" class="w-4 h-4"/></span>
                <p class="text-[11px] font-bold uppercase tracking-widest text-navy-600">Identitas Pegawai</p>
                <span class="flex-1 h-px bg-slate-100"></span>
            </div>
            <div class="grid sm:grid-cols-2 gap-5">
                @if ($pegawai->digitrans_id && \App\Services\DigitransService::aktif())
                    <div class="sm:col-span-2 rounded-xl border border-navy-100 bg-navy-50/70 px-4 py-3 text-xs text-navy-800">
                        <b>Nama &amp; NIP terkunci</b> — identitas pegawai bersumber dari DigiTrans (diperbarui otomatis saat sinkron). Atribut kinerja di bawah tetap dapat diubah.
                    </div>
                @endif
                <div class="sm:col-span-2">
                    <label class="label">Nama Lengkap (dengan gelar) <span class="req">*</span></label>
                    <input name="name" required value="{{ old('name', $pegawai->name) }}" placeholder="cth: Anggit Tinarbuka AW, S.K.M, S.E" class="input {{ $errors->has('name') ? 'input-error' : '' }} {{ $pegawai->digitrans_id ? 'bg-slate-50 text-slate-500' : '' }}" {{ ($pegawai->digitrans_id && \App\Services\DigitransService::aktif()) ? 'readonly' : '' }}/>
                    @error('name')<p class="error-text">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="label">NIP (18 digit)</label>
                    <input name="nip" value="{{ old('nip', $pegawai->nip) }}" placeholder="199010202018031001" class="input {{ $errors->has('nip') ? 'input-error' : '' }} {{ $pegawai->digitrans_id ? 'bg-slate-50 text-slate-500' : '' }}" {{ ($pegawai->digitrans_id && \App\Services\DigitransService::aktif()) ? 'readonly' : '' }}/>
                    @error('nip')<p class="error-text">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="label">Status Kepegawaian</label>
                    <select name="status_pegawai" class="select">
                        <option value="">— pilih —</option>
                        @foreach (\App\Models\User::STATUS_PEGAWAI as $st)
                            <option value="{{ $st }}" @selected(strcasecmp(trim((string) old('status_pegawai', $pegawai->status_pegawai)), $st) === 0)>{{ $st }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="label">Pangkat / Gol. Ruang</label>
                    <input name="pangkat_golongan" value="{{ old('pangkat_golongan', $pegawai->pangkat_golongan) }}" placeholder="cth: Pembina / (IV.a)" class="input"/>
                </div>
                <div>
                    <label class="label">Jenis Kelamin</label>
                    <select name="jenis_kelamin" class="select">
                        <option value="">— pilih —</option>
                        @foreach (['Laki-laki', 'Perempuan'] as $jk)
                            <option value="{{ $jk }}" @selected(\App\Models\User::normJenisKelamin(old('jenis_kelamin', $pegawai->jenis_kelamin)) === $jk)>{{ $jk }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="label">Tempat Lahir</label>
                    <input name="tempat_lahir" value="{{ old('tempat_lahir', $pegawai->tempat_lahir) }}" class="input"/>
                </div>
                <div>
                    <label class="label">Tanggal Lahir</label>
                    <input type="date" name="tanggal_lahir" value="{{ old('tanggal_lahir', $pegawai->tanggal_lahir?->format('Y-m-d')) }}" class="input"/>
                </div>
                <div>
                    <label class="label">Agama</label>
                    <select name="agama" class="select">
                        <option value="">— pilih —</option>
                        @foreach (['Islam', 'Kristen', 'Katolik', 'Hindu', 'Buddha', 'Konghucu'] as $ag)
                            <option value="{{ $ag }}" @selected(strcasecmp(trim((string) old('agama', $pegawai->agama)), $ag) === 0)>{{ $ag }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="label">No. Telepon</label>
                    <input name="no_telp" value="{{ old('no_telp', $pegawai->no_telp) }}" class="input" placeholder="08xxxxxxxxxx"/>
                </div>
                <div class="sm:col-span-2">
                    <label class="label">Alamat</label>
                    <input name="alamat" value="{{ old('alamat', $pegawai->alamat) }}" class="input" placeholder="Alamat domisili"/>
                </div>
            </div>
        </div>

        <hr class="border-slate-100"/>

        {{-- ---------- Bagian 2 · Kedinasan ---------- --}}
        <div>
            <div class="flex items-center gap-2.5 mb-4">
                <span class="w-7 h-7 rounded-lg bg-navy-50 border border-navy-100 text-navy-600 flex items-center justify-center shrink-0"><x-icon name="building" class="w-4 h-4"/></span>
                <p class="text-[11px] font-bold uppercase tracking-widest text-navy-600">Jabatan &amp; Unit Kerja</p>
                <span class="flex-1 h-px bg-slate-100"></span>
            </div>
            <div class="grid sm:grid-cols-2 gap-5">
                <div class="sm:col-span-2">
                    <label class="label">Jabatan</label>
                    <input name="jabatan" value="{{ old('jabatan', $pegawai->jabatan) }}" placeholder="cth: Pranata Hubungan Masyarakat Ahli Madya" class="input"/>
                </div>
                <div class="sm:col-span-2">
                    <label class="label">Unit Kerja <span class="req">*</span></label>
                    <select name="unit_kerja_id" required class="select {{ $errors->has('unit_kerja_id') ? 'input-error' : '' }}">
                        <option value="">— Pilih unit —</option>
                        @foreach ($unitList as $u)
                            <option value="{{ $u->id }}" @selected(old('unit_kerja_id', $pegawai->unit_kerja_id) == $u->id)>{{ $u->nama }}</option>
                        @endforeach
                    </select>
                    @error('unit_kerja_id')<p class="error-text">{{ $message }}</p>@enderror
                </div>
                <div class="sm:col-span-2">
                    <label class="label">Atasan / Penilai</label>
                    <select name="atasan_id" class="select">
                        <option value="">— Tidak ada —</option>
                        @foreach ($atasanList as $a)
                            <option value="{{ $a->id }}" @selected(old('atasan_id', $pegawai->atasan_id) == $a->id)>{{ $a->name }} ({{ $a->roleLabel() }})</option>
                        @endforeach
                    </select>
                    <p class="help-text">Laporan pegawai akan diajukan ke atasan ini.</p>
                </div>
            </div>
        </div>

        <hr class="border-slate-100"/>

        {{-- ---------- Bagian 3 · Kepegawaian lanjutan ---------- --}}
        <div>
            <div class="flex items-center gap-2.5 mb-4">
                <span class="w-7 h-7 rounded-lg bg-navy-50 border border-navy-100 text-navy-600 flex items-center justify-center shrink-0"><x-icon name="report" class="w-4 h-4"/></span>
                <p class="text-[11px] font-bold uppercase tracking-widest text-navy-600">Masa Kerja, Pendidikan &amp; Kemampuan</p>
                <span class="flex-1 h-px bg-slate-100"></span>
            </div>
            <div class="grid sm:grid-cols-3 gap-5">
                <div>
                    <label class="label">TMT Jabatan</label>
                    <input type="date" name="tmt_jabatan" value="{{ old('tmt_jabatan', $pegawai->tmt_jabatan?->format('Y-m-d')) }}" class="input"/>
                </div>
                <div>
                    <label class="label">Kenaikan Pangkat Terakhir</label>
                    <input type="date" name="kenaikan_pangkat" value="{{ old('kenaikan_pangkat', $pegawai->kenaikan_pangkat?->format('Y-m-d')) }}" class="input"/>
                </div>
                <div>
                    <label class="label">Batas Usia Pensiun (BUP)</label>
                    <input type="date" name="batas_usia_pensiun" value="{{ old('batas_usia_pensiun', $pegawai->batas_usia_pensiun?->format('Y-m-d')) }}" class="input"/>
                </div>
            </div>
            <div class="grid sm:grid-cols-2 gap-5 mt-5">
                <div>
                    <label class="label">Pendidikan Terakhir</label>
                    <input name="pendidikan_terakhir" value="{{ old('pendidikan_terakhir', $pegawai->pendidikan_terakhir) }}" placeholder="cth: S.1 Manajemen" class="input"/>
                </div>
                <div>
                    <label class="label">Level Pendidikan</label>
                    <select name="level_pendidikan" class="select">
                        <option value="">— pilih —</option>
                        @foreach (\App\Models\User::LEVEL_PENDIDIKAN as $lp)
                            <option value="{{ $lp }}" @selected(\App\Models\User::normLevelPendidikan(old('level_pendidikan', $pegawai->level_pendidikan)) === $lp)>{{ $lp }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="label">Kemampuan Renang</label>
                    <select name="kemampuan_renang" class="select">
                        <option value="">— pilih —</option>
                        @foreach (\App\Models\User::KEMAMPUAN as $km)
                            <option value="{{ $km }}" @selected(\App\Models\User::normKemampuan(old('kemampuan_renang', $pegawai->kemampuan_renang)) === $km)>{{ $km }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="label">Kemampuan Bahasa Inggris</label>
                    <select name="kemampuan_inggris" class="select">
                        <option value="">— pilih —</option>
                        @foreach (\App\Models\User::KEMAMPUAN as $km)
                            <option value="{{ $km }}" @selected(\App\Models\User::normKemampuan(old('kemampuan_inggris', $pegawai->kemampuan_inggris)) === $km)>{{ $km }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <p class="help-text">BUP dihitung otomatis saat import: 60 th untuk Eselon I/II &amp; Fungsional Madya, selain itu 58 th.</p>
        </div>

        <hr class="border-slate-100"/>

        {{-- ---------- Bagian 4 · Hak akses ---------- --}}
        <div>
            <div class="flex items-center gap-2.5 mb-4">
                <span class="w-7 h-7 rounded-lg bg-navy-50 border border-navy-100 text-navy-600 flex items-center justify-center shrink-0"><x-icon name="key" class="w-4 h-4"/></span>
                <p class="text-[11px] font-bold uppercase tracking-widest text-navy-600">Hak Akses / Peran <span class="req">*</span></p>
                <span class="flex-1 h-px bg-slate-100"></span>
            </div>
            <div class="grid sm:grid-cols-2 gap-3">
                @foreach ($roleList as $rk => $rl)
                    <label class="flex items-start gap-3 rounded-xl border border-slate-200 px-4 py-3 cursor-pointer hover:border-navy-300 hover:bg-navy-50/40 transition has-[:checked]:border-navy-500 has-[:checked]:bg-navy-50">
                        <input type="radio" name="role" value="{{ $rk }}" @checked(old('role', $pegawai->role ?? 'pegawai') === $rk) class="mt-0.5 w-4 h-4 border-slate-300 text-navy-600 focus:ring-navy-400"/>
                        <span>
                            <span class="block text-[13px] font-bold text-navy-800">{{ $rl }}</span>
                            <span class="block text-[11px] text-slate-400">Level {{ \App\Models\User::ROLE_LEVEL[$rk] }}</span>
                        </span>
                    </label>
                @endforeach
            </div>
            @error('role')<p class="error-text">{{ $message }}</p>@enderror
        </div>
    </div>

    {{-- =================== PANEL SAMPING =================== --}}
    <div class="card p-6 space-y-5 lg:sticky lg:top-24">
        <div class="flex items-center gap-3">
            <span class="w-11 h-11 rounded-full bg-gradient-to-br from-navy-600 to-navy-800 text-white flex items-center justify-center text-sm font-bold shrink-0">{{ strtoupper(substr(trim((string) old('name', $pegawai->name)) ?: '?', 0, 1)) }}</span>
            <div class="min-w-0">
                <p class="text-sm font-bold text-navy-900 truncate">{{ \Illuminate\Support\Str::before(trim((string) old('name', $pegawai->name)) ?: 'Pegawai Baru', ',') }}</p>
                <p class="text-[11px] text-slate-400 truncate">{{ old('nip', $pegawai->nip) ?: 'NIP belum diisi' }}</p>
            </div>
        </div>
        <hr class="border-slate-100"/>
        <p class="card-title">Akun Masuk</p>
        @if ($pegawai->digitrans_id && \App\Services\DigitransService::aktif())
            <div class="rounded-xl border border-navy-100 bg-navy-50/70 px-4 py-3 mb-4 text-xs text-navy-800">
                <b>Terhubung DigiTrans</b> — username, email &amp; password akun ini dikelola terpusat di DigiTrans dan tersinkron otomatis. Login memakai email/NIP + password DigiTrans.
            </div>
        @endif
        <div>
            <label class="label">Username <span class="req">*</span></label>
            <input name="username" required value="{{ old('username', $pegawai->username) }}" placeholder="cth: anggit" class="input {{ $errors->has('username') ? 'input-error' : '' }}" {{ ($pegawai->digitrans_id && \App\Services\DigitransService::aktif()) ? 'readonly' : '' }}/>
            @error('username')<p class="error-text">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="label">Email</label>
            <input type="email" name="email" value="{{ old('email', $pegawai->email) }}" placeholder="nama@transmigrasi.go.id" class="input {{ $errors->has('email') ? 'input-error' : '' }}" {{ ($pegawai->digitrans_id && \App\Services\DigitransService::aktif()) ? 'readonly' : '' }}/>
            @error('email')<p class="error-text">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="label">{{ $pegawai->exists ? 'Password Baru (kosongkan bila tidak diubah)' : 'Password Awal' }}</label>
            <input type="password" name="password" placeholder="{{ ($pegawai->digitrans_id && \App\Services\DigitransService::aktif()) ? 'dikelola DigiTrans' : ($pegawai->exists ? '••••••••' : 'default: password') }}" class="input {{ $errors->has('password') ? 'input-error' : '' }}" {{ ($pegawai->digitrans_id && \App\Services\DigitransService::aktif()) ? 'readonly' : '' }}/>
            @error('password')<p class="error-text">{{ $message }}</p>@enderror
            @if (! $pegawai->exists)<p class="help-text">Jika dikosongkan, default: <b>password</b> — wajib diganti oleh pegawai.</p>@endif
        </div>

        <hr class="border-slate-100"/>
        <button class="btn-primary w-full"><x-icon name="check" class="w-4 h-4"/> {{ $pegawai->exists ? 'Simpan Perubahan' : 'Tambah Pegawai' }}</button>
        <a href="{{ route('pegawai.index') }}" class="btn-ghost w-full">Batal</a>
    </div>
</form>
@endsection
