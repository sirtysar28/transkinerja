@extends('layouts.app')
@section('title', 'Dashboard')

@push('scripts')
    @php
        // Antisipasi manifest lama di server: bila entry chart belum ada di public/build/manifest.json,
        // lewati pemuatan skrip agar halaman tidak error 500 (grafik otomatis aktif setelah build di-upload).
        $manifestChart = @file_get_contents(public_path('build/manifest.json'));
        $chartSiap = $manifestChart !== false && str_contains($manifestChart, 'resources/js/dashboard-charts.js');
    @endphp
    @if ($chartSiap)
        @vite('resources/js/dashboard-charts.js')
    @endif
@endpush

@section('content')
@php
    $u = auth()->user();
@endphp

<div class="flex flex-wrap items-end justify-between gap-3">
    <div>
        <h1 class="page-title">Selamat {{ now()->hour < 11 ? 'pagi' : (now()->hour < 15 ? 'siang' : (now()->hour < 18 ? 'sore' : 'malam') ) }}, {{ \Illuminate\Support\Str::before($u->name, ',') }} 👋</h1>
        <p class="page-sub">{{ ucfirst($tipe === 'pegawai' ? 'Ringkasan aktivitas' : 'Ringkasan kinerja') }} periode {{ $namaBulan }} {{ $tahun }} — {{ now()->translatedFormat('l, d F Y') }}</p>
    </div>
    <x-period-filter :bulan="$bulan" :tahun="$tahun"/>
</div>

{{-- ============================ PEGAWAI ============================ --}}
@if ($tipe === 'pegawai')
    <div class="grid grid-cols-1 min-[420px]:grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
        <x-stat-card :href="route('logbook.index', ['bulan' => now()->month, 'tahun' => now()->year])"
                     ikon="book" label="Kegiatan Hari Ini" warna="navy" :delay="0"
                     :nilai="$kegiatanHariIni->count()" :sub="durasi_format($durasiHariIni)"/>
        <x-stat-card :href="route('logbook.index', ['bulan' => $bulan, 'tahun' => $tahun])"
                     ikon="report" label="Total Kegiatan Bulan Ini" warna="sky" :delay="90"
                     :nilai="$statusCounts->sum()" :sub="$hariTerisi.'/'.$hariKerja.' hari kerja terisi'"/>
        <x-stat-card :href="route('logbook.index', ['status' => 'diajukan', 'bulan' => $bulan, 'tahun' => $tahun])"
                     ikon="check-badge" label="Menunggu Persetujuan" warna="amber" :delay="180"
                     :nilai="$statusCounts->get('diajukan', 0)" :sub="'Disetujui: '.$statusCounts->get('disetujui', 0)"
                     nilai-class="text-amber-600"/>
        <x-stat-card :href="route('presensi.index')" ikon="clock" label="Presensi Hari Ini" warna="emerald" :delay="270"
                     sub="Clock-in / clock-out">
            <x-slot:isi>
                <p class="stat-value text-base leading-7 mt-1">
                    <span class="{{ $presensiHariIni->clock_in ? 'text-emerald-600' : 'text-slate-400' }}">{{ $presensiHariIni->clock_in?->format('H:i') ?: '--:--' }}</span>
                    <span class="text-slate-300">→</span>
                    <span class="{{ $presensiHariIni->clock_out ? 'text-emerald-600' : 'text-slate-400' }}">{{ $presensiHariIni->clock_out?->format('H:i') ?: '--:--' }}</span>
                </p>
            </x-slot:isi>
        </x-stat-card>
    </div>

    <div class="flex flex-wrap gap-3">
        @if (! $presensiHariIni->clock_in)
            <form action="{{ route('presensi.clockIn') }}" method="POST" class="w-full min-[420px]:w-auto">
                @csrf
                <button class="btn-primary w-full min-[420px]:w-auto"><x-icon name="clock" class="w-4 h-4"/> Clock In Sekarang</button>
            </form>
        @elseif (! $presensiHariIni->clock_out)
            <form action="{{ route('presensi.clockOut') }}" method="POST" class="w-full min-[420px]:w-auto">
                @csrf
                <button class="btn-warning w-full min-[420px]:w-auto"><x-icon name="clock" class="w-4 h-4"/> Clock Out</button>
            </form>
        @else
            <span class="btn-success cursor-default"><x-icon name="check" class="w-4 h-4"/> Presensi hari ini lengkap</span>
        @endif
        <a href="{{ route('logbook.create') }}" class="btn-secondary w-full min-[420px]:w-auto sm:w-auto"><x-icon name="plus" class="w-4 h-4"/> Catat Kegiatan Baru</a>
    </div>

    {{-- Perlu revisi --}}
    @if ($perluRevisi->isNotEmpty())
        <div class="card border-rose-200">
            <div class="card-header">
                <p class="card-title !text-rose-600">⚠ Perlu Revisi ({{ $perluRevisi->count() }})</p>
                <a href="{{ route('logbook.index', ['status' => 'revisi']) }}" class="text-xs font-semibold text-navy-600 hover:underline">Lihat semua →</a>
            </div>
            <div class="px-4 sm:px-5 pb-5 space-y-2.5">
                @foreach ($perluRevisi->take(3) as $r)
                    <div class="rounded-xl bg-rose-50/70 border border-rose-100 px-4 py-3">
                        <div class="flex flex-wrap items-center gap-2 justify-between">
                            <p class="text-sm font-semibold text-slate-800">{{ Str::limit($r->nama_kegiatan, 70) }}</p>
                            <x-status status="revisi"/>
                        </div>
                        <p class="text-xs text-rose-600 mt-1">Catatan atasan: {{ $r->catatan_revisi }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    @php
        // Data grafik: tren 14 hari + komposisi status
        $trenLabels = []; $trenValues = [];
        foreach (range(13, 0) as $i) {
            $tgl = today()->subDays($i);
            $trenLabels[] = $tgl->format('d');
            $trenValues[] = (int) $tren->get($tgl->toDateString(), 0);
        }
        $donatLabels = ['Disetujui', 'Diajukan', 'Revisi', 'Draft'];
        $donatValues = [(int) $statusCounts->get('disetujui', 0), (int) $statusCounts->get('diajukan', 0), (int) $statusCounts->get('revisi', 0), (int) $statusCounts->get('draft', 0)];
        $donatWarna = ['#0E9F6E', '#E8A13C', '#E02424', '#94A3B8'];
    @endphp

    <div class="grid lg:grid-cols-3 gap-4 sm:gap-6">
        {{-- Kegiatan hari ini --}}
        <div class="card lg:col-span-2 order-2 lg:order-1">
            <div class="card-header">
                <p class="card-title">Kegiatan Hari Ini</p>
                <a href="{{ route('logbook.index') }}" class="text-xs font-semibold text-navy-600 hover:underline">Log Book →</a>
            </div>
            @if ($kegiatanHariIni->isNotEmpty())
                <div class="px-4 sm:px-5 pb-5 space-y-3">
                    @foreach ($kegiatanHariIni as $k)
                        <div class="flex gap-3 sm:gap-4 rounded-xl border border-slate-100 p-3 sm:p-4 hover:border-navy-200 hover:bg-navy-50/40 transition">
                            <div class="shrink-0 text-center">
                                <p class="text-xs font-bold text-navy-700">{{ $k->jam_mulai ?? '--:--' }}</p>
                                <p class="text-[10px] text-slate-300 mt-0.5">s.d.</p>
                                <p class="text-xs font-bold text-navy-700">{{ $k->jam_selesai ?? '--:--' }}</p>
                            </div>
                            <div class="w-px bg-slate-100 shrink-0"></div>
                            <div class="min-w-0 flex-1">
                                <a href="{{ route('logbook.show', $k) }}" class="text-sm font-semibold text-slate-800 hover:text-navy-700">{{ $k->nama_kegiatan }}</a>
                                <p class="text-xs text-slate-500 mt-0.5">{{ $k->output->nama }} • {{ $k->pengguna }}</p>
                                <div class="flex flex-wrap items-center gap-1.5 mt-2">
                                    @foreach ($k->taskTypes as $t)
                                        <span class="badge bg-navy-50 text-navy-700 border-navy-100">{{ $t->kode }}</span>
                                    @endforeach
                                    <x-status :status="$k->status"/>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <x-empty icon="book" teks="Belum ada kegiatan hari ini" sub="Mulai catat aktivitas Anda agar kinerja terdokumentasi.">
                    <a href="{{ route('logbook.create') }}" class="btn-primary btn-sm mt-4"><x-icon name="plus" class="w-4 h-4"/> Catat Kegiatan</a>
                </x-empty>
            @endif
        </div>

        {{-- Donat komposisi status --}}
        <div class="card p-4 sm:p-5 order-1 lg:order-2">
            <p class="card-title">Komposisi Status — {{ $namaBulan }}</p>
            @if (array_sum($donatValues) > 0)
                <div class="chart-box mt-4" data-chart="donut" data-seri-nama="Total Kegiatan"
                     data-labels='@json($donatLabels)' data-values='@json($donatValues)' data-colors='@json($donatWarna)'></div>
            @else
                <x-empty icon="report" teks="Belum ada kegiatan bulan ini" class="!py-10"/>
            @endif
        </div>
    </div>

    {{-- Grafik area tren 14 hari --}}
    <div class="card p-4 sm:p-5">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <p class="card-title">Aktivitas 14 Hari Terakhir</p>
            <span class="text-[11px] text-slate-400">Jumlah kegiatan per hari</span>
        </div>
        <div class="chart-box mt-4" data-chart="area" data-judul="Kegiatan"
             data-labels='@json($trenLabels)' data-values='@json($trenValues)'></div>
        <div class="mt-4 pt-4 border-t border-slate-100 grid grid-cols-3 gap-2 text-center">
            <div><p class="text-lg font-bold text-navy-800">{{ $statusCounts->get('disetujui', 0) }}</p><p class="text-[10px] text-slate-400">Disetujui</p></div>
            <div><p class="text-lg font-bold text-amber-600">{{ $statusCounts->get('diajukan', 0) }}</p><p class="text-[10px] text-slate-400">Diajukan</p></div>
            <div><p class="text-lg font-bold text-slate-500">{{ $statusCounts->get('draft', 0) }}</p><p class="text-[10px] text-slate-400">Draft</p></div>
        </div>
    </div>

{{-- ============================ ATASAN ============================ --}}
@elseif ($tipe === 'atasan')
    <div class="grid grid-cols-1 min-[420px]:grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
        <x-stat-card :href="route('persetujuan.index')" ikon="check-badge" label="Antrean Persetujuan" warna="amber" :delay="0"
                     :nilai="$totalAntrean" sub="menunggu pemeriksaan Anda"
                     :nilai-class="$totalAntrean > 0 ? 'text-amber-600' : ''"/>
        <x-stat-card :href="route('monitoring.index', ['bulan' => $bulan, 'tahun' => $tahun])" ikon="users" label="Anggota Tim" warna="navy" :delay="90"
                     :nilai="$bawahan->count()" sub="pegawai bawahan langsung"/>
        <x-stat-card :href="route('laporan.index', ['bulan' => $bulan, 'tahun' => $tahun])" ikon="report" label="Total Kegiatan Tim ({{ $namaBulan }})" warna="sky" :delay="180"
                     :nilai="$totalKegiatanTim" sub="catatan log book"/>
        <x-stat-card :href="route('laporan.index', ['status' => 'revisi', 'bulan' => $bulan, 'tahun' => $tahun])" ikon="refresh" label="Perlu Revisi" warna="rose" :delay="270"
                     :nilai="$rekapTim->sum(fn ($r) => $r->revisi)" sub="dikembalikan ke pegawai"
                     :nilai-class="$rekapTim->sum(fn ($r) => $r->revisi) > 0 ? 'text-rose-600' : ''"/>
    </div>

    @php
        // Data grafik tim: batang bertumpuk per anggota + donat komposisi
        $timLabels = $rekapTim->map(fn ($r) => \Illuminate\Support\Str::limit(\Illuminate\Support\Str::before($r->pegawai->name, ','), 18))->values()->all();
        $timSeries = [
            ['name' => 'Menunggu', 'data' => $rekapTim->map(fn ($r) => $r->menunggu)->values()->all(), 'color' => '#E8A13C'],
            ['name' => 'Revisi', 'data' => $rekapTim->map(fn ($r) => $r->revisi)->values()->all(), 'color' => '#E02424'],
            ['name' => 'Disetujui', 'data' => $rekapTim->map(fn ($r) => $r->disetujui)->values()->all(), 'color' => '#0E9F6E'],
        ];
        $timTotal = $rekapTim->sum(fn ($r) => $r->total);
        $timDonatLabels = ['Disetujui', 'Menunggu', 'Revisi', 'Draft'];
        $timDonatValues = [(int) $rekapTim->sum(fn ($r) => $r->disetujui), (int) $rekapTim->sum(fn ($r) => $r->menunggu), (int) $rekapTim->sum(fn ($r) => $r->revisi), (int) $rekapTim->sum(fn ($r) => $r->draft)];
        $timDonatWarna = ['#0E9F6E', '#E8A13C', '#E02424', '#94A3B8'];
    @endphp

    <div class="grid lg:grid-cols-3 gap-4 sm:gap-6">
        {{-- Grafik batang bertumpuk per anggota --}}
        <div class="card p-4 sm:p-5 lg:col-span-2">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <p class="card-title">Kegiatan per Anggota — {{ $namaBulan }}</p>
                <a href="{{ route('monitoring.index') }}" class="text-xs font-semibold text-navy-600 hover:underline">Monitoring →</a>
            </div>
            @if ($rekapTim->isNotEmpty() && $timTotal > 0)
                <div class="chart-box mt-4" data-chart="bar-multi" data-labels='@json($timLabels)' data-series='@json($timSeries)'></div>
            @else
                <x-empty icon="users" teks="Belum ada kegiatan tim bulan ini"/>
            @endif
        </div>

        {{-- Donat komposisi status tim --}}
        <div class="card p-4 sm:p-5">
            <p class="card-title">Komposisi Status Tim</p>
            @if ($timTotal > 0)
                <div class="chart-box mt-4" data-chart="donut" data-seri-nama="Total Kegiatan"
                     data-labels='@json($timDonatLabels)' data-values='@json($timDonatValues)' data-colors='@json($timDonatWarna)'></div>
            @else
                <x-empty icon="report" teks="Belum ada data" class="!py-10"/>
            @endif
        </div>
    </div>

    <div class="grid lg:grid-cols-2 gap-4 sm:gap-6">
        {{-- Antrean --}}
        <div class="card">
            <div class="card-header">
                <p class="card-title">Antrean Pemeriksaan Terbaru</p>
                <a href="{{ route('persetujuan.index') }}" class="text-xs font-semibold text-navy-600 hover:underline">Semua antrean →</a>
            </div>
            @if ($antrean->isNotEmpty())
                <div class="px-4 sm:px-5 pb-5 divide-y divide-slate-100">
                    @foreach ($antrean as $a)
                        <div class="py-3 flex items-center gap-3">
                            <span class="w-9 h-9 rounded-full bg-navy-50 text-navy-700 flex items-center justify-center text-xs font-bold shrink-0 hidden min-[400px]:flex">{{ strtoupper(substr($a->user->name, 0, 1)) }}</span>
                            <div class="min-w-0 flex-1">
                                <p class="text-[13px] font-semibold text-slate-800 truncate">{{ $a->nama_kegiatan }}</p>
                                <p class="text-[11px] text-slate-400 truncate">{{ \Illuminate\Support\Str::before($a->user->name, ',') }} • {{ $a->tanggal->translatedFormat('d M') }}</p>
                            </div>
                            <a href="{{ route('persetujuan.show', $a) }}" class="btn-secondary btn-sm shrink-0 !px-3">Periksa</a>
                        </div>
                    @endforeach
                </div>
            @else
                <x-empty icon="check-badge" teks="Antrean bersih" sub="Tidak ada laporan yang menunggu persetujuan."/>
            @endif
        </div>

        {{-- Status tim --}}
        <div class="card">
            <div class="card-header">
                <p class="card-title">Status Pelaporan Tim — {{ $namaBulan }}</p>
                <a href="{{ route('monitoring.index') }}" class="text-xs font-semibold text-navy-600 hover:underline">Monitoring →</a>
            </div>
            @if ($rekapTim->isNotEmpty())
                <div class="table-wrap pb-5">
                    <table class="data-table">
                        <thead><tr><th>Pegawai</th><th class="text-center">Kegiatan</th><th class="text-center">Menunggu</th><th class="text-center">Revisi</th><th class="text-center">Disetujui</th></tr></thead>
                        <tbody>
                        @foreach ($rekapTim as $r)
                            <tr>
                                <td>
                                    <p class="font-semibold text-slate-800">{{ \Illuminate\Support\Str::before($r->pegawai->name, ',') }}</p>
                                    <p class="text-[11px] text-slate-400">{{ $r->pegawai->jabatan }}</p>
                                </td>
                                <td class="text-center font-bold text-navy-700">{{ $r->total }}</td>
                                <td class="text-center"><span class="{{ $r->menunggu ? 'text-amber-600 font-bold' : 'text-slate-300' }}">{{ $r->menunggu }}</span></td>
                                <td class="text-center"><span class="{{ $r->revisi ? 'text-rose-600 font-bold' : 'text-slate-300' }}">{{ $r->revisi }}</span></td>
                                <td class="text-center"><span class="{{ $r->disetujui ? 'text-emerald-600 font-bold' : 'text-slate-300' }}">{{ $r->disetujui }}</span></td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <x-empty icon="users" teks="Belum ada bawahan terdaftar"/>
            @endif
        </div>
    </div>

{{-- ========================= ADMIN UNIT ========================= --}}
@elseif ($tipe === 'admin_unit')
    <div class="grid grid-cols-1 min-[420px]:grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
        <x-stat-card :href="route('pegawai.index')" ikon="users" label="Pegawai {{ $unit?->kode }}" warna="navy" :delay="0"
                     :nilai="$pegawai->count()" sub="akun aktif"/>
        <x-stat-card :href="route('laporan.index', ['bulan' => $bulan, 'tahun' => $tahun])" ikon="report" label="Kegiatan Bulan Ini" warna="sky" :delay="90"
                     :nilai="$totalKegiatan" :sub="$namaBulan.' '.$tahun"/>
        <x-stat-card :href="route('persetujuan.index')" ikon="check-badge" label="Menunggu Persetujuan" warna="amber" :delay="180"
                     :nilai="$menunggu" sub="perlu ditindaklanjuti atasan"
                     :nilai-class="$menunggu ? 'text-amber-600' : ''"/>
        <x-stat-card :href="route('monitoring.index', ['bulan' => $bulan, 'tahun' => $tahun])" ikon="bell" label="Belum Mengisi Bulan Ini" warna="rose" :delay="270"
                     :nilai="$belumMengisi->count()" sub="pegawai tanpa catatan log"
                     :nilai-class="$belumMengisi->count() ? 'text-rose-600' : ''"/>
    </div>

    @if ($belumMengisi->isNotEmpty())
        <div class="card border-amber-200 bg-amber-50/50">
            <div class="px-4 sm:px-5 py-4 flex flex-wrap items-center gap-3">
                <x-icon name="bell" class="w-5 h-5 text-amber-500"/>
                <p class="text-sm text-amber-800 flex-1 min-w-[200px]">
                    <span class="font-bold">{{ $belumMengisi->count() }} pegawai</span> belum mengisi Log Book pada {{ $namaBulan }} {{ $tahun }}:
                    {{ $belumMengisi->take(5)->map(fn ($p) => \Illuminate\Support\Str::before($p->name, ','))->implode(', ') }}{{ $belumMengisi->count() > 5 ? ', …' : '' }}
                </p>
                <a href="{{ route('monitoring.index') }}" class="btn-warning btn-sm">Lihat Monitoring</a>
            </div>
        </div>
    @endif

    @php
        // Data grafik: tren harian sebulan + donat status
        $jumlahHari = now()->create($tahun, $bulan, 1)->daysInMonth;
        $awalBulanChart = now()->create($tahun, $bulan, 1);
        $trenLabels = []; $trenValues = [];
        for ($d = 1; $d <= $jumlahHari; $d++) {
            $trenLabels[] = (string) $d;
            $trenValues[] = (int) $trenHarian->get($awalBulanChart->copy()->day($d)->toDateString(), 0);
        }
        $unitDraft = max(0, (int) $totalKegiatan - (int) $disetujui - (int) $menunggu - (int) $revisi);
        $unitDonatLabels = ['Disetujui', 'Menunggu', 'Revisi', 'Draft'];
        $unitDonatValues = [(int) $disetujui, (int) $menunggu, (int) $revisi, $unitDraft];
        $unitDonatWarna = ['#0E9F6E', '#E8A13C', '#E02424', '#94A3B8'];
    @endphp

    <div class="grid lg:grid-cols-3 gap-4 sm:gap-6">
        {{-- Area tren harian unit --}}
        <div class="card p-4 sm:p-5 lg:col-span-2">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <p class="card-title">Tren Kegiatan Harian — {{ $namaBulan }} {{ $tahun }}</p>
                <span class="text-[11px] text-slate-400">Tanggal dalam bulan</span>
            </div>
            @if ($totalKegiatan > 0)
                <div class="chart-box mt-4" data-chart="area" data-judul="Kegiatan"
                     data-labels='@json($trenLabels)' data-values='@json($trenValues)'></div>
            @else
                <x-empty icon="chart" teks="Belum ada kegiatan bulan ini" sub="Grafik muncul otomatis saat data tersedia." class="!py-12"/>
            @endif
        </div>

        {{-- Donat status unit --}}
        <div class="card p-4 sm:p-5">
            <p class="card-title">Komposisi Status Unit</p>
            @if ($totalKegiatan > 0)
                <div class="chart-box mt-4" data-chart="donut" data-seri-nama="Total Kegiatan"
                     data-labels='@json($unitDonatLabels)' data-values='@json($unitDonatValues)' data-colors='@json($unitDonatWarna)'></div>
            @else
                <x-empty icon="report" teks="Belum ada data" class="!py-10"/>
            @endif
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <p class="card-title">Aktivitas Terbaru Unit — {{ $unit?->nama }}</p>
            <a href="{{ route('laporan.index') }}" class="text-xs font-semibold text-navy-600 hover:underline">Laporan →</a>
        </div>
        <div class="table-wrap pb-5">
            <table class="data-table">
                <thead><tr><th>Pegawai</th><th>Kegiatan</th><th>Output</th><th>Tanggal</th><th>Status</th></tr></thead>
                <tbody>
                @forelse ($aktivitasTerbaru as $a)
                    <tr>
                        <td class="whitespace-nowrap font-semibold text-slate-700">{{ \Illuminate\Support\Str::before($a->user->name, ',') }}</td>
                        <td class="max-w-[220px] sm:max-w-none">{{ \Illuminate\Support\Str::limit($a->nama_kegiatan, 60) }}</td>
                        <td><span class="badge-draft">{{ $a->output->nama }}</span></td>
                        <td class="whitespace-nowrap">{{ $a->tanggal->translatedFormat('d M') }}</td>
                        <td><x-status :status="$a->status"/></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-slate-400 py-8 text-sm">Belum ada aktivitas bulan ini.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

{{-- ========================= ADMIN PUSAT ========================= --}}
@else
    <div class="grid grid-cols-1 min-[420px]:grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
        <x-stat-card :href="route('pegawai.index')" ikon="users" label="Total Pegawai (ASN)" warna="navy" :delay="0"
                     :nilai="$totalPegawai" sub="lintas unit kerja"/>
        <x-stat-card :href="route('laporan.index', ['bulan' => $bulan, 'tahun' => $tahun])" ikon="report" label="Kegiatan {{ $namaBulan }}" warna="sky" :delay="90"
                     :nilai="$totalKegiatan" sub="seluruh unit kerja"/>
        <x-stat-card :href="route('persetujuan.index')" ikon="check-badge" label="Menunggu Persetujuan" warna="amber" :delay="180"
                     :nilai="$menunggu" sub="antrean global"
                     :nilai-class="$menunggu ? 'text-amber-600' : ''"/>
        <x-stat-card :href="route('laporan.index', ['status' => 'disetujui', 'bulan' => $bulan, 'tahun' => $tahun])" ikon="check" label="Disetujui" warna="emerald" :delay="270"
                     :nilai="$disetujui" sub="telah diverifikasi atasan" nilai-class="text-emerald-600"/>
    </div>

    @php
        // Data grafik: tren harian global + donat status + per unit
        $jumlahHari = now()->create($tahun, $bulan, 1)->daysInMonth;
        $awalBulanChart = now()->create($tahun, $bulan, 1);
        $trenLabels = []; $trenValues = [];
        for ($d = 1; $d <= $jumlahHari; $d++) {
            $trenLabels[] = (string) $d;
            $trenValues[] = (int) $trenHarian->get($awalBulanChart->copy()->day($d)->toDateString(), 0);
        }
        $pusatDraft = max(0, (int) $totalKegiatan - (int) $disetujui - (int) $menunggu - (int) $revisiGlobal);
        $pusatDonatLabels = ['Disetujui', 'Menunggu', 'Revisi', 'Draft'];
        $pusatDonatValues = [(int) $disetujui, (int) $menunggu, (int) $revisiGlobal, $pusatDraft];
        $pusatDonatWarna = ['#0E9F6E', '#E8A13C', '#E02424', '#94A3B8'];
    @endphp

    <div class="grid lg:grid-cols-3 gap-4 sm:gap-6">
        {{-- Area tren global --}}
        <div class="card p-4 sm:p-5 lg:col-span-2">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <p class="card-title">Tren Kegiatan Harian Nasional — {{ $namaBulan }} {{ $tahun }}</p>
                <span class="text-[11px] text-slate-400">Tanggal dalam bulan</span>
            </div>
            @if ($totalKegiatan > 0)
                <div class="chart-box mt-4" data-chart="area" data-judul="Kegiatan"
                     data-labels='@json($trenLabels)' data-values='@json($trenValues)'></div>
            @else
                <x-empty icon="chart" teks="Belum ada kegiatan bulan ini" sub="Grafik muncul otomatis saat data tersedia." class="!py-12"/>
            @endif
        </div>

        {{-- Donat status global --}}
        <div class="card p-4 sm:p-5">
            <p class="card-title">Komposisi Status Global</p>
            @if ($totalKegiatan > 0)
                <div class="chart-box mt-4" data-chart="donut" data-seri-nama="Total Kegiatan"
                     data-labels='@json($pusatDonatLabels)' data-values='@json($pusatDonatValues)' data-colors='@json($pusatDonatWarna)'></div>
            @else
                <x-empty icon="report" teks="Belum ada data" class="!py-10"/>
            @endif
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 sm:gap-6">
        {{-- Grafik batang per unit --}}
        <div class="card p-4 sm:p-5">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <p class="card-title">Kinerja per Unit — {{ $namaBulan }} {{ $tahun }}</p>
                <a href="{{ route('monitoring.index') }}" class="text-xs font-semibold text-navy-600 hover:underline shrink-0">Monitoring →</a>
            </div>
            @if ($perUnit->isNotEmpty() && $perUnit->max('total_kegiatan') > 0)
                <div class="chart-box mt-4" data-chart="bar" data-judul="Kegiatan" data-satuan="kegiatan"
                     data-labels='@json($perUnit->values()->map(fn ($un) => \Illuminate\Support\Str::limit($un->nama, 16))->all())'
                     data-values='@json($perUnit->values()->map(fn ($un) => (int) $un->total_kegiatan)->all())'></div>
            @else
                <x-empty teks="Belum ada data unit"/>
            @endif
        </div>

        <div class="card">
            <div class="card-header">
                <p class="card-title">Aktivitas Terbaru (Lintas Unit)</p>
            </div>
            <div class="px-4 sm:px-5 pb-5 divide-y divide-slate-100">
                @forelse ($aktivitasTerbaru as $a)
                    <div class="py-3 flex items-center gap-3">
                        <span class="w-9 h-9 rounded-full bg-navy-50 text-navy-700 flex items-center justify-center text-xs font-bold shrink-0 hidden min-[400px]:flex">{{ strtoupper(substr($a->user->name, 0, 1)) }}</span>
                        <div class="min-w-0 flex-1">
                            <p class="text-[13px] font-semibold text-slate-800 truncate">{{ $a->nama_kegiatan }}</p>
                            <p class="text-[11px] text-slate-400 truncate">
                                {{ \Illuminate\Support\Str::before($a->user->name, ',') }}
                                <span class="hidden min-[420px]:inline">• {{ $a->user->unitKerja?->nama }}</span>
                                • {{ $a->tanggal->translatedFormat('d M') }}
                            </p>
                        </div>
                        <span class="shrink-0"><x-status :status="$a->status"/></span>
                    </div>
                @empty
                    <x-empty teks="Belum ada aktivitas"/>
                @endforelse
            </div>
        </div>
    </div>

    @if ($periodeAktif)
        <div class="card border-navy-100 bg-navy-50/50">
            <div class="px-4 sm:px-5 py-4 flex flex-wrap items-center gap-3 text-sm">
                <x-icon name="calendar" class="w-5 h-5 text-navy-500"/>
                <p class="flex-1 min-w-[200px] text-navy-800">
                    Periode pelaporan aktif: <span class="font-bold">{{ $periodeAktif->label() }}</span>
                    ({{ $periodeAktif->tanggal_buka->translatedFormat('d M Y') }} — {{ $periodeAktif->tanggal_tutup->translatedFormat('d M Y') }})
                </p>
                <a href="{{ route('pengaturan.index', ['tab' => 'periode']) }}" class="btn-secondary btn-sm">Kelola Periode</a>
            </div>
        </div>
    @endif
@endif
@endsection
