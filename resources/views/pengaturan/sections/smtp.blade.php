<div class="grid lg:grid-cols-3 gap-6 items-start">
    <form action="{{ route('pengaturan.smtp.store') }}" method="POST" class="card p-6 lg:col-span-2 space-y-5">
        @csrf
        <div class="card-header !px-0 !pt-0">
            <p class="card-title">Konfigurasi SMTP</p>
            <p class="text-xs text-slate-400">Dipakai untuk mengirim notifikasi email HTML ke email terdaftar tiap pegawai. Contoh SMTP pemerintah/Gmail: host <code class="text-navy-700">smtp.gmail.com</code>, port <code class="text-navy-700">587</code>, TLS + App Password.</p>
        </div>

        <div class="grid sm:grid-cols-3 gap-5">
            <div class="sm:col-span-2">
                <label class="label">SMTP Host <span class="req">*</span></label>
                <input name="smtp_host" required placeholder="smtp.gmail.com" value="{{ old('smtp_host', $smtp['smtp_host']) }}" class="input {{ $errors->has('smtp_host') ? 'input-error' : '' }}"/>
                @error('smtp_host')<p class="error-text">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="label">Port <span class="req">*</span></label>
                <input name="smtp_port" required type="number" min="1" max="65535" value="{{ old('smtp_port', $smtp['smtp_port']) }}" class="input {{ $errors->has('smtp_port') ? 'input-error' : '' }}"/>
                @error('smtp_port')<p class="error-text">{{ $message }}</p>@enderror
            </div>
        </div>

        <div class="grid sm:grid-cols-3 gap-5">
            <div>
                <label class="label">Enkripsi <span class="req">*</span></label>
                <select name="smtp_enkripsi" class="select">
                    @foreach (['tls' => 'TLS (umum, port 587)', 'ssl' => 'SSL (port 465)', 'none' => 'Tanpa enkripsi'] as $val => $label)
                        <option value="{{ $val }}" @selected(old('smtp_enkripsi', $smtp['smtp_enkripsi']) === $val)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="label">Username SMTP</label>
                <input name="smtp_username" placeholder="email@server" value="{{ old('smtp_username', $smtp['smtp_username']) }}" class="input"/>
            </div>
            <div>
                <label class="label">Password SMTP</label>
                <input type="password" name="smtp_password" autocomplete="new-password"
                       placeholder="{{ $smtp['smtp_password_ada'] ? '•••••••• (tersimpan, kosongkan bila tidak diubah)' : 'belum disimpan' }}"
                       class="input"/>
            </div>
        </div>

        <hr class="border-slate-100"/>

        <div class="grid sm:grid-cols-2 gap-5">
            <div>
                <label class="label">Alamat Pengirim <span class="req">*</span></label>
                <input type="email" name="mail_from_address" required placeholder="transkinerja@kementerian.go.id" value="{{ old('mail_from_address', $smtp['mail_from_address']) }}" class="input {{ $errors->has('mail_from_address') ? 'input-error' : '' }}"/>
                @error('mail_from_address')<p class="error-text">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="label">Nama Pengirim <span class="req">*</span></label>
                <input name="mail_from_name" required placeholder="TransKinerja Kementerian Transmigrasi" value="{{ old('mail_from_name', $smtp['mail_from_name']) }}" class="input {{ $errors->has('mail_from_name') ? 'input-error' : '' }}"/>
                @error('mail_from_name')<p class="error-text">{{ $message }}</p>@enderror
            </div>
        </div>

        <label class="flex items-start gap-3 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 cursor-pointer">
            <input type="checkbox" name="notifikasi_email_aktif" value="1" class="mt-0.5 rounded border-slate-300 text-navy-600" @checked(old('notifikasi_email_aktif', $smtp['notifikasi_email_aktif']))/>
            <span class="text-sm text-slate-600">
                <b class="text-slate-800">Kirim notifikasi ke email pegawai</b><br/>
                Setiap notifikasi dalam aplikasi (pengajuan, persetujuan, revisi, dsb.) juga dikirim sebagai <b>email berformat HTML</b> ke email terdaftar penerima.
            </span>
        </label>

        <button class="btn-primary"><x-icon name="check" class="w-4 h-4"/> Simpan Konfigurasi</button>
    </form>

    <div class="space-y-6">
        <div class="card p-6">
            <p class="card-title mb-1">Email Uji Coba</p>
            <p class="text-xs text-slate-400 mb-4">Pastikan konfigurasi sudah disimpan sebelum mengirim uji coba.</p>
            <form action="{{ route('pengaturan.smtp.test') }}" method="POST" class="space-y-4">
                @csrf
                <div>
                    <label class="label">Tujuan <span class="text-[10px] font-normal text-slate-400">(kosong = email Anda: {{ $user->email ?? '-' }})</span></label>
                    <input type="email" name="tujuan" placeholder="email@tujuan.go.id" class="input"/>
                </div>
                <button class="btn-danger w-full"><x-icon name="mail" class="w-4 h-4"/> Kirim Email Tes (HTML)</button>
            </form>
        </div>

        <div class="rounded-xl bg-navy-50 border border-navy-100 px-4 py-4 text-xs text-navy-800/80 space-y-2">
            <p class="font-bold text-navy-800">ℹ Cara kerja</p>
            <p>• Konfigurasi tersimpan di database — <b>tidak perlu mengedit .env</b>.</p>
            <p>• Email memakai template HTML ber-logo resmi Kementerian.</p>
            <p>• Gagal kirim tidak mengganggu aplikasi; kesalahan tercatat di log.</p>
            <p>• Jika server hosting memblokir port SMTP (25/465/587), hubungi penyedia hosting.</p>
        </div>
    </div>
</div>
