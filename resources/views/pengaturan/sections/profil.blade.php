<div class="grid lg:grid-cols-3 gap-6 items-start">
    <div class="card p-6 text-center">
        <span class="w-20 h-20 rounded-full bg-gradient-to-br from-navy-600 to-navy-800 text-white text-3xl font-bold flex items-center justify-center mx-auto">{{ strtoupper(substr($user->name, 0, 1)) }}</span>
        <p class="mt-4 font-bold text-navy-900">{{ $user->name }}</p>
        <p class="text-xs text-slate-400">{{ $user->nip ? 'NIP '.$user->nip : $user->username }}</p>
        <span class="badge bg-navy-50 text-navy-700 border-navy-100 mt-3">{{ $user->roleLabel() }}</span>
        <div class="mt-5 pt-5 border-t border-slate-100 space-y-2 text-left text-xs">
            <p class="flex justify-between"><span class="text-slate-400">Jabatan</span><span class="font-medium text-slate-600 text-right">{{ $user->jabatan ?? '-' }}</span></p>
            <p class="flex justify-between"><span class="text-slate-400">Pangkat/Gol.</span><span class="font-medium text-slate-600 text-right">{{ $user->pangkat_golongan ?? '-' }}</span></p>
            <p class="flex justify-between gap-4"><span class="text-slate-400">Unit Kerja</span><span class="font-medium text-slate-600 text-right">{{ $user->unitKerja?->nama ?? '-' }}</span></p>
            <p class="flex justify-between"><span class="text-slate-400">Atasan</span><span class="font-medium text-slate-600 text-right">{{ $user->atasan?->name ?? '-' }}</span></p>
            <p class="flex justify-between gap-4"><span class="text-slate-400">Email</span><span class="font-medium text-slate-600 text-right">{{ $user->email ?? '-' }}</span></p>
        </div>
        <p class="text-[10px] text-slate-300 mt-4">Perubahan data kepegawaian (NIP, jabatan, unit) silakan hubungi Admin Unit / Superadmin.</p>
    </div>

    <form action="{{ route('pengaturan.profil.update') }}" method="POST" class="card p-6 lg:col-span-2 space-y-5">
        @csrf @method('PUT')
        <div>
            <label class="label">Nama Lengkap <span class="req">*</span></label>
            <input name="name" required value="{{ old('name', $user->name) }}" class="input {{ $errors->has('name') ? 'input-error' : '' }}"/>
            @error('name')<p class="error-text">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="label">Email <span class="text-[10px] font-normal text-slate-400">(tujuan pengiriman notifikasi email)</span></label>
            <input type="email" name="email" value="{{ old('email', $user->email) }}" class="input {{ $errors->has('email') ? 'input-error' : '' }}"/>
            @error('email')<p class="error-text">{{ $message }}</p>@enderror
        </div>
        <hr class="border-slate-100"/>
        <div class="grid sm:grid-cols-2 gap-5">
            <div>
                <label class="label">Password Baru</label>
                <input type="password" name="password" placeholder="min. 6 karakter" class="input {{ $errors->has('password') ? 'input-error' : '' }}"/>
                @error('password')<p class="error-text">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="label">Konfirmasi Password</label>
                <input type="password" name="password_confirmation" class="input"/>
            </div>
        </div>
        <button class="btn-primary"><x-icon name="check" class="w-4 h-4"/> Simpan Perubahan</button>
    </form>
</div>
