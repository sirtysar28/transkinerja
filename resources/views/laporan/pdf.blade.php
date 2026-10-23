{{-- Berkas PDF Formulir Laporan Bulanan — dirender dompdf (A4 landscape) --}}
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8"/>
    <title>Formulir Laporan Bulanan — {{ $pegawai->name }}</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 10px; color: #1e293b; margin: 0; }
        .kop { width: 100%; border-bottom: 2.5px solid #0E2A37; padding-bottom: 6px; margin-bottom: 10px; }
        .kop table { width: 100%; border-collapse: collapse; }
        .kop td { vertical-align: middle; padding: 0; }
        .kop .logo { width: 62px; }
        .kop h1 { margin: 0; font-size: 13px; color: #081C26; letter-spacing: .3px; }
        .kop h2 { margin: 2px 0 0; font-size: 11px; color: #0E2A37; font-weight: bold; }
        .kop p { margin: 1px 0 0; font-size: 9px; color: #64748b; }
        h3.judul { text-align: center; margin: 2px 0 0; font-size: 11px; text-transform: uppercase; letter-spacing: 1px; color: #0E2A37; }

        .blok-atas { width: 100%; margin-bottom: 8px; }
        .blok-atas td { vertical-align: top; }
        .meta td, .identitas td { padding: 1.5px 4px 1.5px 0; font-size: 10px; }
        .meta .label, .identitas .label { color: #475569; font-weight: bold; width: 135px; }
        .identitas .label { width: 118px; }
        .judul-identitas { font-weight: bold; font-size: 10px; margin: 0 0 3px; }

        table.formulir { width: 100%; border-collapse: collapse; margin-top: 4px; }
        table.formulir th, table.formulir td { border: 1px solid #334155; padding: 3px 5px; }
        table.formulir th { background-color: #F2F6F8; font-size: 9px; text-transform: uppercase; color: #0E2A37; }
        table.formulir td.tengah { text-align: center; }
        table.formulir td.tanggal { text-align: center; font-weight: bold; white-space: nowrap; vertical-align: middle; }
        table.formulir td.no { text-align: center; color: #64748b; }

        .ttd { width: 100%; margin-top: 26px; page-break-inside: avoid; }
        .ttd td { width: 50%; text-align: center; vertical-align: top; font-size: 10px; }
        .ttd .spasi { height: 52px; }
        .ttd .nama { font-weight: bold; text-decoration: underline; }
        .catatan { margin-top: 14px; font-size: 7.5px; color: #94a3b8; text-align: center; }
    </style>
</head>
<body>
    {{-- Kop dengan logo Kementerian Transmigrasi --}}
    <div class="kop">
        <table>
            <tr>
                <td class="logo"><img src="{{ $logo }}" width="56" height="56"/></td>
                <td style="padding-left: 8px;">
                    <h1>KEMENTERIAN TRANSMIGRASI REPUBLIK INDONESIA</h1>
                    <h2>{{ $pegawai->unitKerja?->nama ?? '-' }}</h2>
                    <p>Formulir Laporan Bulanan Kinerja ASN — dihasilkan aplikasi TransKinerja</p>
                </td>
            </tr>
        </table>
    </div>

    {{-- Periode, ringkasan produktivitas & identitas lengkap pegawai --}}
    <table class="blok-atas"><tr>
        <td style="width: 46%;">
            <table class="meta">
                <tr><td class="label">Bulan</td><td>: {{ $namaBulan }}</td></tr>
                <tr><td class="label">Tahun</td><td>: {{ $tahun }}</td></tr>
                <tr><td class="label">Total Kegiatan</td><td>: {{ $ringkasan['totalKegiatan'] }} kegiatan</td></tr>
                <tr><td class="label">Kehadiran (Log Book)</td><td>: {{ $ringkasan['hariTerisi'] }} / {{ $ringkasan['hariKerja'] }} hari kerja ({{ $ringkasan['persenKehadiran'] }}%)</td></tr>
                <tr><td class="label">Total Durasi Jam Kerja</td><td>: {{ durasi_format($ringkasan['totalDurasiMenit']) }}</td></tr>
                <tr><td class="label">Rekap Kategori</td><td>: @foreach ($ringkasan['rekapJenis'] as $kode => $n){{ $kode }} = {{ $n }}@if (!$loop->last) · @endif @endforeach</td></tr>
            </table>
        </td>
        <td>
            <p class="judul-identitas">Identitas Pegawai</p>
            <table class="identitas">
                <tr><td class="label">1. Nama</td><td>: {{ $pegawai->name }}</td></tr>
                <tr><td class="label">2. NIP</td><td>: {{ nip_format($pegawai->nip) }}</td></tr>
                <tr><td class="label">3. Pangkat/Gol.Ruang</td><td>: {{ $pegawai->pangkat_golongan ?: '-' }}</td></tr>
                <tr><td class="label">4. Jabatan</td><td>: {{ $pegawai->jabatan ?: '-' }}</td></tr>
                <tr><td class="label">5. Unit Kerja</td><td>: {{ $pegawai->unitKerja?->nama ?? '-' }}</td></tr>
                <tr><td class="label">6. Status Kepegawaian</td><td>: {{ $pegawai->status_pegawai ?: '-' }}</td></tr>
                <tr><td class="label">7. Jenis Kelamin</td><td>: {{ $pegawai->jenis_kelamin ?: '-' }}</td></tr>
                <tr><td class="label">8. Tempat, Tgl Lahir</td><td>: {{ trim(($pegawai->tempat_lahir ?: '-').($pegawai->tanggal_lahir ? ', '.$pegawai->tanggal_lahir->translatedFormat('d F Y') : '')) }}</td></tr>
                <tr><td class="label">9. Agama</td><td>: {{ $pegawai->agama ?: '-' }}</td></tr>
                <tr><td class="label">10. Pendidikan Terakhir</td><td>: {{ $pegawai->pendidikan_terakhir ?: '-' }}</td></tr>
                <tr><td class="label">11. No. Telepon</td><td>: {{ $pegawai->no_telp ?: '-' }}</td></tr>
                <tr><td class="label">12. Email</td><td>: {{ $pegawai->email ?: '-' }}</td></tr>
            </table>
        </td>
    </tr></table>

    {{-- Tabel kegiatan: tanggal sekali per kelompok (rowspan), centang jenis tugas --}}
    <table class="formulir">
        <thead>
        <tr>
            <th style="width: 26px;">No</th>
            <th style="width: 78px;">Tanggal</th>
            <th>Nama Kegiatan</th>
            <th style="width: 66px;">Output</th>
            <th style="width: 95px;">Pengguna</th>
            <th colspan="{{ $taskTypes->count() }}">Jenis Tugas</th>
        </tr>
        <tr>
            <th>1</th><th>2</th><th>3</th><th>4</th><th>5</th>
            @foreach ($taskTypes as $t)<th style="width: 22px;">{{ $t->kode }}</th>@endforeach
        </tr>
        </thead>
        <tbody>
        @php($no = 1)
        @forelse ($logsPerTanggal as $tgl => $kegiatanPerHari)
            @foreach ($kegiatanPerHari as $log)
                <tr>
                    <td class="no">{{ $no++ }}</td>
                    @if ($loop->first)
                        <td class="tanggal" rowspan="{{ $kegiatanPerHari->count() }}">{{ \Carbon\Carbon::parse($tgl)->translatedFormat('j F Y') }}</td>
                    @endif
                    <td>{{ $log->nama_kegiatan }}</td>
                    <td class="tengah">{{ $log->output->nama }}</td>
                    <td>{{ $log->pengguna }}</td>
                    @foreach ($taskTypes as $t)
                        <td class="tengah" style="font-weight: bold;">{{ $log->taskTypes->contains('kode', $t->kode) ? '√' : '' }}</td>
                    @endforeach
                </tr>
            @endforeach
        @empty
            <tr><td colspan="{{ 5 + $taskTypes->count() }}" class="tengah" style="padding: 14px; color: #94a3b8;">Tidak ada kegiatan tercatat pada periode ini.</td></tr>
        @endforelse
        </tbody>
    </table>

    {{-- Tanda tangan --}}
    <table class="ttd"><tr>
        <td>
            Pegawai yang melaporkan,
            <div class="spasi"></div>
            <span class="nama">{{ $pegawai->name }}</span><br/>
            NIP. {{ nip_format($pegawai->nip) }}
        </td>
        <td>
            Atasan / Pejabat Penilai Kinerja,
            <div class="spasi"></div>
            <span class="nama">{{ $pegawai->atasan?->name ?? '................................' }}</span><br/>
            NIP. {{ nip_format($pegawai->atasan?->nip) }}
        </td>
    </tr></table>

    <p class="catatan">Dokumen ini dihasilkan otomatis oleh TransKinerja — Kementerian Transmigrasi RI pada {{ now()->translatedFormat('d F Y, H:i') }} dan memiliki riwayat audit pada aplikasi.</p>
</body>
</html>
