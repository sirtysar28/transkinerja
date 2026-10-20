<div class="grid lg:grid-cols-3 gap-6 items-start">
    <form action="{{ route('pengaturan.otp.store') }}" method="POST" class="card p-6 lg:col-span-2 space-y-5">
        @csrf
        <div class="card-header !px-0 !pt-0">
            <p class="card-title">Verifikasi Dua Langkah (OTP) Saat Login</p>
            <p class="text-xs text-slate-400">Bila <b>AKTIF</b>, setelah username/NIP &amp; password benar, user tetap harus memasukkan kode OTP yang dikirim ke <b>email terdaftar</b> — sesi baru dibuat hanya setelah kode benar.</p>
        </div>

        {{-- Saklar ON/OFF --}}
        <label class="flex items-start gap-3 rounded-xl border {{ old('otp_aktif', $otp['otp_aktif']) ? 'border-emerald-200 bg-emerald-50/60' : 'border-slate-200 bg-slate-50' }} px-4 py-4 cursor-pointer">
            <input type="checkbox" name="otp_aktif" value="1" class="mt-0.5 rounded border-slate-300 text-emerald-600 focus:ring-emerald-400"
                   @checked(old('otp_aktif', $otp['otp_aktif']))/>
            <span class="text-sm text-slate-600">
                <b class="text-slate-800">Aktifkan verifikasi OTP saat login</b>
                <span class="ml-2 inline-flex items-center gap-1 badge {{ old('otp_aktif', $otp['otp_aktif']) ? 'bg-emerald-100 text-emerald-700 border-emerald-300' : 'bg-slate-100 text-slate-500 border-slate-200' }}">
                    {{ old('otp_aktif', $otp['otp_aktif']) ? 'ON' : 'OFF' }}
                </span><br/>
                Status saat ini: <b>{{ $otp['otp_aktif'] ? 'AKTIF — semua login baru wajib verifikasi OTP' : 'NONAKTIF — login langsung setelah password benar' }}</b>.
            </span>
        </label>

        <div class="grid sm:grid-cols-3 gap-5">
            <div>
                <label class="label">Panjang Kode (digit) <span class="req">*</span></label>
                <select name="otp_panjang" class="select">
                    @foreach (range(4, 8) as $n)
                        <option value="{{ $n }}" @selected(old('otp_panjang', $otp['otp_panjang']) == $n)>{{ $n }} digit</option>
                    @endforeach
                </select>
                @error('otp_panjang')<p class="error-text">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="label">Masa Berlaku (menit) <span class="req">*</span></label>
                <input type="number" name="otp_masa_berlaku" required min="1" max="60"
                       value="{{ old('otp_masa_berlaku', $otp['otp_masa_berlaku']) }}" class="input {{ $errors->has('otp_masa_berlaku') ? 'input-error' : '' }}"/>
                @error('otp_masa_berlaku')<p class="error-text">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="label">Maks. Percobaan <span class="req">*</span></label>
                <input type="number" name="otp_maks_percobaan" required min="3" max="10"
                       value="{{ old('otp_maks_percobaan', $otp['otp_maks_percobaan']) }}" class="input {{ $errors->has('otp_maks_percobaan') ? 'input-error' : '' }}"/>
                @error('otp_maks_percobaan')<p class="error-text">{{ $message }}</p>@enderror
            </div>
        </div>

        {{-- Konfirmasi bila ada akun aktif tanpa email --}}
        @if (($otp['tanpaEmail'] ?? 0) > 0)
            <label class="flex items-start gap-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 cursor-pointer">
                <input type="checkbox" name="konfirmasi_tanpa_email" value="1" class="mt-0.5 rounded border-amber-300 text-amber-600 focus:ring-amber-400"/>
                <span class="text-xs text-amber-800">
                    <b>Saya memahami:</b> ada <b>{{ $otp['tanpaEmail'] }} akun aktif tanpa email</b> (lihat menu <b>Manajemen User</b>).
                    Bila OTP dinyalakan, akun tersebut <b>tidak dapat login</b> sampai emailnya dilengkapi.
                </span>
            </label>
        @endif

        <button class="btn-primary"><x-icon name="check" class="w-4 h-4"/> Simpan Pengaturan OTP</button>
    </form>

    <div class="space-y-6">
        <div class="rounded-xl bg-navy-50 border border-navy-100 px-4 py-4 text-xs text-navy-800/80 space-y-2">
            <p class="font-bold text-navy-800 flex items-center gap-1.5"><x-icon name="shield" class="w-4 h-4"/> Cara kerja OTP</p>
            <p>1. User memasukkan username/NIP + password + CAPTCHA seperti biasa.</p>
            <p>2. Bila OTP <b>ON</b>: kode {{ $otp['otp_panjang'] }} digit dikirim ke email user, berlaku {{ $otp['otp_masa_berlaku'] }} menit &amp; sekali pakai.</p>
            <p>3. Sesi login dibuat <b>hanya setelah</b> kode benar (maks {{ $otp['otp_maks_percobaan'] }} percobaan).</p>
            <p>4. Kode asli <b>tidak pernah disimpan</b> di database — hanya hash SHA-256.</p>
            <p>5. Pastikan setiap akun punya email terdaftar — kelola di menu <b>Manajemen User</b>.</p>
            <p>6. Setiap perubahan tercatat di <b>Audit Log</b>.</p>
        </div>

        <div class="card p-5 {{ $otp['smtp_siap'] ? 'border-emerald-200' : 'border-rose-200' }}">
            <p class="text-sm font-bold {{ $otp['smtp_siap'] ? 'text-emerald-600' : 'text-rose-600' }} flex items-center gap-2">
                <x-icon name="{{ $otp['smtp_siap'] ? 'check-badge' : 'x' }}" class="w-5 h-5"/>
                {{ $otp['smtp_siap'] ? 'SMTP sudah dikonfigurasi' : 'SMTP belum dikonfigurasi' }}
            </p>
            <p class="text-xs text-slate-500 mt-1.5">
                {{ $otp['smtp_siap']
                    ? 'Kode OTP akan dikirim memakai konfigurasi SMTP pada tab sebelumnya. Uji dengan tombol "Kirim Email Tes" di tab SMTP & Email bila perlu.'
                    : 'OTP tidak dapat diaktifkan sebelum SMTP diisi — buka tab SMTP & Email, lalu kembali ke sini.' }}
            </p>
        </div>
    </div>
</div>
