{{--
    Dropdown filter multi-pilih (centang lebih dari satu).
    Pemakaian:
    <x-filter-check name="unit" label="Unit Kerja" :options="$unitOptions" :selected="$filter['unit'] ?? []" searchable/>
    -- Opsi: array sederhana [value => label] atau list of models dengan ->id / ->nama
--}}
@php
    $selected = $selected ?? [];
    $selected = array_map(fn ($v) => (string) $v, is_iterable($selected) ? iterator_to_array($selected) : (array) $selected);
    $searchable = $searchable ?? false;
    $lebar = $lebar ?? 'w-64';

    // Bakukan opsi menjadi peta value => label:
    // - Model UnitKerja → id => nama
    // - Array list (0,1,2…) → nilai = label (mis. ["PNS","CPNS"])
    // - Array peta (kode => label, mis. role) → apa adanya
    $opsiBaku = [];
    foreach ($options ?? [] as $kunci => $opsi) {
        if ($opsi instanceof \App\Models\UnitKerja) {
            $opsiBaku[(string) $opsi->id] = $opsi->nama;
        } elseif (is_int($kunci)) {
            $opsiBaku[(string) $opsi] = $opsi;
        } else {
            $opsiBaku[(string) $kunci] = $opsi;
        }
    }
    $kosong = count($opsiBaku) === 0;
@endphp
<div class="relative" data-multicheck>
    <button type="button" data-mf-toggle @disabled($kosong)
            class="select !w-auto flex items-center justify-between gap-2 min-w-[130px] {{ $kosong ? 'opacity-60 cursor-not-allowed' : 'cursor-pointer' }} {{ $selected ? '!border-navy-400 !text-navy-800 !bg-navy-50/50' : '' }}">
        <span class="flex items-center gap-1.5 truncate">
            <span data-mf-label>{{ $label }}</span>
            <span data-mf-count class="hidden inline-flex items-center justify-center min-w-[18px] h-[18px] px-1 rounded-full bg-navy-700 text-white text-[10px] font-bold leading-none">0</span>
        </span>
        <x-icon name="chevron-down" class="w-4 h-4 text-slate-400 shrink-0"/>
    </button>

    <div data-mf-panel class="hidden absolute z-40 mt-2 {{ $lebar }} rounded-2xl border border-slate-200 bg-white shadow-xl shadow-slate-900/10 overflow-hidden">
        @if ($searchable && ! $kosong)
            <div class="p-2 border-b border-slate-100">
                <div class="relative">
                    <x-icon name="search" class="w-3.5 h-3.5 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"/>
                    <input type="text" data-mf-search placeholder="Cari…" autocomplete="off"
                           class="input !py-1.5 !pl-8 !text-xs !rounded-lg"/>
                </div>
            </div>
        @endif
        <div class="max-h-60 overflow-y-auto p-1.5 space-y-0.5" data-mf-list>
            @forelse ($opsiBaku as $value => $labelText)
                <label class="mf-item flex items-center gap-2.5 px-2.5 py-1.5 rounded-lg hover:bg-navy-50 cursor-pointer text-[13px] text-slate-700">
                    <input type="checkbox" name="{{ $name }}[]" value="{{ $value }}" @checked(in_array($value, $selected, true))
                           class="rounded border-slate-300 text-navy-600 focus:ring-navy-400"/>
                    <span class="truncate">{{ $labelText }}</span>
                </label>
            @empty
                <p class="px-3 py-3 text-xs text-slate-400 italic">Belum ada data</p>
            @endforelse
        </div>
        @if (! $kosong)
            <div class="flex items-center justify-between gap-2 border-t border-slate-100 bg-slate-50/70 px-3 py-2 text-[11px]">
                <button type="button" data-mf-all class="font-semibold text-navy-600 hover:text-navy-800 hover:underline">Pilih semua</button>
                <span class="text-slate-300">|</span>
                <button type="button" data-mf-clear class="text-slate-400 hover:text-rose-500 hover:underline">Hapus pilihan</button>
            </div>
        @endif
    </div>
</div>
