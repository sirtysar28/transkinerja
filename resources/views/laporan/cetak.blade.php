<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8"/>
    <title>Formulir Laporan Bulanan — {{ $pegawai->name }}</title>
    <link rel="icon" type="image/png" href="{{ asset('images/favicon.png') }}"/>
    <link rel="preconnect" href="https://fonts.googleapis.com"/>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
    @vite(['resources/css/app.css'])
</head>
<body class="bg-slate-100">
    {{-- Toolbar cetak --}}
    <div class="no-print sticky top-0 z-20 bg-white/95 backdrop-blur border-b border-slate-200 px-6 py-3 flex items-center justify-between gap-3">
        <div class="flex items-center gap-3">
            <img src="{{ asset('images/logo.png') }}" class="w-9 h-9" alt="Logo"/>
            <div>
                <p class="text-sm font-bold text-navy-900">Formulir Laporan Bulanan</p>
                <p class="text-xs text-slate-400">{{ $namaBulan }} {{ $tahun }} • {{ $pegawai->name }}</p>
            </div>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('laporan.pdf', ['pegawai' => $pegawai->id, 'bulan' => $bulan, 'tahun' => $tahun]) }}" class="btn-danger btn-sm">
                <x-icon name="download" class="w-4 h-4"/> Unduh PDF
            </a>
            <a href="{{ route('laporan.export', ['pegawai' => $pegawai->id, 'bulan' => $bulan, 'tahun' => $tahun]) }}" class="btn-success btn-sm">
                <x-icon name="excel" class="w-4 h-4"/> Unduh Excel
            </a>
            <button onclick="window.print()" class="btn-secondary btn-sm"><x-icon name="print" class="w-4 h-4"/> Cetak</button>
        </div>
    </div>

    <div class="p-4 md:p-8 flex justify-center">
        <div class="print-area card w-full max-w-5xl p-8 md:p-10 bg-white">
            {{-- Kop --}}
            <div class="text-center border-b-[3px] border-navy-900 pb-4 mb-6">
                <div class="flex items-center justify-center gap-4 mb-3 print:mb-2">
                    <img src="{{ asset('images/logo.png') }}" class="w-14 h-14" alt="Logo Kementerian Transmigrasi"/>
                    <div class="text-left">
                        <h1 class="text-base font-extrabold text-navy-950 leading-tight">KEMENTERIAN TRANSMIGRASI REPUBLIK INDONESIA</h1>
                        <p class="text-[11px] text-slate-600">{{ $pegawai->unitKerja?->nama ?? '-' }}</p>
                    </div>
                </div>
                <h2 class="text-sm font-bold text-navy-900 uppercase tracking-wide">Formulir Laporan Bulanan Kinerja ASN</h2>
                <p class="text-[11px] text-slate-400">Dihasilkan otomatis oleh aplikasi TransKinerja pada {{ now()->translatedFormat('d F Y, H:i') }}</p>
            </div>

            {{-- Periode & identitas --}}
            <div class="grid md:grid-cols-2 gap-6 mb-6">
                <table class="text-[12px]">
                    <tr><td class="pr-2 align-top font-semibold text-slate-500 w-28">Bulan</td><td class="align-top w-2">:</td><td>{{ $namaBulan }}</td></tr>
                    <tr><td class="pr-2 align-top font-semibold text-slate-500">Tahun</td><td class="align-top">:</td><td>{{ $tahun }}</td></tr>
                    <tr><td class="pr-2 align-top font-semibold text-slate-500">Total Kegiatan</td><td class="align-top">:</td><td>{{ $logs->count() }} kegiatan</td></tr>
                </table>
                <table class="text-[12px] w-full">
                    <tr><td class="pr-2 align-top font-semibold text-slate-500 w-40">1. Nama</td><td class="align-top w-2">:</td><td>{{ $pegawai->name }}</td></tr>
                    <tr><td class="pr-2 align-top font-semibold text-slate-500">2. NIP</td><td class="align-top">:</td><td>{{ $pegawai->nip ?? '-' }}</td></tr>
                    <tr><td class="pr-2 align-top font-semibold text-slate-500">3. Pangkat/Gol.Ruang</td><td class="align-top">:</td><td>{{ $pegawai->pangkat_golongan ?? '-' }}</td></tr>
                    <tr><td class="pr-2 align-top font-semibold text-slate-500">4. Jabatan</td><td class="align-top">:</td><td>{{ $pegawai->jabatan ?? '-' }}</td></tr>
                    <tr><td class="pr-2 align-top font-semibold text-slate-500">5. Unit Kerja</td><td class="align-top">:</td><td>{{ $pegawai->unitKerja?->nama ?? '-' }}</td></tr>
                </table>
            </div>

            {{-- Tabel kegiatan: tanggal ditampilkan sekali per kelompok (konsep formulir sumber) --}}
            <table class="w-full text-[11px] border-collapse">
                <thead>
                <tr class="bg-navy-50">
                    <th class="border border-slate-400 px-2 py-2 w-10">No</th>
                    <th class="border border-slate-400 px-2 py-2 w-24">Tanggal</th>
                    <th class="border border-slate-400 px-2 py-2">Nama Kegiatan</th>
                    <th class="border border-slate-400 px-2 py-2 w-24">Output</th>
                    <th class="border border-slate-400 px-2 py-2 w-32">Pengguna</th>
                    <th class="border border-slate-400 px-2 py-2 w-10" colspan="{{ $taskTypes->count() }}">Jenis Tugas</th>
                </tr>
                <tr class="bg-navy-50">
                    <th class="border border-slate-400 px-2 py-1">1</th>
                    <th class="border border-slate-400 px-2 py-1">2</th>
                    <th class="border border-slate-400 px-2 py-1">3</th>
                    <th class="border border-slate-400 px-2 py-1">4</th>
                    <th class="border border-slate-400 px-2 py-1">5</th>
                    @foreach ($taskTypes as $t)
                        <th class="border border-slate-400 px-1 py-1 text-[9px]">{{ $t->kode }}</th>
                    @endforeach
                </tr>
                </thead>
                <tbody>
                @php($no = 1)
                @forelse ($logsPerTanggal as $tgl => $kegiatanPerHari)
                    @foreach ($kegiatanPerHari as $log)
                        <tr>
                            <td class="border border-slate-400 px-2 py-1.5 text-center text-slate-500">{{ $no++ }}</td>
                            @if ($loop->first)
                                <td class="border border-slate-400 px-2 py-1.5 text-center align-middle font-semibold whitespace-nowrap" rowspan="{{ $kegiatanPerHari->count() }}">{{ \Carbon\Carbon::parse($tgl)->translatedFormat('j F Y') }}</td>
                            @endif
                            <td class="border border-slate-400 px-2 py-1.5">{{ $log->nama_kegiatan }}</td>
                            <td class="border border-slate-400 px-2 py-1.5 text-center">{{ $log->output->nama }}</td>
                            <td class="border border-slate-400 px-2 py-1.5">{{ $log->pengguna }}</td>
                            @foreach ($taskTypes as $t)
                                <td class="border border-slate-400 px-1 py-1.5 text-center font-bold">{{ $log->taskTypes->contains('kode', $t->kode) ? '√' : '' }}</td>
                            @endforeach
                        </tr>
                    @endforeach
                @empty
                    <tr><td class="border border-slate-400 px-2 py-6 text-center text-slate-400" colspan="{{ 5 + $taskTypes->count() }}">Tidak ada kegiatan tercatat pada periode ini.</td></tr>
                @endforelse
                </tbody>
            </table>

            {{-- Tanda tangan --}}
            <div class="grid grid-cols-2 gap-10 mt-10 text-[12px]">
                <div>
                    <p class="text-center">Pegawai yang melaporkan,</p>
                    <div class="h-20"></div>
                    <p class="text-center font-bold underline">{{ $pegawai->name }}</p>
                    <p class="text-center">NIP. {{ $pegawai->nip ?? '-' }}</p>
                </div>
                <div>
                    <p class="text-center">Atasan / Pejabat Penilai Kinerja,</p>
                    <div class="h-20"></div>
                    <p class="text-center font-bold underline">{{ $pegawai->atasan?->name ?? '................................' }}</p>
                    <p class="text-center">NIP. {{ $pegawai->atasan?->nip ?? '-' }}</p>
                </div>
            </div>

            <p class="text-[9px] text-slate-300 mt-8 text-center">Dokumen ini dihasilkan otomatis oleh TransKinerja — Kementerian Transmigrasi RI dan memiliki riwayat audit pada aplikasi.</p>
        </div>
    </div>
</body>
</html>
