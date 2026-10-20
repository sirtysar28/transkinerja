<div class="grid lg:grid-cols-2 gap-6 items-start">
    {{-- Jenis tugas --}}
    <div class="card">
        <div class="card-header"><p class="card-title">Jenis Tugas (KS / KP / KT)</p></div>
        <div class="px-5 pb-5 space-y-2">
            @foreach ($taskTypes as $t)
                <div class="flex items-center gap-3 rounded-xl border border-slate-200 px-4 py-3">
                    <span class="w-12 text-center font-extrabold text-navy-800">{{ $t->kode }}</span>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-semibold text-slate-700">{{ $t->nama }}</p>
                        @if ($t->keterangan)<p class="text-[11px] text-slate-400">{{ $t->keterangan }}</p>@endif
                    </div>
                    <form action="{{ route('pengaturan.taskType.toggle', $t) }}" method="POST">
                        @csrf
                        <button class="{{ $t->is_aktif ? 'badge-disetujui' : 'badge-draft' }} cursor-pointer hover:opacity-80">{{ $t->is_aktif ? 'Aktif' : 'Nonaktif' }}</button>
                    </form>
                </div>
            @endforeach
        </div>
        <form action="{{ route('pengaturan.taskType.store') }}" method="POST" class="px-5 pb-5 flex flex-wrap gap-2 items-end">
            @csrf
            <div class="w-20"><label class="label">Kode</label><input name="kode" required placeholder="XX" class="input" maxlength="10"/></div>
            <div class="flex-1 min-w-[140px]"><label class="label">Nama</label><input name="nama" required placeholder="Nama jenis tugas" class="input"/></div>
            <button class="btn-primary btn-sm"><x-icon name="plus" class="w-4 h-4"/> Tambah</button>
        </form>
        <div class="px-5 pb-5">
            <div class="rounded-xl bg-amber-50 border border-amber-100 px-4 py-3 text-xs text-amber-700">
                ℹ Definisi resmi kode <b>KS, KP, KT</b> masih menunggu konfirmasi Biro SDM sesuai catatan dokumen workflow. Keterangan di atas dapat disesuaikan tanpa memengaruhi data yang sudah tercatat.
            </div>
        </div>
    </div>

    {{-- Output --}}
    <div class="card">
        <div class="card-header"><p class="card-title">Referensi Output</p></div>
        <div class="px-5 pb-5 space-y-2">
            @foreach ($outputs as $o)
                <div class="flex items-center gap-3 rounded-xl border border-slate-200 px-4 py-3">
                    <x-icon name="file" class="w-4 h-4 text-navy-300"/>
                    <p class="text-sm font-semibold text-slate-700 flex-1">{{ $o->nama }}</p>
                    <form action="{{ route('pengaturan.output.toggle', $o) }}" method="POST">
                        @csrf
                        <button class="{{ $o->is_aktif ? 'badge-disetujui' : 'badge-draft' }} cursor-pointer hover:opacity-80">{{ $o->is_aktif ? 'Aktif' : 'Nonaktif' }}</button>
                    </form>
                </div>
            @endforeach
        </div>
        <form action="{{ route('pengaturan.output.store') }}" method="POST" class="px-5 pb-5 flex flex-wrap gap-2 items-end">
            @csrf
            <div class="flex-1 min-w-[160px]"><label class="label">Nama Output</label><input name="nama" required placeholder="mis. Dokumen" class="input"/></div>
            <button class="btn-primary btn-sm"><x-icon name="plus" class="w-4 h-4"/> Tambah</button>
        </form>
    </div>
</div>
