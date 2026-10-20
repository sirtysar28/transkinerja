<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Verifikasi OTP — TransKinerja</title>
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
        .otp-box {
            width: 48px; height: 56px;
            border: 2px solid #cbd5e1; border-radius: 12px;
            font-size: 24px; font-weight: 800; text-align: center;
            color: #0E2A37; background: #fff;
            transition: border-color .15s, box-shadow .15s;
        }
        .otp-box:focus { outline: none; border-color: #163D4F; box-shadow: 0 0 0 3px rgba(22,61,79,.15); }
        .otp-box.terisi { border-color: #163D4F; background: #f8fafc; }
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
                    Satu langkah<br/>lagi untuk masuk.
                </h1>
                <p class="mt-4 text-navy-100/85 text-sm md:text-[15px] leading-relaxed">
                    Username/NIP dan password Anda sudah benar. Untuk keamanan tambahan,
                    masukkan kode OTP yang baru saja kami kirim ke email Anda.
                </p>
                <ul class="mt-6 space-y-2.5 text-[13px] text-navy-100/75">
                    <li class="flex items-center gap-2.5"><span class="w-1.5 h-1.5 rounded-full bg-gold-400"></span> Kode hanya berlaku sekali pakai &amp; berbatas waktu</li>
                    <li class="flex items-center gap-2.5"><span class="w-1.5 h-1.5 rounded-full bg-gold-400"></span> Jangan bagikan kode kepada siapa pun</li>
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
                    <div class="flex items-center gap-3 mb-1">
                        <span class="w-11 h-11 rounded-2xl bg-navy-800 text-white flex items-center justify-center shrink-0">
                            <x-icon name="shield" class="w-6 h-6"/>
                        </span>
                        <div>
                            <h2 class="text-xl font-extrabold text-navy-900 leading-tight">Verifikasi OTP</h2>
                        </div>
                    </div>
                    <p class="text-sm text-slate-500 mt-2 mb-5">
                        Halo <b class="text-slate-700">{{ $user->name }}</b>, kode {{ $panjang }} digit telah dikirim ke
                        <b class="text-slate-700">{{ $email }}</b>.
                    </p>

                    @if (session('sukses'))
                        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700 mb-4">
                            {{ session('sukses') }}
                        </div>
                    @endif

                    <form action="{{ route('login.otp.verify') }}" method="POST" class="space-y-5" id="form-otp">
                        @csrf
                        @if ($errors->any())
                            <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                                <p class="font-semibold mb-1">Verifikasi gagal</p>
                                <ul class="list-disc list-inside space-y-0.5 text-[13px]">
                                    @foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach
                                </ul>
                            </div>
                        @endif

                        {{-- Kotak-kotak digit OTP --}}
                        <div>
                            <div class="flex justify-center gap-2" id="otp-grup">
                                @for ($i = 0; $i < $panjang; $i++)
                                    <input class="otp-box" type="text" inputmode="numeric" autocomplete="{{ $i === 0 ? 'one-time-code' : 'off' }}"
                                           maxlength="1" @if ($i === 0) autofocus @endif data-otp-index="{{ $i }}"/>
                                @endfor
                            </div>
                            {{-- nilai digabung ke satu field tersembunyi agar mudah diverifikasi server --}}
                            <input type="hidden" name="kode" id="otp-kode" value="{{ old('kode') }}"/>
                            <p class="help-text text-center mt-3">
                                Berlaku <span id="otp-hitung" class="font-bold text-navy-800">--:--</span>
                                <span id="otp-habis" class="hidden text-rose-600 font-semibold">— kedaluwarsa, minta kode baru.</span>
                            </p>
                        </div>

                        <button type="submit" class="btn-primary w-full !py-3" id="btn-verifikasi">
                            <x-icon name="check-badge" class="w-4 h-4"/> Verifikasi &amp; Masuk
                        </button>
                    </form>

                    <div class="flex items-center justify-between text-[13px] mt-5 pt-4 border-t border-slate-100">
                        <form action="{{ route('login.otp.resend') }}" method="POST">
                            @csrf
                            <button type="submit" id="btn-kirim-ulang" class="font-semibold text-navy-700 hover:text-navy-900 disabled:text-slate-400 disabled:cursor-not-allowed transition">
                                Kirim ulang kode
                            </button>
                        </form>
                        <form action="{{ route('login.otp.batalkan') }}" method="POST">
                            @csrf
                            <button type="submit" class="text-slate-400 hover:text-rose-500 transition">Batalkan</button>
                        </form>
                    </div>
                </div>

                <p class="text-center text-[11px] text-slate-400 mt-6">
                    Tidak menerima email? Periksa folder spam, atau hubungi administrator aplikasi.
                </p>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const kotak = Array.from(document.querySelectorAll('[data-otp-index]'));
            const gabung = document.getElementById('otp-kode');
            const hitung = document.getElementById('otp-hitung');
            const habis = document.getElementById('otp-habis');
            const btnKirim = document.getElementById('btn-kirim-ulang');

            // ---- Gabungkan digit & auto-fokus/auto-kirim ----
            const segarkan = () => {
                const nilai = kotak.map(k => k.value.replace(/\D/g, '')).join('');
                gabung.value = nilai;
                kotak.forEach(k => k.classList.toggle('terisi', k.value !== ''));
                return nilai;
            };
            kotak.forEach((k, i) => {
                k.addEventListener('input', () => {
                    k.value = k.value.replace(/\D/g, '').slice(-1);
                    if (k.value && i < kotak.length - 1) kotak[i + 1].focus();
                    if (segarkan().length === kotak.length) document.getElementById('form-otp').submit();
                });
                k.addEventListener('keydown', (e) => {
                    if (e.key === 'Backspace' && !k.value && i > 0) kotak[i - 1].focus();
                    if (e.key === 'ArrowLeft' && i > 0) kotak[i - 1].focus();
                    if (e.key === 'ArrowRight' && i < kotak.length - 1) kotak[i + 1].focus();
                });
                k.addEventListener('paste', (e) => {
                    e.preventDefault();
                    const teks = (e.clipboardData.getData('text') || '').replace(/\D/g, '');
                    teks.split('').slice(0, kotak.length).forEach((d, j) => { if (kotak[j]) kotak[j].value = d; });
                    segarkan();
                });
            });
            // Isi ulang nilai lama bila ada error validasi
            if (gabung.value) {
                gabung.value.split('').slice(0, kotak.length).forEach((d, j) => { if (kotak[j]) kotak[j].value = d; });
                segarkan();
            }

            // ---- Hitung mundur masa berlaku kode ----
            let sisa = {{ max(0, (int) ($kedaluwarsa ?? 0) - now()->timestamp) }};
            const detak = setInterval(() => {
                if (sisa <= 0) {
                    clearInterval(detak);
                    hitung.textContent = '00:00';
                    habis.classList.remove('hidden');
                    document.getElementById('btn-verifikasi').classList.add('opacity-50');
                    return;
                }
                sisa--;
                const m = String(Math.floor(sisa / 60)).padStart(2, '0');
                const d = String(sisa % 60).padStart(2, '0');
                hitung.textContent = m + ':' + d;
            }, 1000);

            // ---- Cooldown kirim ulang 60 detik ----
            const jeda = () => {
                let tunggu = 60;
                const awal = btnKirim.textContent;
                btnKirim.disabled = true;
                const iv = setInterval(() => {
                    if (tunggu <= 0) {
                        clearInterval(iv);
                        btnKirim.disabled = false;
                        btnKirim.textContent = awal;
                        return;
                    }
                    btnKirim.textContent = `Kirim ulang (${tunggu--} dtk)`;
                }, 1000);
            };
            jeda();
        });
    </script>
</body>
</html>
