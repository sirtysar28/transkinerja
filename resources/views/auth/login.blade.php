<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Masuk — TransKinerja</title>
    <link rel="icon" type="image/png" href="{{ asset('images/favicon.png') }}"/>
    <link rel="preconnect" href="https://fonts.googleapis.com"/>
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        body { background: #f1f5f9; }
        .brand-pattern {
            background:
                radial-gradient(ellipse 80% 60% at 80% -10%, rgba(232,161,60,.20), transparent),
                radial-gradient(ellipse 60% 50% at 0% 110%, rgba(43,95,120,.40), transparent),
                linear-gradient(160deg, #0E2A37 0%, #123446 55%, #081C26 100%);
        }
    </style>
</head>
<body class="min-h-screen">
    <div class="min-h-screen flex flex-col lg:flex-row">

        {{-- Panel identitas (kiri) --}}
        <div class="brand-pattern relative lg:w-[46%] xl:w-[42%] px-8 md:px-14 py-12 lg:py-0 flex flex-col justify-between text-white overflow-hidden">
            <div class="absolute inset-0 opacity-[0.04] pointer-events-none"
                 style="background-image:url('{{ asset('images/logo.png') }}');background-size:78%;background-position:120% 90%;background-repeat:no-repeat;"></div>

            <div class="relative flex items-center gap-4">
                <img src="{{ asset('images/logo.png') }}" alt="Logo Kementerian Transmigrasi" class="w-14 h-14 md:w-16 md:h-16 rounded-2xl bg-white/95 p-1 shadow-lg"/>
                <div>
                    <p class="font-bold text-lg md:text-xl tracking-wide">TransKinerja</p>
                    <p class="text-navy-200/90 text-[11px] md:text-xs leading-snug">Kementerian Transmigrasi<br/>Republik Indonesia</p>
                </div>
            </div>

            <div class="relative mt-10 lg:mt-0 max-w-md">
                <h1 class="text-2xl md:text-[2rem] font-extrabold leading-tight">
                    Kinerja tercatat,<br/>pelayanan meningkat.
                </h1>
                <p class="mt-4 text-navy-100/85 text-sm md:text-[15px] leading-relaxed">
                    Satu platform untuk pencatatan aktivitas, monitoring, dan pelaporan
                    kinerja ASN di lingkungan Kementerian Transmigrasi Republik Indonesia.
                </p>
                <ul class="mt-6 space-y-2.5 text-[13px] text-navy-100/75">
                    <li class="flex items-center gap-2.5"><span class="w-1.5 h-1.5 rounded-full bg-gold-400"></span> Log Book aktivitas harian dengan alur persetujuan</li>
                    <li class="flex items-center gap-2.5"><span class="w-1.5 h-1.5 rounded-full bg-gold-400"></span> Monitoring jam kerja & rekap kinerja bulanan</li>
                    <li class="flex items-center gap-2.5"><span class="w-1.5 h-1.5 rounded-full bg-gold-400"></span> Laporan konsisten dengan formulir Lapkin ASN</li>
                </ul>
            </div>

            <p class="relative mt-10 lg:mt-0 text-[11px] text-navy-300/70">
                &copy; {{ date('Y') }} Kementerian Transmigrasi Republik Indonesia — Semua hak dilindungi.
            </p>
        </div>

        {{-- Panel form (kanan) --}}
        <div class="flex-1 flex items-center justify-center p-6 md:p-10">
            <div class="w-full max-w-md">
                <div class="lg:hidden flex items-center justify-center gap-3 mb-8">
                    <img src="{{ asset('images/logo.png') }}" alt="Logo" class="w-12 h-12 rounded-xl bg-white p-1 shadow"/>
                    <div>
                        <p class="font-extrabold text-navy-900">TransKinerja</p>
                        <p class="text-[10px] text-slate-500">Kementerian Transmigrasi RI</p>
                    </div>
                </div>

                <div class="card p-7 md:p-9">
                    <h2 class="text-xl font-extrabold text-navy-900">Masuk ke akun Anda</h2>
                    <p class="text-sm text-slate-500 mt-1 mb-6">Gunakan akun yang telah didaftarkan oleh administrator.</p>

                    <form action="{{ route('login.attempt') }}" method="POST" class="space-y-5">
                        @csrf
                        @if ($errors->any())
                            <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                                <p class="font-semibold mb-1">Gagal masuk</p>
                                <ul class="list-disc list-inside space-y-0.5 text-[13px]">
                                    @foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach
                                </ul>
                            </div>
                        @endif

                        {{-- Username / NIP --}}
                        <div>
                            <label for="login" class="label">Username atau NIP <span class="req">*</span></label>
                            <input id="login" name="login" type="text" required autofocus value="{{ old('login') }}"
                                   placeholder="cth: anggit atau 199010202018031001"
                                   class="input {{ $errors->has('login') ? 'input-error' : '' }}"/>
                        </div>

                        {{-- Password + intip --}}
                        <div>
                            <label for="password" class="label">Password <span class="req">*</span></label>
                            <div class="relative">
                                <input id="password" name="password" type="password" required
                                       placeholder="••••••••" class="input pr-12 {{ $errors->has('password') ? 'input-error' : '' }}"/>
                                <button type="button" data-password-toggle="password"
                                        class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-navy-600 transition"
                                        aria-label="Tampilkan password" title="Tampilkan password">
                                    <x-icon name="eye" class="icon-eye-open w-5 h-5"/>
                                    <x-icon name="eye-closed" class="icon-eye-closed w-5 h-5 hidden"/>
                                </button>
                            </div>
                        </div>

                        {{-- CAPTCHA --}}
                        <div>
                            <label for="captcha" class="label">CAPTCHA — masukkan 5 karakter <span class="req">*</span></label>
                            <div class="flex gap-2.5">
                                <img id="img-captcha" src="{{ route('captcha') }}" data-src="{{ route('captcha') }}"
                                     alt="CAPTCHA" width="220" height="64"
                                     class="rounded-xl border border-slate-300 bg-white shadow-sm cursor-pointer select-none shrink-0"
                                     title="Klik gambar untuk mengganti kode" draggable="false"/>
                                <button type="button" id="btn-captcha-ganti"
                                        class="btn-secondary btn-sm h-[64px] !rounded-xl flex-col !gap-0.5 text-[10px] leading-none shrink-0">
                                    <x-icon name="refresh" class="w-4 h-4"/>
                                    Ganti
                                </button>
                            </div>
                            <input id="captcha" name="captcha" type="text" required maxlength="5" autocomplete="off"
                                   placeholder="5 karakter" value=""
                                   class="input mt-2.5 tracking-[0.35em] font-bold uppercase {{ $errors->has('captcha') ? 'input-error' : '' }}"/>
                            <p class="help-text">Huruf besar/kecil tidak berpengaruh. Kode berlaku 10 menit & sekali pakai.</p>
                        </div>

                        <div class="flex items-center justify-between text-[13px]">
                            <label class="flex items-center gap-2 text-slate-600 select-none cursor-pointer">
                                <input type="checkbox" name="ingat" value="1" class="rounded border-slate-300 text-navy-600 focus:ring-navy-400"/>
                                Ingat saya
                            </label>
                            <span class="text-slate-400" title="Hubungi admin unit / Biro SDM untuk pemulihan akun">Lupa password?</span>
                        </div>

                        <button type="submit" class="btn-primary w-full !py-3">
                            <x-icon name="login" class="w-4 h-4"/> Masuk
                        </button>

                        <p class="text-center text-[11px] text-slate-400">Akses hanya untuk pengguna berwenang. Percobaan login dibatasi &amp; tercatat.</p>
                    </form>
                </div>

                <p class="text-center text-[11px] text-slate-400 mt-6">
                    Akun demo: <span class="font-semibold text-slate-500">superadmin / atasan / adminbppmt / anggit</span> — sandi: <span class="font-semibold text-slate-500">password</span>
                </p>
            </div>
        </div>
    </div>
</body>
</html>
