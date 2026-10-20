<?php

namespace App\Http\Controllers;

use App\Models\UnitKerja;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UnitKerjaController extends Controller
{
    public function index()
    {
        $unit = UnitKerja::withCount(['pegawai' => fn ($q) => $q->where('is_aktif', true)])
            ->with('parent')->orderBy('nama')->paginate(10);

        return view('unit-kerja.index', [
            'unit' => $unit,
            'parentList' => UnitKerja::where('is_aktif', true)->orderBy('nama')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'kode' => ['required', 'string', 'max:30', 'unique:unit_kerja,kode'],
            'nama' => ['required', 'string', 'max:150'],
            'parent_id' => ['nullable', 'exists:unit_kerja,id'],
        ], ['kode.unique' => 'Kode unit sudah digunakan.']);

        $unit = UnitKerja::create($data + ['is_aktif' => true]);
        AuditService::catat('create', 'Unit Kerja', "Menambah unit kerja {$unit->nama}.", UnitKerja::class, $unit->id);

        return back()->with('sukses', 'Unit kerja ditambahkan.');
    }

    public function update(Request $request, UnitKerja $unitKerja)
    {
        $data = $request->validate([
            'kode' => ['required', 'string', 'max:30', Rule::unique('unit_kerja')->ignore($unitKerja->id)],
            'nama' => ['required', 'string', 'max:150'],
            'parent_id' => ['nullable', 'exists:unit_kerja,id', Rule::notIn([$unitKerja->id])],
            'is_aktif' => ['nullable', 'boolean'],
        ]);

        $data['is_aktif'] = ($data['is_aktif'] ?? null) == '1';
        $unitKerja->update($data);
        AuditService::catat('update', 'Unit Kerja', "Mengubah unit kerja {$unitKerja->nama}.", UnitKerja::class, $unitKerja->id);

        return back()->with('sukses', 'Unit kerja diperbarui.');
    }
}
