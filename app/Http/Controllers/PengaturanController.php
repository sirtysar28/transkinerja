<?php

namespace App\Http\Controllers;

use App\Mail\NotifikasiEmail;
use App\Models\AuditLog;
use App\Models\OutputRef;
use App\Models\Pengaturan;
use App\Models\PeriodeLaporan;
use App\Models\TaskType;
use App\Models\User;
use App\Services\AuditService;
use App\Services\OtpService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;

class PengaturanController extends Controller
{
    /**
     * Satu halaman Pengaturan dengan tab: Profil, OTP & Keamanan (superadmin),
     * SMTP & Email, Periode, Referensi, dan Audit Log — tanpa berpindah halaman.
     * (Manajemen user & akses login kini menu tersendiri: Manajemen User.)
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $isSuperadmin = $user->role === User::ROLE_SUPERADMIN;

        $tab = $request->query('tab', 'profil');

        // Tab lama "akses" telah menjadi menu sendiri — arahkan penanda lama ke sana.
        if ($tab === 'akses') {
            return redirect()->route('manajemen-user.index');
        }

        $tabs = [
            'profil' => ['label' => 'Profil Saya', 'icon' => 'user'],
        ];
        if ($isSuperadmin) {
            $tabs['otp'] = ['label' => 'OTP & Keamanan', 'icon' => 'shield'];
            $tabs['smtp'] = ['label' => 'SMTP & Email', 'icon' => 'mail'];
            $tabs['periode'] = ['label' => 'Periode Pelaporan', 'icon' => 'calendar'];
            $tabs['referensi'] = ['label' => 'Referensi', 'icon' => 'list'];
            $tabs['audit'] = ['label' => 'Audit Log', 'icon' => 'file'];
        }

        $data = [
            'user' => $user,
            'tabAktif' => array_key_exists($tab, $tabs) ? $tab : 'profil',
            'tabs' => $tabs,
            'isSuperadmin' => $isSuperadmin,
            'periode' => null,
            'taskTypes' => null,
            'outputs' => null,
            'logs' => null,
            'modulList' => null,
            'filter' => $request->only(['q', 'modul']),
            'otp' => [
                'otp_aktif' => OtpService::aktif(),
                'otp_panjang' => OtpService::panjang(),
                'otp_masa_berlaku' => OtpService::masaBerlakuMenit(),
                'otp_maks_percobaan' => OtpService::maksPercobaan(),
                'smtp_siap' => (bool) Pengaturan::get('smtp_host'),
                'tanpaEmail' => 0,
            ],
            'smtp' => [
                'smtp_host' => Pengaturan::get('smtp_host'),
                'smtp_port' => Pengaturan::get('smtp_port', '587'),
                'smtp_enkripsi' => Pengaturan::get('smtp_enkripsi', 'tls'),
                'smtp_username' => Pengaturan::get('smtp_username'),
                'smtp_password_ada' => Pengaturan::get('smtp_password') !== null,
                'mail_from_address' => Pengaturan::get('mail_from_address', config('mail.from.address')),
                'mail_from_name' => Pengaturan::get('mail_from_name', config('mail.from.name')),
                'notifikasi_email_aktif' => Pengaturan::get('notifikasi_email_aktif') === '1',
            ],
        ];

        if ($isSuperadmin) {
            $data['periode'] = PeriodeLaporan::orderByDesc('tahun')->orderByDesc('bulan')->paginate(10)->withQueryString();
            $data['taskTypes'] = TaskType::orderBy('kode')->get();
            $data['outputs'] = OutputRef::orderBy('nama')->get();

            $q = $request->query('q');
            $modul = $request->query('modul');
            $data['logs'] = AuditLog::with('aktor')
                ->when($q, fn ($qq) => $qq->where(fn ($w) => $w
                    ->where('deskripsi', 'like', "%{$q}%")
                    ->orWhereHas('aktor', fn ($a) => $a->where('name', 'like', "%{$q}%"))))
                ->when($modul, fn ($qq) => $qq->where('modul', $modul))
                ->orderByDesc('created_at')
                ->paginate(10)->withQueryString();
            $data['modulList'] = AuditLog::select('modul')->distinct()->orderBy('modul')->pluck('modul');

            // Jumlah akun aktif tanpa email — dipakai peringatan pada tab OTP.
            $data['otp']['tanpaEmail'] = User::where('is_aktif', true)
                ->where(fn ($q) => $q->whereNull('email')->orWhere('email', ''))
                ->count();
        }

        return view('pengaturan.index', $data);
    }

    // ---------- Profil sendiri ----------
    public function profilUpdate(Request $request)
    {
        $user = $request->user();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['nullable', 'email', Rule::unique('users')->ignore($user->id)],
            'password' => ['nullable', 'confirmed', 'min:6'],
        ]);

        if (! empty($data['password'])) {
            $data['password'] = bcrypt($data['password']);
        } else {
            unset($data['password']);
        }
        $user->update($data);
        AuditService::catat('update', 'Pengaturan', 'Memperbarui profil / password sendiri.', User::class, $user->id);

        return back()->withFragment('tab-profil')->with('sukses', 'Profil berhasil diperbarui.');
    }

    // ---------- SMTP & notifikasi email ----------
    public function smtpStore(Request $request)
    {
        $d = $request->validate([
            'smtp_host' => ['required', 'string', 'max:190'],
            'smtp_port' => ['required', 'integer', 'between:1,65535'],
            'smtp_enkripsi' => ['required', 'in:tls,ssl,none'],
            'smtp_username' => ['nullable', 'string', 'max:190'],
            'smtp_password' => ['nullable', 'string', 'max:190'],
            'mail_from_address' => ['required', 'email', 'max:190'],
            'mail_from_name' => ['required', 'string', 'max:100'],
            'notifikasi_email_aktif' => ['nullable'],
        ]);

        $uid = $request->user()->id;
        Pengaturan::set('smtp_host', $d['smtp_host'], $uid);
        Pengaturan::set('smtp_port', (string) $d['smtp_port'], $uid);
        Pengaturan::set('smtp_enkripsi', $d['smtp_enkripsi'], $uid);
        Pengaturan::set('smtp_username', $d['smtp_username'] ?: null, $uid);
        // password kosong = pertahankan yang lama
        if (! empty($d['smtp_password'])) {
            Pengaturan::set('smtp_password', $d['smtp_password'], $uid);
        }
        Pengaturan::set('mail_from_address', $d['mail_from_address'], $uid);
        Pengaturan::set('mail_from_name', $d['mail_from_name'], $uid);
        Pengaturan::set('notifikasi_email_aktif', ($d['notifikasi_email_aktif'] ?? null) === '1' ? '1' : '0', $uid);

        AuditService::catat('update', 'Pengaturan', 'Memperbarui konfigurasi SMTP & notifikasi email.');

        return back()->withFragment('tab-smtp')->with('sukses', 'Konfigurasi SMTP berhasil disimpan.');
    }

    /** Kirim email uji coba HTML ke email milik superadmin yang sedang login. */
    public function smtpTest(Request $request)
    {
        $request->validate(['tujuan' => ['nullable', 'email']]);

        $tujuan = $request->input('tujuan') ?: $request->user()->email;
        if (! $tujuan) {
            return back()->withFragment('tab-smtp')->with('gagal', 'Akun Anda belum memiliki email — isi kolom tujuan uji coba.');
        }

        try {
            Mail::to($tujuan)->send(new NotifikasiEmail(
                judul: 'Email Uji Coba Berhasil',
                pesan: "Ini adalah email uji coba dari aplikasi TransKinerja. Jika Anda menerima email ini, konfigurasi SMTP sudah benar dan notifikasi akan dikirim dalam format HTML seperti ini.",
                tautan: '/dashboard',
                penerima: $request->user(),
            ));
            AuditService::catat('create', 'Pengaturan', "Mengirim email uji coba SMTP ke {$tujuan}.");

            return back()->withFragment('tab-smtp')->with('sukses', "Email uji coba terkirim ke {$tujuan}. Periksa kotak masuk (dan folder spam).");
        } catch (\Throwable $e) {
            return back()->withFragment('tab-smtp')->with('gagal', 'Gagal mengirim: '.$e->getMessage());
        }
    }

    // ---------- OTP & Keamanan (verifikasi dua langkah) ----------
    public function otpStore(Request $request)
    {
        $d = $request->validate([
            'otp_aktif' => ['nullable'],
            'otp_panjang' => ['required', 'integer', 'between:4,8'],
            'otp_masa_berlaku' => ['required', 'integer', 'between:1,60'],
            'otp_maks_percobaan' => ['required', 'integer', 'between:3,10'],
        ], [
            'otp_panjang.between' => 'Panjang kode OTP 4–8 digit.',
            'otp_masa_berlaku.between' => 'Masa berlaku kode 1–60 menit.',
            'otp_maks_percobaan.between' => 'Maksimal percobaan 3–10 kali.',
        ]);

        $aktif = ($d['otp_aktif'] ?? null) === '1';

        // Jaring keamanan: jangan izinkan OTP dinyalakan bila SMTP belum dikonfigurasi
        // atau akun superadmin sendiri belum punya email (risiko terkunci).
        if ($aktif) {
            if (! Pengaturan::get('smtp_host')) {
                return back()->withFragment('tab-otp')->with('gagal', 'Konfigurasikan SMTP terlebih dahulu pada tab SMTP & Email sebelum mengaktifkan OTP.');
            }
            $tanpaEmail = User::where('is_aktif', true)->where(fn ($q) => $q->whereNull('email')->orWhere('email', ''))->count();
            if ($tanpaEmail > 0 && ! $request->boolean('konfirmasi_tanpa_email')) {
                return back()->withFragment('tab-otp')->with('gagal',
                    "Masih ada {$tanpaEmail} akun aktif tanpa email — mereka tidak akan bisa login. Centang pernyataan konfirmasi atau lengkapi email mereka di menu Manajemen User.");
            }
        }

        $uid = $request->user()->id;
        Pengaturan::set('otp_aktif', $aktif ? '1' : '0', $uid);
        Pengaturan::set('otp_panjang', (string) $d['otp_panjang'], $uid);
        Pengaturan::set('otp_masa_berlaku', (string) $d['otp_masa_berlaku'], $uid);
        Pengaturan::set('otp_maks_percobaan', (string) $d['otp_maks_percobaan'], $uid);

        AuditService::catat('update', 'Pengaturan', 'Mengubah pengaturan OTP login: '.($aktif ? 'AKTIF' : 'nonaktif').
            " ({$d['otp_panjang']} digit, {$d['otp_masa_berlaku']} menit, maks {$d['otp_maks_percobaan']} percobaan).");

        return back()->withFragment('tab-otp')->with('sukses', 'Pengaturan OTP '.($aktif ? 'diaktifkan' : 'dinonaktifkan').'.');
    }

    // ---------- Periode pelaporan ----------
    public function periodeStore(Request $request)
    {
        $data = $request->validate([
            'bulan' => ['required', 'integer', 'min:1', 'max:12'],
            'tahun' => ['required', 'integer', 'min:2020', 'max:2100'],
            'tanggal_buka' => ['required', 'date'],
            'tanggal_tutup' => ['required', 'date', 'after_or_equal:tanggal_buka'],
            'is_aktif' => ['nullable', 'boolean'],
        ]);
        $data['is_aktif'] = ($data['is_aktif'] ?? null) == '1';

        $exists = PeriodeLaporan::where('bulan', $data['bulan'])->where('tahun', $data['tahun'])->exists();
        if ($exists) {
            return back()->withFragment('tab-periode')->with('gagal', 'Periode untuk bulan/tahun tersebut sudah ada.');
        }

        if ($data['is_aktif']) {
            PeriodeLaporan::where('is_aktif', true)->update(['is_aktif' => false]);
        }

        $periode = PeriodeLaporan::create($data);
        AuditService::catat('create', 'Pengaturan', "Menambah periode laporan {$periode->label()}.", PeriodeLaporan::class, $periode->id);

        return back()->withFragment('tab-periode')->with('sukses', 'Periode laporan ditambahkan.');
    }

    public function periodeToggle(PeriodeLaporan $periode)
    {
        if (! $periode->is_aktif) {
            PeriodeLaporan::where('is_aktif', true)->update(['is_aktif' => false]);
            $periode->update(['is_aktif' => true]);
        } else {
            $periode->update(['is_aktif' => false]);
        }
        AuditService::catat('update', 'Pengaturan', "Mengubah status periode {$periode->label()}.");

        return back()->withFragment('tab-periode')->with('sukses', 'Status periode diperbarui.');
    }

    // ---------- Referensi: jenis tugas & output ----------
    public function taskTypeStore(Request $request)
    {
        $data = $request->validate([
            'kode' => ['required', 'string', 'max:10', 'unique:task_types,kode'],
            'nama' => ['required', 'string', 'max:100'],
            'keterangan' => ['nullable', 'string', 'max:255'],
        ]);
        $t = TaskType::create($data + ['is_aktif' => true]);
        AuditService::catat('create', 'Pengaturan', "Menambah jenis tugas {$t->kode}.", TaskType::class, $t->id);

        return back()->withFragment('tab-referensi')->with('sukses', 'Jenis tugas ditambahkan.');
    }

    public function taskTypeToggle(TaskType $taskType)
    {
        $taskType->update(['is_aktif' => ! $taskType->is_aktif]);
        AuditService::catat('update', 'Pengaturan', "Mengubah status jenis tugas {$taskType->kode}.", TaskType::class, $taskType->id);

        return back()->withFragment('tab-referensi')->with('sukses', 'Status jenis tugas diperbarui.');
    }

    public function outputStore(Request $request)
    {
        $data = $request->validate(['nama' => ['required', 'string', 'max:100', 'unique:output_refs,nama']]);
        $o = OutputRef::create($data + ['is_aktif' => true]);
        AuditService::catat('create', 'Pengaturan', "Menambah referensi output {$o->nama}.", OutputRef::class, $o->id);

        return back()->withFragment('tab-referensi')->with('sukses', 'Referensi output ditambahkan.');
    }

    public function outputToggle(OutputRef $outputRef)
    {
        $outputRef->update(['is_aktif' => ! $outputRef->is_aktif]);
        AuditService::catat('update', 'Pengaturan', "Mengubah status output {$outputRef->nama}.", OutputRef::class, $outputRef->id);

        return back()->withFragment('tab-referensi')->with('sukses', 'Status output diperbarui.');
    }
}
