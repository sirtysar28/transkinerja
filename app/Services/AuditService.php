<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Request;

class AuditService
{
    public static function catat(string $aksi, string $modul, ?string $deskripsi = null, ?string $entitas = null, ?int $entitasId = null): void
    {
        AuditLog::create([
            'aktor_id' => auth()->id(),
            'aksi' => $aksi,
            'modul' => $modul,
            'entitas' => $entitas,
            'entitas_id' => $entitasId,
            'deskripsi' => $deskripsi,
            'ip' => Request::ip(),
            'created_at' => now(),
        ]);
    }
}
