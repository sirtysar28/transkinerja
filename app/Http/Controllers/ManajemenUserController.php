<?php

namespace App\Http\Controllers;

use App\Models\Pengaturan;
use App\Models\UnitKerja;
use App\Models\User;
use App\Services\AuditService;
use App\Services\DigitransService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Manajemen User — daftar semua user & pemetaan akses loginnya.
 * Menu khusus Superadmin: lihat siapa yang punya akses login, ubah mapping
 * (username, email, password, peran), serta cabut/aktifkan akses.
 */
class ManajemenUserController extends Controller
{
    public function index(Request $request)
    {
        $katakunci = $request->query('katakunci');
        $peran = array_values(array_filter((array) $request->query('peran')));
        $statusAkses = $request->query('status');
        $unitAkses = array_values(array_filter((array) $request->query('unit')));

        $akses = User::with('unitKerja')
            ->when($katakunci, fn ($qq) => $qq->where(fn ($w) => $w
                ->where('name', 'like', "%{$katakunci}%")
                ->orWhere('nip', 'like', "%{$katakunci}%")
                ->orWhere('username', 'like', "%{$katakunci}%")
                ->orWhere('email', 'like', "%{$katakunci}%")))
            ->when($peran, fn ($qq) => $qq->whereIn('role', $peran))
            ->when($statusAkses !== null && $statusAkses !== '' && $statusAkses !== 'semua',
                fn ($qq) => $qq->where('is_aktif', $statusAkses === 'aktif'))
            ->when($unitAkses, fn ($qq) => $qq->whereIn('unit_kerja_id', $unitAkses))
            ->orderByDesc('is_aktif')
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString();

        return view('manajemen-user.index', [
            'akses' => $akses,
            'aksesStat' => [
                'total' => User::count(),
                'aktif' => User::where('is_aktif', true)->count(),
                'nonaktif' => User::where('is_aktif', false)->count(),
                'tanpaEmail' => User::where('is_aktif', true)->where(fn ($q) => $q->whereNull('email')->orWhere('email', ''))->count(),
            ],
            'unitList' => UnitKerja::where('is_aktif', true)->whereHas('pegawai')->orderBy('nama')->get(),
            'roleList' => User::ROLES,
            'otpAktif' => Pengaturan::get('otp_aktif') === '1',
            'filter' => $request->only(['katakunci', 'peran', 'status', 'unit']),
        ]);
    }

    /** Jaring keamanan superadmin terakhir — jangan sampai sistem kehilangan akses admin. */
    private function superadminTerakhirDilock(User $target, string $peranBaru, bool $aktifBaru): ?string
    {
        if ($target->role !== User::ROLE_SUPERADMIN || ($peranBaru === User::ROLE_SUPERADMIN && $aktifBaru)) {
            return null;
        }
        $superadminLain = User::where('role', User::ROLE_SUPERADMIN)
            ->where('is_aktif', true)
            ->whereKeyNot($target->id)
            ->exists();

        return $superadminLain ? null : 'Tidak dapat mengubah akses superadmin terakhir yang aktif — sediakan superadmin lain dahulu.';
    }

    /** Simpan mapping akses login: username, email, password, peran, status. */
    public function update(Request $request, User $akses)
    {
        $d = $request->validate([
            'username' => ['required', 'string', 'max:60', 'alpha_dash', Rule::unique('users', 'username')->ignore($akses->id)],
            'email' => ['nullable', 'email', 'max:190', Rule::unique('users', 'email')->ignore($akses->id)],
            'password' => ['nullable', 'string', 'min:6'],
            'role' => ['required', Rule::in(array_keys(User::ROLES))],
            'is_aktif' => ['nullable'],
        ], [
            'username.alpha_dash' => 'Username hanya boleh huruf, angka, tanda hubung, dan garis bawah.',
            'username.unique' => 'Username sudah dipakai user lain.',
            'email.unique' => 'Email sudah dipakai user lain.',
        ]);

        $peranBaru = $d['role'];
        $aktifBaru = ($d['is_aktif'] ?? null) === '1';
        $diriSendiri = $akses->id === $request->user()->id;

        // Akun tertaut DigiTrans: identitas & status login dikunci (master tunggal),
        // hanya PERAN yang dikelola lokal oleh TransKinerja.
        $dariDigitrans = $akses->digitrans_id !== null && DigitransService::aktif();
        $pesanInfo = null;
        if ($dariDigitrans) {
            $d['username'] = $akses->username;
            $d['email'] = $akses->email ?? '';
            unset($d['password']);
            $aktifBaru = $akses->is_aktif; // ikut is_active di DigiTrans
            $pesanInfo = "Identitas, password & status akses {$akses->name} dikelola DigiTrans — hanya peran yang diperbarui.";
        }

        // Superadmin tidak boleh menurunkan/menonaktifkan akunnya sendiri (anti-lockout).
        if ($diriSendiri && ($peranBaru !== User::ROLE_SUPERADMIN || ! $aktifBaru)) {
            return back()->with('gagal', 'Anda tidak dapat mengubah peran atau menonaktifkan akun sendiri.');
        }

        if ($pesan = $this->superadminTerakhirDilock($akses, $peranBaru, $aktifBaru)) {
            return back()->with('gagal', $pesan);
        }

        $perubahan = [];
        if (! $dariDigitrans && $akses->username !== $d['username']) $perubahan[] = 'username';
        if (! $dariDigitrans && $akses->email !== ($d['email'] ?: null)) $perubahan[] = 'email';
        if ($akses->role !== $peranBaru) $perubahan[] = 'peran → '.$peranBaru;
        if (! $dariDigitrans && $akses->is_aktif !== $aktifBaru) $perubahan[] = 'status → '.($aktifBaru ? 'aktif' : 'nonaktif');
        if (! empty($d['password'])) $perubahan[] = 'password diganti';

        $akses->fill([
            'username' => $d['username'],
            'email' => $d['email'] ?: null,
            'role' => $peranBaru,
            'is_aktif' => $aktifBaru,
        ]);
        if (! empty($d['password'])) {
            $akses->password = $d['password']; // mutator 'hashed' pada model meng-hash otomatis
        }
        $akses->save();

        AuditService::catat('update', 'Manajemen User', "Mapping akses login {$akses->name}: ".($perubahan ? implode(', ', $perubahan) : 'tanpa perubahan'), User::class, $akses->id);

        return back()->with('sukses', "Akses login {$akses->name} diperbarui".($perubahan ? ' ('.implode(', ', $perubahan).')' : '').'.')->with('info', $pesanInfo);
    }

    /** Cabut / aktifkan kembali akses login. */
    public function toggle(Request $request, User $akses)
    {
        if ($akses->id === $request->user()->id) {
            return back()->with('gagal', 'Anda tidak dapat menonaktifkan akun sendiri.');
        }

        if ($akses->digitrans_id && DigitransService::aktif()) {
            return back()->with('gagal', "Status akses {$akses->name} mengikuti DigiTrans (kolom is_active). Ubah di DigiTrans lalu sinkronkan — sinkron otomatis berjalan setiap 15 menit.");
        }

        $baru = ! $akses->is_aktif;
        if ($pesan = $this->superadminTerakhirDilock($akses, $akses->role, $baru)) {
            return back()->with('gagal', $pesan);
        }

        $akses->update(['is_aktif' => $baru]);
        AuditService::catat('update', 'Manajemen User', ($baru ? 'Mengaktifkan' : 'Menonaktifkan')." akses login {$akses->name}.", User::class, $akses->id);

        return back()->with('sukses', 'Akses login '.$akses->name.' '.($baru ? 'diaktifkan' : 'dinonaktifkan').'.');
    }
}
