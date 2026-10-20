{{-- Filter periode (bulan & tahun) + parameter tambahan. Pemakaian:
     <x-period-filter :bulan="$bulan" :tahun="$tahun" :extra="['status' => $status]"/> --}}
@props(['bulan', 'tahun', 'extra' => [], 'action' => null])
@php
    $bulanList = [];
    for ($b = 1; $b <= 12; $b++) { $bulanList[$b] = \App\Models\PeriodeLaporan::namaBulan($b); }
    $tahunList = range(now()->year + 1, 2024, -1);
@endphp
<form method="GET" action="{{ $action }}" class="flex items-center gap-2" data-no-loader>
    @foreach (($extra ?? []) as $k => $v)
        @if ($k !== 'bulan' && $k !== 'tahun' && $k !== 'page' && $v !== null && $v !== '')
            <input type="hidden" name="{{ $k }}" value="{{ $v }}"/>
        @endif
    @endforeach
    <select name="bulan" data-autosubmit class="select !py-2 !w-auto text-xs font-medium">
        @foreach ($bulanList as $b => $nama)
            <option value="{{ $b }}" @selected($b == $bulan)>{{ $nama }}</option>
        @endforeach
    </select>
    <select name="tahun" data-autosubmit class="select !py-2 !w-auto text-xs font-medium">
        @foreach ($tahunList as $t)
            <option value="{{ $t }}" @selected($t == $tahun)>{{ $t }}</option>
        @endforeach
    </select>
    <noscript><button class="btn-secondary btn-sm">Terapkan</button></noscript>
</form>
