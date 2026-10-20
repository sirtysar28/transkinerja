{{--
    Kartu statistik dashboard — beranimasi (masuk + hover + angka berhitung),
    berikon, dan dapat diklik menuju halaman data terkait.
    Pemakaian:
    <x-stat-card :href="route('logbook.index')" ikon="book" label="Kegiatan Hari Ini"
                 :nilai="$kegiatanHariIni->count()" sub="2 j 30 mnt" warna="navy" :delay="0"/>
    Nilai khusus (mis. jam presensi) gunakan slot "isi".
--}}
@props(['href', 'ikon', 'label', 'nilai' => null, 'sub' => null, 'warna' => 'navy', 'delay' => 0, 'nilaiClass' => ''])
@php
    // Skema warna aksen kartu
    $skema = [
        'navy' => [
            'ikon' => 'from-navy-500 to-navy-800', 'blob' => 'from-navy-200/80 to-navy-50/0',
            'bar' => 'from-navy-600 to-navy-400',
        ],
        'sky' => [
            'ikon' => 'from-sky-500 to-sky-700', 'blob' => 'from-sky-200/70 to-sky-50/0',
            'bar' => 'from-sky-600 to-sky-400',
        ],
        'amber' => [
            'ikon' => 'from-amber-400 to-amber-600', 'blob' => 'from-amber-200/70 to-amber-50/0',
            'bar' => 'from-amber-500 to-amber-300',
        ],
        'emerald' => [
            'ikon' => 'from-emerald-500 to-emerald-700', 'blob' => 'from-emerald-200/70 to-emerald-50/0',
            'bar' => 'from-emerald-600 to-emerald-400',
        ],
        'rose' => [
            'ikon' => 'from-rose-500 to-rose-700', 'blob' => 'from-rose-200/70 to-rose-50/0',
            'bar' => 'from-rose-600 to-rose-400',
        ],
    ][$warna] ?? null;

    $angka = is_numeric($nilai);
@endphp
<a href="{{ $href }}" class="stat-card group" style="animation-delay: {{ $delay }}ms">
    {{-- Bola gradasi dekoratif — membesar saat hover --}}
    <span aria-hidden="true" class="pointer-events-none absolute -top-12 -right-12 w-32 h-32 rounded-full bg-gradient-to-br {{ $skema['blob'] }} blur-2xl transition-all duration-500 group-hover:scale-[1.8] group-hover:rotate-12"></span>

    <div class="relative flex items-start justify-between gap-3">
        <span class="stat-ikon bg-gradient-to-br {{ $skema['ikon'] }}">
            <x-icon :name="$ikon" class="w-[22px] h-[22px]"/>
        </span>
        <span class="flex items-center gap-1 text-[10px] font-bold uppercase tracking-wider text-slate-300 transition-colors duration-300 group-hover:text-navy-500">
            Detail
            <x-icon name="chevron-right" class="w-4 h-4 transition-transform duration-300 group-hover:translate-x-1"/>
        </span>
    </div>

    <p class="stat-label !mt-3.5 relative">{{ $label }}</p>

    @if (isset($isi) && $isi->isNotEmpty())
        <div class="relative mt-1">{{ $isi }}</div>
    @else
        <p class="stat-value relative {{ $nilaiClass }}" @if ($angka) data-countup="{{ $nilai }}" @endif>{{ $nilai }}</p>
    @endif

    @if ($sub)
        <p class="text-xs text-slate-400 mt-0.5 relative">{{ $sub }}</p>
    @endif

    {{-- Garis aksen bawah — meregang saat hover --}}
    <span aria-hidden="true" class="absolute bottom-0 left-0 h-[3px] w-0 bg-gradient-to-r {{ $skema['bar'] }} rounded-full transition-all duration-500 group-hover:w-full"></span>
</a>
