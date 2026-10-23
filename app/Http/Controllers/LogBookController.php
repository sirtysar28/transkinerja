<?php

namespace App\Http\Controllers;

use App\Models\ApprovalLog;
use App\Models\LogKegiatan;
use App\Models\LogLampiran;
use App\Models\OutputRef;
use App\Models\PeriodeLaporan;
use App\Models\TaskType;
use App\Services\AuditService;
use App\Services\NotifikasiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class LogBookController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $bulan = (int) $request->query('bulan', now()->month);
        $tahun = (int) $request->query('tahun', now()->year);
        $status = $request->query('status');
        $q = $request->query('q');

        // Filter tanggal / rentang tanggal (menggantikan filter bulan bila diisi)
        $dari = $request->query('dari');
        $sampai = $request->query('sampai');
        $pakaiRentang = $dari || $sampai;
        if ($dari && ! \Illuminate\Support\Carbon::canBeCreatedFromFormat($dari, 'Y-m-d')) {
            $dari = null;
        }
        if ($sampai && ! \Illuminate\Support\Carbon::canBeCreatedFromFormat($sampai, 'Y-m-d')) {
            $sampai = null;
        }

        $query = LogKegiatan::with(['output', 'taskTypes', 'lampirans'])
            ->where('user_id', $user->id);

        if ($pakaiRentang && ($dari || $sampai)) {
            $awalBulan = now()->create($tahun, $bulan, 1)->startOfDay();
            $akhirBulan = $awalBulan->copy()->endOfMonth();
            $query->whereBetween('tanggal', [
                $dari ? \Illuminate\Support\Carbon::parse($dari) : $awalBulan,
                $sampai ? \Illuminate\Support\Carbon::parse($sampai)->endOfDay() : $akhirBulan,
            ]);
        } else {
            $pakaiRentang = false;
            $query->whereYear('tanggal', $tahun)
                ->whereMonth('tanggal', $bulan);
        }

        if ($status && array_key_exists($status, LogKegiatan::STATUS_LABEL)) {
            $query->where('status', $status);
        }
        if ($q) {
            $query->where(fn ($w) => $w
                ->where('nama_kegiatan', 'like', "%{$q}%")
                ->orWhere('pengguna', 'like', "%{$q}%"));
        }

        $logs = $query->orderByDesc('tanggal')->orderBy('jam_mulai')->paginate(10)->withQueryString();

        return view('logbook.index', [
            'logs' => $logs,
            'bulan' => $bulan,
            'tahun' => $tahun,
            'status' => $status,
            'q' => $q,
            'dari' => $dari,
            'sampai' => $sampai,
            'pakaiRentang' => $pakaiRentang,
            'statusOptions' => LogKegiatan::STATUS_LABEL,
        ]);
    }

    public function create()
    {
        return view('logbook.form', [
            'log' => new LogKegiatan([
                'tanggal' => today()->toDateString(),
                'jam_mulai' => '08:00',
                'jam_selesai' => '16:00',
            ]),
            'outputs' => OutputRef::where('is_aktif', true)->orderBy('nama')->get(),
            'taskTypes' => TaskType::where('is_aktif', true)->orderBy('kode')->get(),
            'aksi' => 'create',
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $log = DB::transaction(function () use ($request, $data) {
            $log = LogKegiatan::create($data + ['user_id' => $request->user()->id]);
            $log->taskTypes()->sync($request->input('jenis_tugas', []));
            $this->simpanLampiran($request, $log);

            return $log;
        });

        AuditService::catat('create', 'Log Book', "Menambah kegiatan \"{$log->nama_kegiatan}\" ({$log->tanggal->format('d-m-Y')}).", LogKegiatan::class, $log->id);

        // Opsi: langsung ajukan saat simpan
        if ($request->input('aksi') === 'ajukan') {
            return $this->prosesAjukan($request, $log, false);
        }

        return redirect()->route('logbook.index', ['bulan' => $log->tanggal->month, 'tahun' => $log->tanggal->year])
            ->with('sukses', 'Kegiatan berhasil disimpan sebagai draft.');
    }

    public function show(LogKegiatan $logbook)
    {
        $this->authorize('view', $logbook);

        $logbook->load(['output', 'taskTypes', 'lampirans', 'approvals.aktor', 'user.unitKerja']);

        return view('logbook.show', ['log' => $logbook]);
    }

    public function edit(LogKegiatan $logbook)
    {
        $this->authorize('update', $logbook);

        return view('logbook.form', [
            'log' => $logbook->load('taskTypes', 'lampirans'),
            'outputs' => OutputRef::where('is_aktif', true)->orderBy('nama')->get(),
            'taskTypes' => TaskType::where('is_aktif', true)->orderBy('kode')->get(),
            'aksi' => 'edit',
        ]);
    }

    public function update(Request $request, LogKegiatan $logbook)
    {
        $this->authorize('update', $logbook);
        $data = $this->validated($request);

        DB::transaction(function () use ($request, $logbook, $data) {
            $logbook->update($data);
            $logbook->taskTypes()->sync($request->input('jenis_tugas', []));
            $this->simpanLampiran($request, $logbook);
        });

        AuditService::catat('update', 'Log Book', "Mengubah kegiatan \"{$logbook->nama_kegiatan}\".", LogKegiatan::class, $logbook->id);

        if ($request->input('aksi') === 'ajukan') {
            return $this->prosesAjukan($request, $logbook, false);
        }

        return redirect()->route('logbook.index', ['bulan' => $logbook->tanggal->month, 'tahun' => $logbook->tanggal->year])
            ->with('sukses', 'Kegiatan berhasil diperbarui.');
    }

    public function destroy(LogKegiatan $logbook)
    {
        $this->authorize('delete', $logbook);

        $nama = $logbook->nama_kegiatan;
        $logbook->delete();
        AuditService::catat('delete', 'Log Book', "Menghapus kegiatan \"{$nama}\".", LogKegiatan::class, $logbook->id);

        return redirect()->route('logbook.index')->with('sukses', 'Kegiatan dihapus.');
    }

    /** Pegawai mengajukan log (draft/revisi -> diajukan). */
    public function ajukan(Request $request, LogKegiatan $logbook)
    {
        $this->authorize('ajukan', $logbook);

        return $this->prosesAjukan($request, $logbook);
    }

    private function prosesAjukan(Request $request, LogKegiatan $log, bool $redirectSendiri = true)
    {
        if (! $log->taskTypes()->exists()) {
            return back()->with('gagal', 'Pilih satu jenis tugas (KS/KP/KT) sebelum mengajukan.');
        }

        // Cek periode pelaporan: jika periode ditutup, pengajuan ditolak.
        $periode = PeriodeLaporan::where('bulan', $log->tanggal->month)
            ->where('tahun', $log->tanggal->year)->first();
        if ($periode && ! $periode->terbuka() && ! $request->user()->isAdminPusat()) {
            return back()->with('gagal', "Periode pelaporan {$periode->label()} sudah ditutup ({$periode->tanggal_tutup->format('d-m-Y')}).");
        }

        $log->update(['status' => LogKegiatan::STATUS_DIAJUKAN, 'catatan_revisi' => null]);
        ApprovalLog::create([
            'log_kegiatan_id' => $log->id,
            'aktor_id' => $request->user()->id,
            'aksi' => ApprovalLog::AKSI_AJUKAN,
        ]);

        // Notifikasi ke atasan penilai (jika ada).
        if ($log->user->atasan_id && $log->user->atasan_id !== $request->user()->id) {
            NotifikasiService::kirim(
                $log->user->atasan_id,
                'Pengajuan Log Book',
                "{$request->user()->name} mengajukan kegiatan \"{$log->nama_kegiatan}\" untuk diperiksa.",
                route('persetujuan.show', $log->id)
            );
        }

        AuditService::catat('ajukan', 'Persetujuan', "Mengajukan kegiatan \"{$log->nama_kegiatan}\".", LogKegiatan::class, $log->id);

        if (! $redirectSendiri) {
            return redirect()->route('logbook.index', ['bulan' => $log->tanggal->month, 'tahun' => $log->tanggal->year])
                ->with('sukses', 'Kegiatan diajukan ke atasan untuk diverifikasi.');
        }

        return back()->with('sukses', 'Kegiatan diajukan ke atasan untuk diverifikasi.');
    }

    public function downloadLampiran(LogLampiran $lampiran)
    {
        $this->authorize('view', $lampiran->logKegiatan);

        return Storage::disk('local')->download($lampiran->path, $lampiran->nama_asli);
    }

    public function hapusLampiran(LogLampiran $lampiran, Request $request)
    {
        $this->authorize('update', $lampiran->logKegiatan);
        $lampiran->delete();
        AuditService::catat('delete', 'Log Book', "Menghapus lampiran \"{$lampiran->nama_asli}\".", LogLampiran::class, $lampiran->id);

        return back()->with('sukses', 'Lampiran dihapus.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'tanggal' => ['required', 'date', 'before_or_equal:today'],
            'nama_kegiatan' => ['required', 'string', 'max:500'],
            'output_id' => ['required', 'exists:output_refs,id'],
            'pengguna' => ['required', 'string', 'max:150'],
            'jam_mulai' => ['nullable', 'date_format:H:i'],
            'jam_selesai' => ['nullable', 'date_format:H:i', 'required_with:jam_mulai'],
            'jenis_tugas' => ['required', 'array', 'min:1', 'max:1'],
            'jenis_tugas.*' => ['exists:task_types,id'],
            'lampiran.*' => ['nullable', 'file', 'max:5120', 'mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx,ppt,pptx,zip'],
        ], [
            'tanggal.before_or_equal' => 'Tanggal tidak boleh di masa depan.',
            'jenis_tugas.required' => 'Pilih salah satu jenis tugas.',
            'jenis_tugas.max' => 'Jenis tugas hanya boleh dipilih satu: KS, KP, atau KT.',
            'jam_selesai.required_with' => 'Jam selesai wajib diisi bila jam mulai diisi.',
        ]);
    }

    private function simpanLampiran(Request $request, LogKegiatan $log): void
    {
        if (! $request->hasFile('lampiran')) {
            return;
        }

        foreach ($request->file('lampiran') as $file) {
            if (! $file->isValid()) {
                continue;
            }
            $path = $file->store("bukti/{$log->id}", 'local');
            $log->lampirans()->create([
                'nama_asli' => $file->getClientOriginalName(),
                'path' => $path,
                'mime' => $file->getMimeType(),
                'ukuran' => $file->getSize(),
            ]);
        }
    }
}
