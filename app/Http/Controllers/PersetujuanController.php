<?php

namespace App\Http\Controllers;

use App\Models\ApprovalLog;
use App\Models\LogKegiatan;
use App\Models\User;
use App\Services\AuditService;
use App\Services\NotifikasiService;
use Illuminate\Http\Request;

class PersetujuanController extends Controller
{
    /** Antrean pemeriksaan log yang berstatus diajukan. */
    public function index(Request $request)
    {
        $user = $request->user();
        $bulan = (int) $request->query('bulan', now()->month);
        $tahun = (int) $request->query('tahun', now()->year);

        $query = LogKegiatan::with(['user.unitKerja', 'output', 'taskTypes'])
            ->where('status', 'diajukan')
            ->whereYear('tanggal', $tahun)
            ->whereMonth('tanggal', $bulan);

        $query = $this->scopeByRole($user, $query);
        $q = $request->query('q');
        if ($q) {
            $query->where(fn ($w) => $w
                ->where('nama_kegiatan', 'like', "%{$q}%")
                ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$q}%")));
        }

        $antrean = $query->orderBy('tanggal')->orderBy('user_id')->paginate(10)->withQueryString();

        return view('persetujuan.index', [
            'antrean' => $antrean,
            'bulan' => $bulan,
            'tahun' => $tahun,
            'q' => $q,
        ]);
    }

    public function show(LogKegiatan $persetujuan)
    {
        $this->authorize('menilai', $persetujuan);
        $persetujuan->load(['output', 'taskTypes', 'lampirans', 'approvals.aktor', 'user.unitKerja']);

        return view('persetujuan.show', ['log' => $persetujuan]);
    }

    public function setujui(Request $request, LogKegiatan $persetujuan)
    {
        $this->authorize('menilai', $persetujuan);

        if ($persetujuan->status !== LogKegiatan::STATUS_DIAJUKAN) {
            return back()->with('gagal', 'Kegiatan ini tidak sedang menunggu persetujuan.');
        }

        $persetujuan->update(['status' => LogKegiatan::STATUS_DISETUJUI]);
        ApprovalLog::create([
            'log_kegiatan_id' => $persetujuan->id,
            'aktor_id' => $request->user()->id,
            'aksi' => ApprovalLog::AKSI_SETUJU,
            'catatan' => $request->input('catatan'),
        ]);

        if ($persetujuan->user_id !== $request->user()->id) {
            NotifikasiService::kirim(
                $persetujuan->user_id,
                'Laporan Disetujui',
                "Kegiatan \"{$persetujuan->nama_kegiatan}\" telah disetujui oleh {$request->user()->name}.",
                route('logbook.show', $persetujuan->id)
            );
        }

        AuditService::catat('setujui', 'Persetujuan', "Menyetujui kegiatan \"{$persetujuan->nama_kegiatan}\" milik {$persetujuan->user->name}.", LogKegiatan::class, $persetujuan->id);

        return redirect()->route('persetujuan.index')->with('sukses', 'Kegiatan disetujui.');
    }

    public function revisi(Request $request, LogKegiatan $persetujuan)
    {
        $this->authorize('menilai', $persetujuan);
        $request->validate(['catatan' => ['required', 'string', 'max:1000']], [
            'catatan.required' => 'Catatan revisi wajib diisi agar pegawai tahu bagian yang harus diperbaiki.',
        ]);

        if ($persetujuan->status !== LogKegiatan::STATUS_DIAJUKAN) {
            return back()->with('gagal', 'Kegiatan ini tidak sedang menunggu persetujuan.');
        }

        $persetujuan->update([
            'status' => LogKegiatan::STATUS_REVISI,
            'catatan_revisi' => $request->input('catatan'),
        ]);
        ApprovalLog::create([
            'log_kegiatan_id' => $persetujuan->id,
            'aktor_id' => $request->user()->id,
            'aksi' => ApprovalLog::AKSI_REVISI,
            'catatan' => $request->input('catatan'),
        ]);

        if ($persetujuan->user_id !== $request->user()->id) {
            NotifikasiService::kirim(
                $persetujuan->user_id,
                'Perlu Revisi',
                "Kegiatan \"{$persetujuan->nama_kegiatan}\" dikembalikan dengan catatan: ".$request->input('catatan'),
                route('logbook.show', $persetujuan->id)
            );
        }

        AuditService::catat('revisi', 'Persetujuan', "Mengembalikan kegiatan \"{$persetujuan->nama_kegiatan}\" untuk revisi.", LogKegiatan::class, $persetujuan->id);

        return redirect()->route('persetujuan.index')->with('sukses', 'Kegiatan dikembalikan kepada pegawai untuk direvisi.');
    }

    /** Admin pusat dapat membuka kembali laporan yang sudah disetujui (mekanisme koreksi). */
    public function bukaKembali(Request $request, LogKegiatan $persetujuan)
    {
        abort_unless($request->user()->isAdminPusat(), 403, 'Hanya Superadmin yang dapat membuka kembali laporan.');

        $request->validate(['catatan' => ['required', 'string', 'max:1000']]);

        if ($persetujuan->status !== LogKegiatan::STATUS_DISETUJUI) {
            return back()->with('gagal', 'Hanya laporan berstatus Disetujui yang dapat dibuka kembali.');
        }

        $persetujuan->update(['status' => LogKegiatan::STATUS_DRAFT, 'catatan_revisi' => $request->input('catatan')]);
        ApprovalLog::create([
            'log_kegiatan_id' => $persetujuan->id,
            'aktor_id' => $request->user()->id,
            'aksi' => ApprovalLog::AKSI_BUKA,
            'catatan' => $request->input('catatan'),
        ]);

        if ($persetujuan->user_id !== $request->user()->id) {
            NotifikasiService::kirim(
                $persetujuan->user_id,
                'Laporan Dibuka Kembali',
                "Kegiatan \"{$persetujuan->nama_kegiatan}\" dibuka kembali oleh Superadmin: ".$request->input('catatan'),
                route('logbook.show', $persetujuan->id)
            );
        }

        AuditService::catat('buka_kembali', 'Persetujuan', "Membuka kembali kegiatan \"{$persetujuan->nama_kegiatan}\".", LogKegiatan::class, $persetujuan->id);

        return back()->with('sukses', 'Laporan dibuka kembali menjadi draft untuk dikoreksi.');
    }

    /** Riwayat persetujuan (semua tindakan pada cakupan user). */
    public function riwayat(Request $request)
    {
        $user = $request->user();
        $bulan = (int) $request->query('bulan', now()->month);
        $tahun = (int) $request->query('tahun', now()->year);

        $logIds = LogKegiatan::select('id')
            ->whereYear('tanggal', $tahun)
            ->whereMonth('tanggal', $bulan);
        $logIds = $this->scopeByRole($user, $logIds)->pluck('id');

        $riwayat = ApprovalLog::with(['aktor', 'logKegiatan.user', 'logKegiatan.output'])
            ->whereIn('log_kegiatan_id', $logIds)
            ->orderByDesc('created_at')
            ->paginate(10)->withQueryString();

        return view('persetujuan.riwayat', [
            'riwayat' => $riwayat,
            'bulan' => $bulan,
            'tahun' => $tahun,
        ]);
    }

    private function scopeByRole(User $user, $query)
    {
        return match ($user->role) {
            User::ROLE_ATASAN => $query->whereHas('user', fn ($q) => $q->where('atasan_id', $user->id)),
            User::ROLE_ADMIN_UNIT => $query->whereHas('user', fn ($q) => $q->where('unit_kerja_id', $user->unit_kerja_id)),
            User::ROLE_SUPERADMIN => $query,
            default => $query->where('user_id', $user->id),
        };
    }
}
