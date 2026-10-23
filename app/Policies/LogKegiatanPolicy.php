<?php

namespace App\Policies;

use App\Models\LogKegiatan;
use App\Models\User;

class LogKegiatanPolicy
{
    public function view(User $user, LogKegiatan $log): bool
    {
        return $this->dalamCakupan($user, $log);
    }

    public function manage(User $user, LogKegiatan $log): bool
    {
        // Pegawai hanya mengelola miliknya; atasan/admin dapat mengelola dalam cakupan.
        return $this->dalamCakupan($user, $log);
    }

    public function update(User $user, LogKegiatan $log): bool
    {
        if (! $this->dalamCakupan($user, $log)) {
            return false;
        }

        // Laporan disetujui tidak bisa diedit langsung oleh pegawai.
        if ($log->status === LogKegiatan::STATUS_DISETUJUI) {
            return $user->isAdminPusat(); // hanya pejabat berwenang (admin pusat) lewat mekanisme koreksi
        }

        // Setelah diajukan, log terkunci sampai ada keputusan atasan (kecuali koreksi admin pusat).
        if ($log->status === LogKegiatan::STATUS_DIAJUKAN) {
            return $user->isAdminPusat();
        }

        // Pegawai lain (atasan/admin) tidak mengubah isi log bawahan.
        return $user->id === $log->user_id || $user->isAdminPusat();
    }

    public function delete(User $user, LogKegiatan $log): bool
    {
        return $user->id === $log->user_id && $log->bolehEdit();
    }

    public function ajukan(User $user, LogKegiatan $log): bool
    {
        return $user->id === $log->user_id && $log->bolehEdit();
    }

    public function menilai(User $user, LogKegiatan $log): bool
    {
        // Atasan langsung, admin unit satu unit, atau admin pusat.
        return $log->user->atasan_id === $user->id
            || ($user->role === User::ROLE_ADMIN_UNIT && $log->user->unit_kerja_id === $user->unit_kerja_id)
            || $user->isAdminPusat();
    }

    private function dalamCakupan(User $user, LogKegiatan $log): bool
    {
        if ($user->id === $log->user_id || $user->isAdminPusat()) {
            return true;
        }

        // Pimpinan dapat melihat laporan kinerja seluruh pegawai.
        if ($user->role === User::ROLE_PIMPINAN) {
            return true;
        }

        if ($user->role === User::ROLE_ATASAN) {
            return $log->user->atasan_id === $user->id;
        }

        if ($user->role === User::ROLE_ADMIN_UNIT) {
            return $log->user->unit_kerja_id === $user->unit_kerja_id;
        }

        return false;
    }
}
