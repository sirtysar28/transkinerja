<?php

namespace App\Http\Controllers;

use App\Models\UnitKerja;
use App\Models\User;
use App\Services\AuditService;
use App\Services\DigitransService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class PegawaiController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $q = $request->query('q');

        // Filter multi-centang: nilai dikirim sebagai array (mis. unit[]=1&unit[]=2)
        $arr = fn ($v) => array_values(array_filter(array_map('strval', (array) $v)));
        $unit = $arr($request->query('unit'));
        $role = $arr($request->query('role'));
        $status = $arr($request->query('status'));
        $jk = $arr($request->query('jk'));
        $pendidikan = $arr($request->query('pendidikan'));
        $renang = $arr($request->query('renang'));
        $inggris = $arr($request->query('inggris'));

        // Pencocokan teks tahan-varian: kecilkan huruf & buang spasi/tanda baca di KEDUA sisi
        // sehingga data lama ("Laki - laki", "pns", "S-1", "PNS ") tetap cocok dengan opsi baku.
        $sqlNorm = fn (string $kolom) => "replace(replace(replace(lower({$kolom}), ' ', ''), '-', ''), '.', '')";
        $baku = fn (array $v) => array_values(array_filter(
            array_map(fn ($x) => preg_replace('/[^a-z0-9]+/u', '', mb_strtolower((string) $x)), $v)
        ));

        $query = User::with(['unitKerja', 'atasan'])
            ->where('is_aktif', true)
            ->when(! $user->isAdminPusat(), fn ($qq) => $qq->where('unit_kerja_id', $user->unit_kerja_id))
            ->when($q, fn ($qq) => $qq->where(fn ($w) => $w
                ->where('name', 'like', "%{$q}%")
                ->orWhere('nip', 'like', "%{$q}%")
                ->orWhere('username', 'like', "%{$q}%")
                ->orWhere('jabatan', 'like', "%{$q}%")))
            ->when($unit, fn ($qq) => $qq->whereIn('unit_kerja_id', $unit))
            ->when($role, fn ($qq) => $qq->whereIn('role', $role))
            ->when($status, fn ($qq) => $qq->whereIn(DB::raw($sqlNorm('status_pegawai')), $baku($status)))
            ->when($jk, fn ($qq) => $qq->whereIn(DB::raw($sqlNorm('jenis_kelamin')), $baku($jk)))
            ->when($pendidikan, fn ($qq) => $qq->whereIn(DB::raw($sqlNorm('level_pendidikan')), $baku($pendidikan)))
            ->when($renang, fn ($qq) => $qq->whereIn(DB::raw($sqlNorm('kemampuan_renang')), $baku($renang)))
            ->when($inggris, fn ($qq) => $qq->whereIn(DB::raw($sqlNorm('kemampuan_inggris')), $baku($inggris)));

        $pegawai = $query->orderBy('name')->paginate(10)->withQueryString();

        // Opsi dropdown filter — nilai baku dulu, lalu nilai unik dari data yang sudah DINORMALISASI
        // agar varian lama (mis. "Laki-Laki", "P", "LAKI-LAKI") tak memunculkan opsi ganda.
        $cakupan = User::where('is_aktif', true)
            ->when(! $user->isAdminPusat(), fn ($qq) => $qq->where('unit_kerja_id', $user->unit_kerja_id));
        $distink = fn (string $kolom) => $cakupan->clone()->whereNotNull($kolom)->distinct()->pluck($kolom)->all();

        $statusOptions = collect(User::STATUS_PEGAWAI)
            ->merge(collect($distink('status_pegawai'))->map(fn ($v) => User::normStatusPegawai($v)))
            ->filter()->unique()->values()->all();
        $jkOptions = collect(['Laki-laki', 'Perempuan'])
            ->merge(collect($distink('jenis_kelamin'))->map(fn ($v) => User::normJenisKelamin($v)))
            ->filter()->unique()->values()->all();
        $pendidikanOptions = collect(User::LEVEL_PENDIDIKAN)
            ->merge(collect($distink('level_pendidikan'))->map(fn ($v) => User::normLevelPendidikan($v)))
            ->filter()->unique()->values()->all();
        $kemampuanDasar = collect(array_keys(User::KEMAMPUAN));
        $renangOptions = $kemampuanDasar
            ->merge(collect($distink('kemampuan_renang'))->map(fn ($v) => User::normKemampuan($v)))
            ->filter()->unique()->values()->all();
        $inggrisOptions = $kemampuanDasar
            ->merge(collect($distink('kemampuan_inggris'))->map(fn ($v) => User::normKemampuan($v)))
            ->filter()->unique()->values()->all();

        // Opsi unit kerja pada filter HANYA unit yang benar-benar ada pegawai aktifnya
        // (sebagaimana data hasil import Excel) — unit kosong/kembar tak ditampilkan.
        $unitList = UnitKerja::where('is_aktif', true)
            ->whereHas('pegawai', fn ($qq) => $qq->where('is_aktif', true))
            ->orderBy('nama')->get();

        return view('pegawai.index', [
            'pegawai' => $pegawai,
            'unitList' => $unitList,
            'roleList' => User::ROLES,
            'statusOptions' => $statusOptions,
            'jkOptions' => $jkOptions,
            'pendidikanOptions' => $pendidikanOptions,
            'renangOptions' => $renangOptions,
            'inggrisOptions' => $inggrisOptions,
            'filter' => $request->only(['q', 'unit', 'role', 'status', 'jk', 'pendidikan', 'renang', 'inggris']),
        ]);
    }

    public function create()
    {
        if (DigitransService::aktif()) {
            return redirect()->route('pegawai.index')
                ->with('gagal', 'Penambahan akun dinonaktifkan — data pegawai & login dikelola terpusat di DigiTrans. Buat akun di DigiTrans lalu klik «Sinkron DigiTrans».');
        }

        return view('pegawai.form', [
            'pegawai' => new User(),
            'unitList' => $this->unitList(),
            'atasanList' => $this->atasanList(),
            'roleList' => User::ROLES,
        ]);
    }

    public function store(Request $request)
    {
        if (DigitransService::aktif()) {
            return redirect()->route('pegawai.index')
                ->with('gagal', 'Penambahan akun dinonaktifkan — data pegawai & login dikelola terpusat di DigiTrans.');
        }

        $data = $this->validated($request);
        $data['password'] = $data['password'] ?? 'password';
        $pegawai = User::create($data);

        AuditService::catat('create', 'Pegawai', "Menambah pegawai {$pegawai->name} ({$pegawai->username}).", User::class, $pegawai->id);

        return redirect()->route('pegawai.index')->with('sukses', "Pegawai {$pegawai->name} berhasil ditambahkan.");
    }

    /** Jalankan sinkronisasi manual dari DigiTrans (tombol di halaman Pegawai). */
    public function sinkronDigitrans(Request $request)
    {
        if (! DigitransService::aktif()) {
            return back()->with('gagal', 'Integrasi DigiTrans belum aktif.');
        }

        $stat = DigitransService::sinkronPegawai();
        AuditService::catat('digitrans-sync', 'Pegawai', sprintf(
            'Sinkron manual dari halaman Pegawai: %d diproses, %d dibuat, %d diperbarui, %d dinonaktifkan, %d gagal.',
            $stat['diproses'], $stat['dibuat'], $stat['diperbarui'], $stat['dinonaktifkan'], $stat['gagal']
        ));

        return back()->with('sukses', sprintf(
            'Sinkron DigiTrans selesai: %d diproses · %d baru · %d diperbarui · %d dinonaktifkan · %d gagal.',
            $stat['diproses'], $stat['dibuat'], $stat['diperbarui'], $stat['dinonaktifkan'], $stat['gagal']
        ));
    }

    public function edit(User $pegawai)
    {
        $this->cekKewenangan(request()->user(), $pegawai);

        return view('pegawai.form', [
            'pegawai' => $pegawai,
            'unitList' => $this->unitList(),
            'atasanList' => $this->atasanList(),
            'roleList' => User::ROLES,
        ]);
    }

    public function update(Request $request, User $pegawai)
    {
        $this->cekKewenangan($request->user(), $pegawai);
        $data = $this->validated($request, $pegawai);
        if (empty($data['password'])) {
            unset($data['password']);
        }

        // Akun tertaut DigiTrans: identitas (nama/NIP/username/email/password)
        // dikunci — dikelola terpusat di DigiTrans agar tidak dobel data.
        $pesanInfo = null;
        if ($pegawai->digitrans_id && DigitransService::aktif()) {
            unset($data['name'], $data['nip'], $data['username'], $data['email'], $data['password']);
            $pesanInfo = 'Identitas & login akun ini dikelola DigiTrans — hanya data kinerja/atribut yang diperbarui.';
        }

        $pegawai->update($data);

        AuditService::catat('update', 'Pegawai', "Mengubah data pegawai {$pegawai->name}.", User::class, $pegawai->id);

        return redirect()->route('pegawai.index')->with('sukses', "Data {$pegawai->name} diperbarui.")->with('info', $pesanInfo);
    }

    public function destroy(User $pegawai)
    {
        $this->cekKewenangan(request()->user(), $pegawai);
        abort_if($pegawai->id === request()->user()->id, 403, 'Tidak dapat menonaktifkan akun sendiri.');

        if ($pegawai->digitrans_id && DigitransService::aktif()) {
            return back()->with('gagal', "Akun {$pegawai->name} bersumber dari DigiTrans — nonaktifkan/ hapus dari DigiTrans, lalu sinkronkan. Riwayat kinerjanya tetap tersimpan.");
        }

        $pegawai->update(['is_aktif' => false]);
        AuditService::catat('nonaktif', 'Pegawai', "Menonaktifkan pegawai {$pegawai->name}.", User::class, $pegawai->id);

        return redirect()->route('pegawai.index')->with('sukses', "Akun {$pegawai->name} dinonaktifkan.");
    }

    public function resetPassword(User $pegawai)
    {
        $this->cekKewenangan(request()->user(), $pegawai);

        if ($pegawai->digitrans_id && DigitransService::aktif()) {
            return back()->with('gagal', "Password {$pegawai->name} dikelola DigiTrans — ubah passwordnya di DigiTrans, lalu sinkronkan agar ikut ke TransKinerja.");
        }

        $pegawai->update(['password' => 'password']);
        AuditService::catat('reset_password', 'Pegawai', "Reset password {$pegawai->username}.", User::class, $pegawai->id);

        return back()->with('sukses', "Password {$pegawai->name} direset menjadi: password");
    }

    private function validated(Request $request, ?User $pegawai = null): array
    {
        $unique = Rule::unique('users');
        $pegawai ? $unique->ignore($pegawai->id) : $unique;

        return $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'nip' => ['nullable', 'string', 'max:30', Rule::unique('users')->ignore($pegawai?->id)],
            'username' => ['required', 'string', 'max:60', 'alpha_dash', Rule::unique('users')->ignore($pegawai?->id)],
            'email' => ['nullable', 'email', Rule::unique('users')->ignore($pegawai?->id)],
            'password' => [$pegawai ? 'nullable' : 'nullable', Password::min(6)],
            'role' => ['required', Rule::in(array_keys(User::ROLES))],
            'pangkat_golongan' => ['nullable', 'string', 'max:80'],
            'jabatan' => ['nullable', 'string', 'max:150'],
            'unit_kerja_id' => ['required', 'exists:unit_kerja,id'],
            'atasan_id' => ['nullable', 'exists:users,id', Rule::notIn([$pegawai?->id])],
            'status_pegawai' => ['nullable', 'string', 'max:20'],
            'jenis_kelamin' => ['nullable', 'string', 'max:15'],
            'tempat_lahir' => ['nullable', 'string', 'max:60'],
            'tanggal_lahir' => ['nullable', 'date'],
            'agama' => ['nullable', 'string', 'max:20'],
            'alamat' => ['nullable', 'string', 'max:190'],
            'no_telp' => ['nullable', 'string', 'max:30'],
            'pendidikan_terakhir' => ['nullable', 'string', 'max:190'],
            'level_pendidikan' => ['nullable', 'string', 'max:10'],
            'tmt_jabatan' => ['nullable', 'date'],
            'kenaikan_pangkat' => ['nullable', 'date'],
            'batas_usia_pensiun' => ['nullable', 'date'],
            'kemampuan_renang' => ['nullable', 'string', 'max:20'],
            'kemampuan_inggris' => ['nullable', 'string', 'max:20'],
        ], [
            'username.unique' => 'Username sudah digunakan.',
            'nip.unique' => 'NIP sudah terdaftar.',
            'unit_kerja_id.required' => 'Unit kerja wajib dipilih.',
        ]);
    }

    private function cekKewenangan(User $user, User $target): void
    {
        if (! $user->isAdminPusat() && $target->unit_kerja_id !== $user->unit_kerja_id) {
            abort(403, 'Pegawai di luar unit kerja Anda.');
        }
    }

    // ---------- Import Excel (format Dashboard OSDMRB / Digitrans) ----------

    /** Unduh template Excel format import pegawai. */
    public function template()
    {
        $sheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $ws = $sheet->getActiveSheet();
        $ws->setTitle('Data Pegawai');

        $header = ['NO', 'NAMA', 'NIP', 'STATUS', 'Nama Jabatan', 'Level', 'Es. II', 'Es. I', 'Es. III',
            'Level Eselon', 'Level Fungsional', 'TMT JABATAN', 'PANGKAT', 'GOLONGAN', 'GOL', 'TMT GOL',
            'KENAIKAN PANGKAT', 'TMT CPNS', 'MASA KERJA', 'TMT PNS', 'TMT KGB', 'Masa Kerja Saat KGB',
            'KGB BERIKUTNYA', 'PENDIDIKAN TERAKHIR 1', 'PENDIDIKAN TERAKHIR 2', 'PENDIDIKAN TERAKHIR 3',
            'LEVEL PENDIDIKAN', 'SLTA', 'D3', 'S1', 'S2', 'S3', 'TEMPAT LAHIR', 'TANGGAL LAHIR', 'Usia',
            'BATAS USIA PENSIUN', 'JENIS KELAMIN', 'AGAMA', 'ALAMAT', 'EMAIL', 'NO TLP', 'NO KTP',
            'KEMAMPUAN RENANG', 'KEMAMPUAN BAHASA INGGRIS'];

        foreach ($header as $i => $h) {
            $kol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i + 1);
            $ws->setCellValue($kol.'1', $h);
        }
        $ws->getStyle('A1:'.\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($header)).'1')
            ->getFont()->setBold(true);
        $ws->getStyle('A1:'.\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($header)).'1')
            ->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setRGB('EEF3FA');
        $ws->freezePane('A2');

        $contoh = [
            [1, 'Contoh Nama Pegawai, S.E., M.M.', '196911161996031001', 'PNS', 'Analis Kebijakan', 'Fungsional',
                'Biro Sumber Daya Manusia', 'Sekretariat Jenderal Kementerian Transmigrasi', '-',
                'IV', '-', '45915', 'Pembina Utama Madya', '(IV.d)', 'IV', '45383', '46844', '35125',
                '30 tahun 6 bulan', '35490', '', '', '', 'S.2 Magister Manajemen, Universitas A, 2003',
                'S.1 Manajemen, Universitas B, 1994', '', 'S2', '', '', '', '', '', 'Jakarta', 25523, '56', 47453,
                'Laki-laki', 'Islam', 'Jl. Contoh No. 1 Jakarta', 'pegawai@transmigrasi.go.id', '081200000001', '3174101611690004',
                'Mahir', 'Menengah'],
            [2, 'Contoh Nama Lain, S.Tr.', '199108182025212047', 'PPPK', 'Penata Layanan Operasional', 'PPPK',
                'Biro Sumber Daya Manusia', 'Sekretariat Jenderal Kementerian Transmigrasi', '-',
                'PPPK Fungsional Umum', 'PPPK Umum', '46000', '-', '(IX)', 'IX', '45839', '47300', '46266',
                '0 tahun 0 bulan', '46631', '', '', '', 'S.1 Pariwisata, Sekolah Tinggi Pariwisata X', '', '', 'S1', '', '', '', '', '',
                'Bandung', 33468, '35', 54667, 'Perempuan', 'Islam', '', 'pegawai2@transmigrasi.go.id', '', '',
                'Pemula', 'Mahir'],
        ];
        foreach ($contoh as $r => $row) {
            foreach ($row as $c => $val) {
                $ws->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c + 1).($r + 2), $val);
            }
        }
        $ws->getColumnDimension('B')->setWidth(32);
        $ws->getColumnDimension('C')->setWidth(20);
        $ws->getColumnDimension('E')->setWidth(28);
        $ws->getColumnDimension('G')->setWidth(30);
        $ws->getColumnDimension('H')->setWidth(34);
        $ws->getColumnDimension('Y')->setWidth(40);
        $ws->getColumnDimension('AM')->setWidth(40);

        $berkasTmp = tempnam(sys_get_temp_dir(), 'tpl-pegawai').'.xlsx';
        (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($sheet))->save($berkasTmp);

        return response()->download(
            $berkasTmp,
            'Template-Import-Pegawai-TransKinerja.xlsx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']
        )->deleteFileAfterSend(true);
    }

    /** Import pegawai dari file Excel format Dashboard OSDMRB / Digitrans. */
    public function import(Request $request)
    {
        $request->validate([
            'berkas' => ['required', 'file', 'max:10240', 'mimes:xlsx,xls,csv'],
        ], [
            'berkas.required' => 'Pilih berkas Excel terlebih dahulu.',
            'berkas.mimes' => 'Berkas harus berformat .xlsx / .xls / .csv.',
            'berkas.max' => 'Ukuran berkas maksimal 10 MB.',
        ]);

        $user = $request->user();

        try {
            $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($request->file('berkas')->getRealPath());
            $reader->setReadDataOnly(true);
            $sheet = $reader->load($request->file('berkas')->getRealPath())->getActiveSheet();
        } catch (\Throwable $e) {
            return back()->with('gagal', 'Berkas tidak dapat dibaca sebagai Excel: '.$e->getMessage());
        }

        $rows = $sheet->toArray(null, true, true, true);

        // 1) Cari baris header (kolom berisi NIP & NAMA) — toleran terhadap kop/tabel atas.
        //    Label dibakukan: isi kurung "(…)" dibuang (mis. "JENIS KELAMIN (L/P)" → jeniskelamin),
        //    lalu spasi & titik dibuang — cocok dengan header format Dashboard/Digitrans.
        $bakuLabel = fn ($t) => strtolower(preg_replace('/[\s.]+/u', '', preg_replace('/\(.*?\)/u', '', trim((string) $t))));

        $headerIdx = null;
        $kolom = [];
        foreach (array_slice($rows, 0, 12, true) as $idx => $row) {
            $norm = [];
            foreach ($row as $key => $val) {
                $label = $bakuLabel($val);
                if ($label !== '') {
                    $norm[$label] = $key;
                }
            }
            if (isset($norm['nip'], $norm['nama'])) {
                $headerIdx = $idx;
                $kolom = $norm;
                break;
            }
        }
        if ($headerIdx === null) {
            return back()->with('gagal', 'Header kolom NIP/NAMA tidak ditemukan. Gunakan template import agar format sesuai.');
        }

        $k = fn (string ...$kandidat) => collect($kandidat)
            ->map(fn ($n) => $bakuLabel($n))
            ->filter(fn ($n) => isset($kolom[$n]))
            ->map(fn ($n) => $kolom[$n])
            ->first();

        $cNama = $k('NAMA', 'NAMAPEGAWAI', 'NAMALENGKAP');
        $cNip = $k('NIP', 'NIK');
        $cStatus = $k('STATUS', 'STATUSPEGAWAI');
        $cJabatan = $k('NAMAJABATAN', 'JABATAN');
        $cEs2 = $k('ESII', 'UNITKERJA', 'UNIT');
        $cEs1 = $k('ESI');
        $cEs3 = $k('ESIII');
        $cEselon = $k('LEVELESELON', 'ESELON');
        $cFungsional = $k('LEVELFUNGSIONAL', 'FUNGSIONAL');
        $cTmtJabatan = $k('TMTJABATAN', 'TMTJABATAN');
        $cPangkat = $k('PANGKAT');
        $cGolongan = $k('GOLONGAN', 'GOL');
        $cKenaikan = $k('KENAIKANPANGKAT', 'KENAIKANPANGKATTERAKHIR');
        $cPendidikan = $k('PENDIDIKANTERAKHIR1', 'PENDIDIKANTERAKHIR');
        $cLevelPend = $k('LEVELPENDIDIKAN');
        $cTempatLahir = $k('TEMPATLAHIR');
        $cTglLahir = $k('TANGGALLAHIR');
        $cBup = $k('BATASUSIAPENSIUN', 'BUP');
        $cJK = $k('JENISKELAMIN', 'JK');
        $cAgama = $k('AGAMA');
        $cAlamat = $k('ALAMAT');
        $cEmail = $k('EMAIL');
        $cTelp = $k('NOTLP', 'NOTELEPON', 'HP');
        $cRenang = $k('KEMAMPUANRENANG', 'RENANG');
        $cInggris = $k('KEMAMPUANBAHASAINGGRIS', 'KEMAMPUANINGGRIS', 'BAHASAINGGRIS', 'INGGRIS');

        $ambil = function (array $row, $col) {
            if (! $col || ! array_key_exists($col, $row) || $row[$col] === null) {
                return null;
            }
            $raw = $row[$col];
            // Sel numerik (mis. NIP/telepon tersimpan sebagai angka) — hindari notasi ilmiah 1.96E+17
            $v = trim(is_float($raw) ? sprintf('%.0f', $raw) : (string) $raw);

            return ($v === '' || $v === '-') ? null : $v;
        };

        // Potong nilai agar tidak melebihi lebar kolom database
        $potong = fn ($v, int $maks) => $v ? mb_substr($v, 0, $maks) : null;

        $tanggalDariSel = function ($val) {
            if (! $val || (is_string($val) && trim($val) === '')) {
                return null;
            }
            if (is_numeric($val)) { // serial Excel, mis. 45915
                try {
                    return \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float) $val)->format('Y-m-d');
                } catch (\Throwable) {
                    return null;
                }
            }
            // Format fleksibel: 12/1/2029 · 01 April 2028 · 2024-01-31 · 01-04-2028 · 1 Jan 2029
            $teks = trim((string) $val);
            foreach ([['j F Y', 'id'], ['j F Y', 'en'], ['j M Y', 'id'], ['j M Y', 'en'], ['j F, Y', 'id']] as [$fmt, $lokale]) {
                try {
                    return \Carbon\Carbon::createFromLocaleFormat($fmt, $lokale, $teks)->format('Y-m-d');
                } catch (\Throwable) {
                    continue;
                }
            }
            foreach (['j/n/Y', 'j-n-Y', 'Y-m-d', 'j/n/y', 'j.n.Y'] as $fmt) {
                try {
                    return \Carbon\Carbon::createFromFormat($fmt, $teks)->format('Y-m-d');
                } catch (\Throwable) {
                    continue;
                }
            }

            return null;
        };

        // Normalisasi nilai master: gunakan helper statis di model User (dipakai bersama
        // oleh import, filter & migrasi pembersihan data lama agar hasilnya konsisten).

        // Berkas besar (ratusan–ribuan baris): longgarkan batas waktu & memori bila diizinkan server
        try { @set_time_limit(600); } catch (\Throwable) {}
        try { @ini_set('memory_limit', '512M'); } catch (\Throwable) {}

        DB::beginTransaction();
        $tambah = $perbarui = 0;
        $gagal = [];

        // ---------- Preload data SEKALI (hindari query per baris → cepat & tidak timeout) ----------
        // Kunci unit dinormalisasi (huruf kecil + spasi dirapikan) supaya perbedaan
        // kapitalisasi pada nama unit Excel ("Di" vs "di") tak menciptakan unit kembar.
        $kunciUnit = fn (?string $nama) => $nama ? mb_strtolower(preg_replace('/\s+/u', ' ', trim($nama))) : '';
        $unitCache = UnitKerja::where('is_aktif', true)
            ->pluck('id', 'nama')
            ->mapWithKeys(fn ($id, $nama) => [$kunciUnit($nama) => $id]);   // nama baku unit => id
        $kodeTerpakai = UnitKerja::pluck('kode')->flip();               // set kode unit
        $usersByNip = User::whereNotNull('nip')->get()->keyBy('nip');   // model pegawai lama
        $emailPemilik = User::whereNotNull('email')->pluck('nip', 'email'); // email => nip pemilik
        $usernameTerpakai = User::pluck('username')->flip();            // set username
        $nipDiproses = [];                                             // NIP yang sudah diproses berkas ini
        $hashPassword = bcrypt('password');                            // hash SEKALI untuk semua akun baru
        $barisBaru = [];                                              // akun baru → sisipkan massal

        foreach ($rows as $noBaris => $row) {
            if ($noBaris <= $headerIdx) {
                continue; // lewati baris header/kop di atasnya
            }
            $nama = $ambil($row, $cNama);
            $nip = $ambil($row, $cNip);
            $nip = $nip ? preg_replace('/\D+/', '', $nip) : null; // buang spasi/format teks

            if (! $nama && ! $nip) {
                continue; // baris kosong
            }
            if (! $nama || ! $nip || strlen($nip) < 8) {
                $gagal[] = "Baris {$noBaris}: nama/NIP tidak valid";
                continue;
            }
            if (isset($nipDiproses[$nip])) {
                $gagal[] = "Baris {$noBaris} ({$nama}): NIP duplikat dalam berkas";
                continue;
            }

            try {
                // Unit kerja: Es. II → Es. I → Es. III; admin unit dipatok ke unitnya sendiri
                $namaUnit = $ambil($row, $cEs2) ?: $ambil($row, $cEs1) ?: $ambil($row, $cEs3);
                $unitId = null;
                if ($user->isAdminPusat()) {
                    if ($namaUnit) {
                        $kunci = $kunciUnit($namaUnit);
                        $unitId = $unitCache[$kunci] ?? null;
                        if (! $unitId) { // unit baru dibuat otomatis
                            $kode = $this->kodeUnitOtomatis($namaUnit, $kodeTerpakai);
                            $unitId = UnitKerja::create(['nama' => $namaUnit, 'is_aktif' => true, 'kode' => $kode])->id;
                            $unitCache[$kunci] = $unitId;
                        }
                    }
                } else {
                    $unitId = $user->unit_kerja_id;
                }
                if (! $unitId) {
                    $gagal[] = "Baris {$noBaris} ({$nama}): unit kerja tidak diketahui";
                    continue;
                }

                // Pangkat/golongan digabung: "Pembina (IV/a)" atau PPPK "(IX)" → "IX"
                $pangkat = $ambil($row, $cPangkat);
                $golongan = $ambil($row, $cGolongan);
                $golBersih = $golongan ? preg_replace('/[^IVXivx0-9a-z\/\.]+/i', '', $golongan) : null;
                $pangkatGol = trim(($pangkat && $pangkat !== '-' ? $pangkat.' ' : '').($golBersih && ! str_contains((string) $pangkat, $golBersih) ? "({$golBersih})" : '')) ?: null;

                $pendidikan = $ambil($row, $cPendidikan) ?: $ambil($row, $cLevelPend);

                // Status baku: PNS / CPNS / PPPK (nama lain dibiarkan apa adanya)
                $status = User::normStatusPegawai($ambil($row, $cStatus));

                $tglLahir = $tanggalDariSel($row[$cTglLahir] ?? null);

                // BUP: pakai kolom BATAS USIA PENSIUN bila terisi; kosong → hitung otomatis
                // (60 th Eselon I/II & Fungsional Madya/Utama, selain itu 58 th)
                $bup = $tanggalDariSel($row[$cBup] ?? null)
                    ?: User::hitungBup($tglLahir, $ambil($row, $cEselon), $ambil($row, $cFungsional));

                $data = [
                    'name' => $potong($nama, 150),
                    'jabatan' => $potong($ambil($row, $cJabatan), 150),
                    'pangkat_golongan' => $potong($pangkatGol, 80),
                    'unit_kerja_id' => $unitId,
                    'status_pegawai' => $potong($status, 20),
                    'tmt_jabatan' => $tanggalDariSel($row[$cTmtJabatan] ?? null),
                    'kenaikan_pangkat' => $tanggalDariSel($row[$cKenaikan] ?? null),
                    'jenis_kelamin' => User::normJenisKelamin($ambil($row, $cJK)),
                    'tempat_lahir' => $potong($ambil($row, $cTempatLahir), 60),
                    'tanggal_lahir' => $tglLahir,
                    'batas_usia_pensiun' => $bup,
                    'agama' => $potong($ambil($row, $cAgama), 20),
                    'alamat' => $potong($ambil($row, $cAlamat), 190),
                    'no_telp' => $potong($ambil($row, $cTelp), 30),
                    'pendidikan_terakhir' => $pendidikan ? mb_substr($pendidikan, 0, 190) : null,
                    'level_pendidikan' => User::normLevelPendidikan($ambil($row, $cLevelPend)),
                    'kemampuan_renang' => User::normKemampuan($ambil($row, $cRenang)),
                    'kemampuan_inggris' => User::normKemampuan($ambil($row, $cInggris)),
                    'is_aktif' => true,
                ];

                // Email unik opsional — bila dipakai pegawai lain, kosongkan (cek dari cache, tanpa query)
                $email = $ambil($row, $cEmail);
                if ($email && ($pemilik = $emailPemilik[$email] ?? null) !== null && $pemilik !== $nip) {
                    $email = null;
                }
                $data['email'] = $email ?: null;
                if ($email) {
                    $emailPemilik[$email] = $nip; // tandai terpakai untuk baris berikutnya
                }

                $pegawai = $usersByNip->get($nip);
                if ($pegawai) {
                    if (empty($data['email'])) {
                        unset($data['email']); // jangan menimpa email lama bila kolom kosong
                    }
                    // Kolom yang kosong pada Excel tidak menghapus data lama
                    foreach ($data as $kunci => $isi) {
                        if ($isi === null && $pegawai->{$kunci} !== null) {
                            unset($data[$kunci]);
                        }
                    }
                    // Akun tertaut DigiTrans: identitas tetap dikunci (hanya atribut diperkaya)
                    if ($pegawai->digitrans_id && DigitransService::aktif()) {
                        unset($data['name'], $data['email'], $data['is_aktif']);
                    }
                    $pegawai->fill($data)->save();
                    $perbarui++;
                    $nipDiproses[$nip] = true;
                } elseif (DigitransService::aktif()) {
                    // DigiTrans = master pegawai: jangan ciptakan akun baru dari Excel
                    $gagal[] = "Baris {$noBaris} ({$nama}): dilewati — akun baru dikelola DigiTrans";
                    $nipDiproses[$nip] = true;
                } else {
                    // username unik: coba NIP, bila dipakai tambahkan angka (cek dari cache)
                    $username = $nip;
                    $i = 1;
                    while (isset($usernameTerpakai[$username])) {
                        $username = $nip.($i++);
                    }
                    $usernameTerpakai[$username] = true;

                    $barisBaru[] = $data + [
                        'nip' => $nip,
                        'username' => $username,
                        'password' => $hashPassword, // hash dipakai bersama — jauh lebih cepat
                        'role' => User::ROLE_PEGAWAI,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                    $tambah++;
                    $nipDiproses[$nip] = true;
                }
            } catch (\Throwable $e) {
                $gagal[] = "Baris {$noBaris} ({$nama}): {$e->getMessage()}";
            }
        }

        // Sisipkan akun baru secara massal (chunk) — bukan satu-per-satu
        foreach (array_chunk($barisBaru, 50) as $chunk) {
            DB::table('users')->insert($chunk);
        }

        DB::commit();
        AuditService::catat('create', 'Pegawai', "Import Excel pegawai: {$tambah} baru, {$perbarui} diperbarui, ".count($gagal).' gagal.');

        return back()->with('hasil_import', [
            'tambah' => $tambah,
            'perbarui' => $perbarui,
            'gagal' => array_slice($gagal, 0, 25),
            'total_gagal' => count($gagal),
            'berkas' => $request->file('berkas')->getClientOriginalName(),
        ]);
    }

    /** Kode unit kerja otomatis (akronim nama, unik, maks 30 karakter) untuk unit hasil import.
     *  Daftar kode terpakai diteruskan dari cache koleksi — tanpa query per unit. */
    private function kodeUnitOtomatis(string $nama, \Illuminate\Support\Collection $terpakai): string
    {
        $kata = collect(preg_split('/[\s,\/&()\-]+/u', $nama, -1, PREG_SPLIT_NO_EMPTY))
            ->reject(fn ($w) => in_array(mb_strtolower($w), ['dan', 'dari', 'serta', 'atau', 'untuk', 'pada', 'the', 'of']))
            ->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))
            ->implode('');

        $kode = mb_substr($kata !== '' ? $kata : 'UNIT', 0, 27);
        while ($terpakai->has($kode)) {
            $kode = mb_substr($kode, 0, 27).'-'.random_int(100, 999);
        }
        $terpakai[$kode] = true;

        return $kode;
    }

    private function unitList()
    {
        $user = request()->user();

        return $user->isAdminPusat()
            ? UnitKerja::where('is_aktif', true)->orderBy('nama')->get()
            : UnitKerja::where('id', $user->unit_kerja_id)->get();
    }

    private function atasanList()
    {
        $user = request()->user();

        return User::whereIn('role', [User::ROLE_ATASAN, User::ROLE_ADMIN_UNIT, User::ROLE_SUPERADMIN])
            ->where('is_aktif', true)
            ->when(! $user->isAdminPusat(), fn ($q) => $q->where('unit_kerja_id', $user->unit_kerja_id))
            ->orderBy('name')->get();
    }
}
