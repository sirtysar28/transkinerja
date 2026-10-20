<?php

namespace App\Http\Controllers;

use App\Models\LogKegiatan;
use App\Models\OutputRef;
use App\Models\PeriodeLaporan;
use App\Models\TaskType;
use App\Models\User;
use App\Services\AuditService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;

class LaporanController extends Controller
{
    /** Rekapitulasi kinerja dengan filter periode / pegawai / jenis tugas / output. */
    public function index(Request $request)
    {
        $user = $request->user();
        $bulan = (int) $request->query('bulan', now()->month);
        $tahun = (int) $request->query('tahun', now()->year);

        // Pegawai yang dapat dipilih sesuai kewenangan
        $pegawaiOptions = User::cakupanPegawai()->where('is_aktif', true)
            ->orderBy('name')->get();

        $pegawaiTerpilih = $request->query('pegawai');
        if ($user->role === User::ROLE_PEGAWAI) {
            $pegawaiTerpilih = $user->id;
        } elseif ($pegawaiTerpilih) {
            abort_unless($pegawaiOptions->contains('id', (int) $pegawaiTerpilih), 403, 'Pegawai di luar cakupan Anda.');
        }

        $query = LogKegiatan::with(['user.unitKerja', 'output', 'taskTypes'])
            ->whereYear('tanggal', $tahun)->whereMonth('tanggal', $bulan);
        $query = $this->scope($user, $query);
        if ($pegawaiTerpilih) {
            $query->where('user_id', $pegawaiTerpilih);
        }
        if ($request->filled('jenis')) {
            $query->whereHas('taskTypes', fn ($q) => $q->where('task_types.id', $request->query('jenis')));
        }
        if ($request->filled('output')) {
            $query->where('output_id', $request->query('output'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }
        if ($request->filled('q')) {
            $q = $request->query('q');
            $query->where(fn ($w) => $w->where('nama_kegiatan', 'like', "%{$q}%")
                ->orWhere('pengguna', 'like', "%{$q}%"));
        }

        $logs = (clone $query)->orderBy('tanggal')->orderBy('user_id')->orderBy('jam_mulai')
            ->paginate(10)->withQueryString();

        // agregasi
        $rekapJenis = TaskType::orderBy('kode')->get()
            ->mapWithKeys(function ($t) use ($bulan, $tahun, $user, $pegawaiTerpilih) {
                $q = LogKegiatan::whereYear('tanggal', $tahun)->whereMonth('tanggal', $bulan);
                $q = $this->scope($user, $q);
                if ($pegawaiTerpilih) {
                    $q->where('user_id', $pegawaiTerpilih);
                }

                return [$t->kode => $q->whereHas('taskTypes', fn ($x) => $x->whereKey($t->id))->count()];
            });

        $rekapOutput = OutputRef::orderBy('nama')->get()
            ->mapWithKeys(function ($o) use ($bulan, $tahun, $user, $pegawaiTerpilih) {
                $q = LogKegiatan::whereYear('tanggal', $tahun)->whereMonth('tanggal', $bulan);
                $q = $this->scope($user, $q);
                if ($pegawaiTerpilih) {
                    $q->where('user_id', $pegawaiTerpilih);
                }

                return [$o->nama => $q->where('output_id', $o->id)->count()];
            });

        $statQuery = clone $query;
        $totalDurasi = (clone $query)->get()->sum(fn ($l) => $l->durasiMenit() ?? 0);
        $statStatus = (clone $statQuery)->select('status', DB::raw('count(*) as c'))
            ->groupBy('status')->pluck('c', 'status');

        return view('laporan.index', [
            'logs' => $logs,
            'pegawaiOptions' => $pegawaiOptions,
            'pegawaiTerpilih' => $pegawaiTerpilih,
            'bulan' => $bulan,
            'tahun' => $tahun,
            'namaBulan' => PeriodeLaporan::namaBulan($bulan),
            'jenisList' => TaskType::where('is_aktif', true)->get(),
            'outputList' => OutputRef::where('is_aktif', true)->get(),
            'rekapJenis' => $rekapJenis,
            'rekapOutput' => $rekapOutput,
            'statStatus' => $statStatus,
            'totalDurasi' => $totalDurasi,
            'filter' => $request->only(['jenis', 'output', 'status', 'q']),
        ]);
    }

    /** Data bersama formulir laporan bulanan (dipakai cetak, PDF, dan Excel). */
    private function dataLaporan(Request $request): array
    {
        $user = $request->user();
        $bulan = (int) $request->query('bulan', now()->month);
        $tahun = (int) $request->query('tahun', now()->year);

        $pegawaiId = (int) $request->query('pegawai', $user->id);
        $pegawai = User::cakupanPegawai()->where('is_aktif', true)->findOrFail($pegawaiId);

        $logs = LogKegiatan::with(['output', 'taskTypes', 'lampirans'])
            ->where('user_id', $pegawai->id)
            ->whereYear('tanggal', $tahun)->whereMonth('tanggal', $bulan)
            ->orderBy('tanggal')->orderBy('jam_mulai')->get();

        return [
            'pegawai' => $pegawai,
            'logs' => $logs,
            // tanggal tampil sekali per kelompok kegiatan (konsep formulir Excel sumber)
            'logsPerTanggal' => $logs->groupBy(fn ($l) => $l->tanggal->format('Y-m-d')),
            'bulan' => $bulan,
            'tahun' => $tahun,
            'namaBulan' => PeriodeLaporan::namaBulan($bulan),
            'taskTypes' => TaskType::orderBy('kode')->get(),
        ];
    }

    /** Formulir Laporan Bulanan siap cetak (print ke PDF via browser). */
    public function cetak(Request $request)
    {
        $data = $this->dataLaporan($request);
        AuditService::catat('cetak', 'Laporan', "Mencetak laporan bulanan {$data['pegawai']->name} ({$data['bulan']}/{$data['tahun']}).");

        return view('laporan.cetak', $data);
    }

    /** Unduh laporan bulanan sebagai berkas PDF asli (dompdf). */
    public function pdf(Request $request)
    {
        $data = $this->dataLaporan($request);
        $data['logo'] = 'data:image/png;base64,'.base64_encode(file_get_contents(public_path('images/logo.png')));

        $fileName = 'Lapkin_'.$data['pegawai']->username.'_'.$data['bulan'].'_'.$data['tahun'].'.pdf';
        AuditService::catat('export', 'Laporan', "Mengunduh PDF laporan {$data['pegawai']->name} ({$data['bulan']}/{$data['tahun']}).");

        return Pdf::loadView('laporan.pdf', $data)
            ->setPaper('a4', 'landscape')
            ->download($fileName);
    }

    /** Ekspor Excel mengikuti format Formulir Laporan Bulanan ASN (dengan logo). */
    public function export(Request $request)
    {
        ['pegawai' => $pegawai, 'logs' => $logs, 'bulan' => $bulan, 'tahun' => $tahun, 'taskTypes' => $taskTypes]
            = $data = $this->dataLaporan($request);

        $sheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $ws = $sheet->getActiveSheet();
        $ws->setTitle('Laporan Bulanan');

        $border = ['borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]];
        $head = ['font' => ['bold' => true], 'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER]];

        // Logo Kementerian Transmigrasi di kop formulir
        $drawing = new Drawing();
        $drawing->setName('Logo Kementerian Transmigrasi RI');
        $drawing->setDescription('Logo Kementerian Transmigrasi Republik Indonesia');
        $drawing->setPath(public_path('images/logo.png'));
        $drawing->setHeight(58);
        $drawing->setCoordinates('A1');
        $drawing->setOffsetX(4);
        $drawing->setOffsetY(4);
        $drawing->setWorksheet($ws);
        $ws->getRowDimension(1)->setRowHeight(30);
        $ws->getRowDimension(2)->setRowHeight(24);

        // Kop formulir
        $ws->mergeCells('C1:H1')->setCellValue('C1', 'FORMULIR LAPORAN BULANAN');
        $ws->getStyle('C1')->getFont()->setBold(true)->setSize(14);
        $ws->getStyle('C1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $ws->mergeCells('C2:H2')->setCellValue('C2', 'Kementerian Transmigrasi Republik Indonesia');
        $ws->getStyle('C2')->getFont()->setBold(true);
        $ws->getStyle('C2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $ws->mergeCells('C3:H3')->setCellValue('C3', $pegawai->unitKerja?->nama ?? '');
        $ws->getStyle('C3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $ws->getStyle('A1:H3')->getBorders()->getBottom()->setBorderStyle(Border::BORDER_MEDIUM);

        $ws->setCellValue('A5', 'Bulan')->setCellValue('C5', ': '.PeriodeLaporan::namaBulan($bulan));
        $ws->setCellValue('A6', 'Tahun')->setCellValue('C6', ": {$tahun}");
        $ws->getStyle('A5:A6')->getFont()->setBold(true);

        $identitas = [
            ['Nama', $pegawai->name],
            ['NIP', $pegawai->nip ?? '-'],
            ['Pangkat/Gol.Ruang', $pegawai->pangkat_golongan ?? '-'],
            ['Jabatan', $pegawai->jabatan ?? '-'],
            ['Unit Kerja', $pegawai->unitKerja?->nama ?? '-'],
        ];
        $row = 7;
        $ws->setCellValue('A'.$row, 'Identitas Pegawai')->getStyle('A'.$row)->getFont()->setBold(true);
        $row++;
        foreach ($identitas as $i => [$label, $nilai]) {
            $ws->setCellValue('A'.$row, $i + 1)
                ->setCellValue('B'.$row, $label)
                ->setCellValue('D'.$row, ':')
                ->setCellValue('E'.$row, $nilai);
            $row++;
        }

        // Tabel kegiatan
        $row += 1;
        $headerRow = $row;
        $ws->mergeCells("A{$row}:A".($row + 1))->setCellValue("A{$row}", 'No');
        $ws->mergeCells("B{$row}:B".($row + 1))->setCellValue("B{$row}", 'Tanggal');
        $ws->mergeCells("C{$row}:C".($row + 1))->setCellValue("C{$row}", 'Nama Kegiatan');
        $ws->mergeCells("D{$row}:D".($row + 1))->setCellValue("D{$row}", 'Output');
        $ws->mergeCells("E{$row}:E".($row + 1))->setCellValue("E{$row}", 'Pengguna');
        $ws->mergeCells("F{$row}:H{$row}")->setCellValue("F{$row}", 'Jenis Tugas');
        $col = 'F';
        foreach ($taskTypes as $t) {
            $ws->setCellValue($col.($row + 1), $t->kode);
            $col++;
        }
        $ws->getStyle("A{$row}:H".($row + 1))->applyFromArray($head + ['fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'EFF3F9']]]);

        // Baris data: tanggal ditulis sekali per kelompok tanggal lalu di-merge (konsep formulir sumber)
        $no = 1;
        $logsPerTanggal = $logs->groupBy(fn ($l) => $l->tanggal->format('Y-m-d'));
        foreach ($logsPerTanggal as $tgl => $kelompok) {
            $awalGrup = $row + 1;
            foreach ($kelompok as $log) {
                $row++;
                $ws->setCellValue("A{$row}", $no++)
                    ->setCellValue("C{$row}", $log->nama_kegiatan)
                    ->setCellValue("D{$row}", $log->output->nama)
                    ->setCellValue("E{$row}", $log->pengguna);
                $kodeJenis = $log->taskTypes->pluck('kode');
                $col = 'F';
                foreach ($taskTypes as $t) {
                    $ws->setCellValue($col.$row, $kodeJenis->contains($t->kode) ? '√' : '');
                    $ws->getStyle($col.$row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $col++;
                }
                $ws->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
            }
            // Tanggal satu kali per grup, merge sel vertikal, format d-mmmm-yyyy (numFmt sumber)
            $ws->setCellValue("B{$awalGrup}", \Illuminate\Support\Carbon::parse($tgl)->translatedFormat('j F Y'));
            if ($kelompok->count() > 1) {
                $sheet->getActiveSheet()->mergeCells("B{$awalGrup}:B{$row}");
            }
            $ws->getStyle("B{$awalGrup}")->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                ->setVertical(Alignment::VERTICAL_CENTER)
                ->setWrapText(true);
            $ws->getStyle("A{$awalGrup}:H{$row}")->applyFromArray($border);
        }

        foreach (['A', 'B', 'D', 'E', 'F', 'G', 'H'] as $c) {
            $ws->getColumnDimension($c)->setAutoSize(true);
        }
        $ws->getColumnDimension('C')->setWidth(60);

        // Blok tanda tangan
        $row += 2;
        $tanggalTtd = \Illuminate\Support\Carbon::create($tahun, $bulan, 1)->endOfMonth();
        $ws->setCellValue("F{$row}", 'Banjarmasin, '.$tanggalTtd->translatedFormat('j F Y'));
        $row++;
        $ws->setCellValue("A{$row}", 'Pegawai yang melaporkan,')->setCellValue("F{$row}", 'Atasan/Pejabat Penilai,');
        $row += 4;
        $ws->setCellValue("A{$row}", $pegawai->name)->setCellValue("F{$row}", $pegawai->atasan?->name ?? '..............................');
        $ws->getStyle("A{$row}")->getFont()->setBold(true)->setUnderline(true);
        $ws->getStyle("F{$row}")->getFont()->setBold(true)->setUnderline(true);
        $row++;
        $ws->setCellValue("A{$row}", 'NIP. '.($pegawai->nip ?? '-'))
            ->setCellValue("F{$row}", 'NIP. '.($pegawai->atasan?->nip ?? '-'));

        $fileName = 'Lapkin_'.$pegawai->username.'_'.$bulan.'_'.$tahun.'.xlsx';
        AuditService::catat('export', 'Laporan', "Ekspor Excel laporan {$pegawai->name} ({$bulan}/{$tahun}).");

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($sheet);

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    private function scope(User $user, $query)
    {
        return match ($user->role) {
            User::ROLE_ATASAN => $query->whereHas('user', fn ($q) => $q->where('atasan_id', $user->id)->orWhere('users.id', $user->id)),
            User::ROLE_ADMIN_UNIT => $query->whereHas('user', fn ($q) => $q->where('unit_kerja_id', $user->unit_kerja_id)),
            User::ROLE_SUPERADMIN => $query,
            default => $query->where('user_id', $user->id),
        };
    }
}
