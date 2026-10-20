{{--
    Paginasi TransKinerja: 10 item/halaman, ringkas & bergaya aplikasi.
    Meng-override view pagination Tailwind bawaan Laravel.
    Dipakai otomatis oleh {{ $paginator->links() }} di semua halaman.
--}}
@if ($paginator->hasPages())
    @php
        $halamanIni = $paginator->currentPage();
        $halamanAkhir = $paginator->lastPage();
        // jendela ringkas: 1 … (x-1, x, x+1) … N
        $halaman = collect(range(max(1, $halamanIni - 1), min($halamanAkhir, $halamanIni + 1)))
            ->merge([1, $halamanAkhir])->unique()->sort()->values();
    @endphp
    <nav role="navigation" aria-label="Navigasi halaman" class="flex flex-wrap items-center justify-between gap-3">
        <p class="text-xs text-slate-500">
            @if ($halamanAkhir > 1)
                Menampilkan <span class="font-semibold text-slate-700">{{ $paginator->firstItem() }}–{{ $paginator->lastItem() }}</span>
                dari <span class="font-semibold text-slate-700">{{ $paginator->total() }}</span> item
            @else
                Total <span class="font-semibold text-slate-700">{{ $paginator->total() }}</span> item
            @endif
        </p>

        <ul class="inline-flex flex-wrap items-center gap-1">
            {{-- Sebelumnya --}}
            @if ($paginator->onFirstPage())
                <li><span aria-disabled="true" class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-slate-200 text-slate-300">&lsaquo;</span></li>
            @else
                <li><a href="{{ $paginator->previousPageUrl() }}" rel="prev"
                       class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50">&lsaquo;</a></li>
            @endif

            @foreach ($halaman as $h)
                @if ($loop->iteration > 1 && $h - $halaman[$loop->iteration - 2] > 1)
                    <li><span class="px-1 text-xs text-slate-400">&hellip;</span></li>
                @endif
                @if ($h == $halamanIni)
                    <li><span aria-current="page"
                              class="inline-flex h-8 min-w-[32px] items-center justify-center rounded-lg border border-navy-700 bg-navy-700 px-2 text-xs font-semibold text-white">{{ $h }}</span></li>
                @else
                    <li><a href="{{ $paginator->url($h) }}"
                           class="inline-flex h-8 min-w-[32px] items-center justify-center rounded-lg border border-slate-200 px-2 text-xs font-medium text-slate-600 hover:bg-slate-50">{{ $h }}</a></li>
                @endif
            @endforeach

            {{-- Berikutnya --}}
            @if ($paginator->hasMorePages())
                <li><a href="{{ $paginator->nextPageUrl() }}" rel="next"
                       class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50">&rsaquo;</a></li>
            @else
                <li><span aria-disabled="true" class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-slate-200 text-slate-300">&rsaquo;</span></li>
            @endif
        </ul>
    </nav>
@endif
