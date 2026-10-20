<?php

namespace App\Http\Controllers;

use App\Models\LogKegiatan;
use App\Models\UnitKerja;
use App\Models\User;
use Illuminate\Http\Request;

class PencarianController extends Controller
{
    /** Pencarian global: pegawai, kegiatan, unit kerja — sesuai izin akses. */
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q'));
        $user = $request->user();
        $hasil = (object) ['kegiatan' => collect(), 'pegawai' => collect(), 'unit' => collect()];

        if (mb_strlen($q) >= 2) {
            // Kegiatan dalam cakupan user
            $logQuery = LogKegiatan::with('user', 'output')
                ->where('nama_kegiatan', 'like', "%{$q}%");
            $logQuery = match ($user->role) {
                User::ROLE_ATASAN => $logQuery->whereHas('user', fn ($qq) => $qq->where('atasan_id', $user->id)->orWhere('users.id', $user->id)),
                User::ROLE_ADMIN_UNIT => $logQuery->whereHas('user', fn ($qq) => $qq->where('unit_kerja_id', $user->unit_kerja_id)),
                User::ROLE_SUPERADMIN => $logQuery,
                default => $logQuery->where('user_id', $user->id),
            };
            $hasil->kegiatan = $logQuery->orderByDesc('tanggal')->limit(10)->get();

            // Pegawai (untuk atasan/admin)
            if ($user->role !== User::ROLE_PEGAWAI) {
                $hasil->pegawai = User::cakupanPegawai()->where('is_aktif', true)
                    ->where(fn ($qq) => $qq->where('name', 'like', "%{$q}%")
                        ->orWhere('nip', 'like', "%{$q}%")
                        ->orWhere('jabatan', 'like', "%{$q}%"))
                    ->with('unitKerja')->limit(10)->get();
            }

            // Unit kerja (admin)
            if ($user->bolehKelolaPegawai()) {
                $hasil->unit = UnitKerja::where('nama', 'like', "%{$q}%")
                    ->orWhere('kode', 'like', "%{$q}%")->limit(10)->get();
            }
        }

        return view('pencarian.index', ['q' => $q, 'hasil' => $hasil]);
    }
}
