<div class="grid lg:grid-cols-3 gap-6 items-start">
    <div class="card lg:col-span-2">
        <div class="card-header"><p class="card-title">Daftar Periode</p></div>
        @if ($periode->isNotEmpty())
            {{-- Tabel periode — hanya tablet/desktop --}}
            <div class="table-wrap hidden md:block">
                <table class="data-table">
                    <thead><tr><th>Periode</th><th>Dibuka</th><th>Ditutup</th><th>Status</th><th class="text-right">Aksi</th></tr></thead>
                    <tbody>
                    @foreach ($periode as $p)
                        <tr>
                            <td class="font-bold text-navy-800">{{ $p->label() }}</td>
                            <td class="text-xs">{{ $p->tanggal_buka->translatedFormat('d M Y') }}</td>
                            <td class="text-xs">{{ $p->tanggal_tutup->translatedFormat('d M Y') }}</td>
                            <td>
                                @if ($p->is_aktif)
                                    <span class="badge-disetujui">Aktif</span>
                                @elseif ($p->tanggal_tutup->isPast())
                                    <span class="badge-draft">Ditutup</span>
                                @else
                                    <span class="badge-diajukan">Terjadwal</span>
                                @endif
                            </td>
                            <td>
                                <form action="{{ route('pengaturan.periode.toggle', $p) }}" method="POST" class="flex justify-end"
                                      data-confirm="{{ $p->is_aktif ? 'Nonaktifkan periode ini?' : 'Jadikan periode ini sebagai periode aktif? Periode aktif lain akan dinonaktifkan.' }}">
                                    @csrf
                                    <button class="{{ $p->is_aktif ? 'btn-ghost btn-sm text-rose-500' : 'btn-secondary btn-sm' }}">
                                        {{ $p->is_aktif ? 'Nonaktifkan' : 'Aktifkan' }}
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Daftar kartu periode — khusus mobile, tanpa scroll horizontal --}}
            <div class="md:hidden divide-y divide-slate-100">
                @foreach ($periode as $p)
                    <div class="px-4 py-3.5 flex items-center gap-3">
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="text-sm font-bold text-navy-800">{{ $p->label() }}</p>
                                @if ($p->is_aktif)
                                    <span class="badge-disetujui">Aktif</span>
                                @elseif ($p->tanggal_tutup->isPast())
                                    <span class="badge-draft">Ditutup</span>
                                @else
                                    <span class="badge-diajukan">Terjadwal</span>
                                @endif
                            </div>
                            <p class="text-[11px] text-slate-400 mt-1 truncate">
                                Buka {{ $p->tanggal_buka->translatedFormat('d M Y') }} · Tutup {{ $p->tanggal_tutup->translatedFormat('d M Y') }}
                            </p>
                        </div>
                        <form action="{{ route('pengaturan.periode.toggle', $p) }}" method="POST" class="shrink-0"
                              data-confirm="{{ $p->is_aktif ? 'Nonaktifkan periode ini?' : 'Jadikan periode ini sebagai periode aktif? Periode aktif lain akan dinonaktifkan.' }}">
                            @csrf
                            <button class="{{ $p->is_aktif ? 'btn-ghost btn-sm !px-3 !text-rose-500' : 'btn-secondary btn-sm !px-3' }}">
                                {{ $p->is_aktif ? 'Nonaktifkan' : 'Aktifkan' }}
                            </button>
                        </form>
                    </div>
                @endforeach
            </div>

            <div class="px-4 sm:px-5 py-4">{{ $periode->links() }}</div>
        @else
            <x-empty icon="calendar" teks="Belum ada periode"/>
        @endif
    </div>

    <div class="card p-6 lg:sticky lg:top-24">
        <p class="card-title mb-4">Tambah Periode</p>
        <form action="{{ route('pengaturan.periode.store') }}" method="POST" class="space-y-4">
            @csrf
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="label">Bulan <span class="req">*</span></label>
                    <select name="bulan" required class="select">
                        @foreach (range(1, 12) as $b)
                            <option value="{{ $b }}">{{ \App\Models\PeriodeLaporan::namaBulan($b) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="label">Tahun <span class="req">*</span></label>
                    <select name="tahun" required class="select">
                        @foreach (range(now()->year + 1, 2024, -1) as $t)
                            <option value="{{ $t }}" @selected($t == now()->year)>{{ $t }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div>
                <label class="label">Tanggal Buka <span class="req">*</span></label>
                <input type="date" name="tanggal_buka" required value="{{ now()->startOfMonth()->toDateString() }}" class="input {{ $errors->has('tanggal_buka') ? 'input-error' : '' }}"/>
                @error('tanggal_buka')<p class="error-text">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="label">Tanggal Tutup <span class="req">*</span></label>
                <input type="date" name="tanggal_tutup" required value="{{ now()->endOfMonth()->toDateString() }}" class="input {{ $errors->has('tanggal_tutup') ? 'input-error' : '' }}"/>
                @error('tanggal_tutup')<p class="error-text">{{ $message }}</p>@enderror
            </div>
            <label class="flex items-center gap-2 text-sm text-slate-600">
                <input type="checkbox" name="is_aktif" value="1" class="rounded border-slate-300 text-navy-600"/>
                Jadikan periode aktif
            </label>
            @if ($errors->has('bulan') || $errors->has('tahun'))
                <p class="error-text">{{ $errors->first('bulan') ?: $errors->first('tahun') }}</p>
            @endif
            <button class="btn-primary w-full"><x-icon name="plus" class="w-4 h-4"/> Tambah Periode</button>
        </form>
    </div>
</div>
