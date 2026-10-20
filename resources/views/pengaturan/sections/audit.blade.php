<div class="card p-4 mb-6">
    <form method="GET" action="{{ route('pengaturan.index') }}" class="flex flex-wrap gap-3 items-end" data-no-loader>
        <input type="hidden" name="tab" value="audit"/>
        <div class="flex-1 min-w-[200px]">
            <label class="label">Cari</label>
            <div class="relative">
                <x-icon name="search" class="w-4 h-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400"/>
                <input name="q" value="{{ $filter['q'] ?? '' }}" placeholder="Deskripsi atau nama aktor…" class="input !pl-10"/>
            </div>
        </div>
        <div>
            <label class="label">Modul</label>
            <select name="modul" class="select !w-full sm:!w-auto">
                <option value="">Semua modul</option>
                @foreach ($modulList as $m)
                    <option value="{{ $m }}" @selected($m == ($filter['modul'] ?? null))>{{ $m }}</option>
                @endforeach
            </select>
        </div>
        <button class="btn-primary w-full sm:w-auto">Filter</button>
    </form>
</div>

<div class="card">
    @if ($logs->isNotEmpty())
        {{-- Tabel audit — hanya tablet/desktop --}}
        <div class="table-wrap hidden md:block">
            <table class="data-table">
                <thead><tr><th>Waktu</th><th>Aktor</th><th>Aksi</th><th>Modul</th><th>Deskripsi</th><th>IP</th></tr></thead>
                <tbody>
                @foreach ($logs as $l)
                    <tr>
                        <td class="whitespace-nowrap text-xs text-slate-500">{{ $l->created_at->translatedFormat('d M Y H:i') }}</td>
                        <td class="text-xs font-semibold text-slate-700">{{ $l->aktor?->name ?? 'Sistem' }}</td>
                        <td>
                            <span class="badge {{ str_contains($l->aksi, 'delete') || str_contains($l->aksi, 'nonaktif') ? 'badge-revisi' : (str_contains($l->aksi, 'create') ? 'badge-disetujui' : 'badge-draft') }}">{{ $l->aksi }}</span>
                        </td>
                        <td class="text-xs text-slate-500">{{ $l->modul }}</td>
                        <td class="text-xs text-slate-600 max-w-[320px]">{{ $l->deskripsi }}</td>
                        <td class="text-[10px] text-slate-400 font-mono">{{ $l->ip }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>

        {{-- Daftar kartu audit — khusus mobile, tanpa scroll horizontal --}}
        <div class="md:hidden divide-y divide-slate-100">
            @foreach ($logs as $l)
                <div class="px-4 py-3.5">
                    <div class="flex items-center gap-2">
                        <span class="badge {{ str_contains($l->aksi, 'delete') || str_contains($l->aksi, 'nonaktif') ? 'badge-revisi' : (str_contains($l->aksi, 'create') ? 'badge-disetujui' : 'badge-draft') }}">{{ $l->aksi }}</span>
                        <span class="ml-auto text-[10px] text-slate-400 whitespace-nowrap">{{ $l->created_at->translatedFormat('d M Y H:i') }}</span>
                    </div>
                    <p class="text-xs font-semibold text-slate-700 mt-1.5 truncate">
                        {{ $l->aktor?->name ?? 'Sistem' }} <span class="font-normal text-slate-400">• {{ $l->modul }}</span>
                    </p>
                    <p class="text-xs text-slate-500 mt-0.5 line-clamp-2">{{ $l->deskripsi }}</p>
                    <p class="text-[10px] text-slate-300 font-mono mt-1">{{ $l->ip }}</p>
                </div>
            @endforeach
        </div>

        <div class="px-4 sm:px-5 py-4">{{ $logs->links() }}</div>
    @else
        <x-empty icon="file" teks="Belum ada aktivitas tercatat"/>
    @endif
</div>
