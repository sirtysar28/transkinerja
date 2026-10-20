# TransKinerja — Aplikasi Kinerja ASN Kementerian Transmigrasi RI

Aplikasi **pencatatan aktivitas harian (Log Book), pemantauan jam kerja, dan rekapitulasi kinerja ASN** Kementerian Transmigrasi Republik Indonesia, dibangun sesuai dokumen *Workflow Aplikasi TransKinerja*.

> **Tagline:** *Kinerja tercatat, pelayanan meningkat.*

---

## Teknologi

| Komponen | Teknologi |
|---|---|
| Backend | Laravel 11 · PHP 8.2+ |
| Frontend | Blade + Tailwind CSS (aset sudah di-build, hosting tidak wajib menjalankan build) |
| Database | MySQL / MariaDB (dapat juga SQLite untuk pengujian) |
| Ekspor Excel | PhpSpreadsheet (format **Formulir Laporan Bulanan** sesuai lampiran Lapkin) |
| Cetak PDF | Tampilan cetak siap-print (browser → Simpan sebagai PDF) |

## Fitur Utama (sesuai Workflow)

1. **Login modern** — branding Kementerian di kiri, form di kanan, tombol intip password, **CAPTCHA 5 karakter sisi server** (berlaku 10 menit, sekali pakai), throttling percobaan login, login via **username atau NIP**. **Verifikasi dua langkah (OTP)** opsional: setelah password benar, kode OTP dikirim ke email — bisa **ON/OFF** oleh Superadmin di *Pengaturan → OTP & Keamanan* (panjang kode, masa berlaku, dan batas percobaan dapat diatur; kode disimpan hanya sebagai hash SHA-256).
2. **Dashboard per peran** — Pegawai · Atasan · Admin Unit · Superadmin dengan ringkasan, tren aktivitas, dan status pelaporan.
3. **Log Book harian** — tanggal, kegiatan, output, pengguna/penerima, **jenis tugas KS/KP/KT** (multi-pilih), jam mulai–selesai (durasi otomatis), **bukti dukung** (lampiran berkas), riwayat pengajuan.
4. **Alur persetujuan** — `Draft → Diajukan → Disetujui`, dengan `Perlu Revisi` + catatan atasan. **Laporan disetujui tidak dapat diedit langsung** — hanya melalui mekanisme *buka kembali* oleh Superadmin (pejabat berwenang), lengkap dengan **audit trail**.
5. **Presensi & jam kerja** — clock-in/clock-out harian + rekap total jam kerja.
6. **Rekapitulasi & laporan** — harian/bulanan, filter pegawai/jenis tugas/output/status, **cetak Formulir Laporan Bulanan** identik file Excel Lapkin, **ekspor Excel (.xlsx)**.
7. **Monitoring & analitik** — perbandingan kinerja antar pegawai & antar unit, kelengkapan pelaporan (% hari terisi), rekap jam kerja.
8. **Pengelolaan data** — pegawai ASN (NIP, pangkat/golongan, jabatan, atasan, unit), unit kerja berjenjang, hak akses 4 level, **filter dropdown multi-centang** (unit kerja, status, peran, jenis kelamin, pendidikan, kemampuan renang & bahasa Inggris).
9. **Import Excel format Data Dashboard** — berkas *Data Dashboard.xlsx* instansi diunggah langsung tanpa template; NIP ada → diperbarui, NIP baru → ditambahkan; unit kerja (Es. II/I) otomatis dibuat; tanggal fleksibel (`12/1/2029`, `01 April 2028`, `2024-01-31`); **BUP kosong dihitung otomatis** (60 th Eselon I/II & Fungsional Madya, selain itu 58 th); kolom tambahan **Kemampuan Renang** & **Kemampuan Bahasa Inggris**; baris tidak valid dilewati & dilaporkan.
10. **Pengaturan** — periode pelaporan (buka/tutup pengajuan), referensi jenis tugas & output, **audit log**, profil & ganti password.
11. **Manajemen User (menu khusus Superadmin)** — lihat siapa saja yang **punya akses login** (aktif/nonaktif, login terakhir, tanpa email), **mapping akses login** (username, email tujuan OTP, password, peran/level akses), serta cabut/aktifkan akses dengan pengaman anti-lockout superadmin terakhir.
11. **UX pemerintahan modern** — sidebar dapat diciutkan jadi ikon (persisten) & drawer di mobile, header dengan pencarian global + notifikasi + profil + pemilih periode, tabel dengan filter/pagination, toast, empty state, konfirmasi aksi, mode terang, **PWA-ready** (dapat dipasang di layar utama).
12. **Keamanan** — validasi server, pembatasan percobaan login, hak akses diperiksa di backend (middleware `role:` + Policy), sesi aman.

## Pembagian Hak Akses

| Level | Peran | Ringkasan Kewenangan |
|---|---|---|
| 1 | **Pegawai ASN** | Mengelola Log Book sendiri, bukti dukung, mengajukan & memperbaiki revisi, unduh laporan pribadi |
| 2 | **Atasan / Pejabat Penilai** | Memeriksa, merevisi, menyetujui laporan bawahan; memantau tim |
| 3 | **Admin Unit Kerja** | Kelola pegawai unit, status pelaporan & rekap unit |
| 4 | **Superadmin** | Referensi organisasi, hak akses, **manajemen user & akses login**, periode & format pelaporan, data lintas unit, audit log, buka kembali laporan |

## Menjalankan Aplikasi

```bash
# 1. Konfigurasi .env (default: MySQL database "transkinerja")
DB_CONNECTION=mysql
DB_DATABASE=transkinerja
DB_USERNAME=root
DB_PASSWORD=

# 2. Migrasi + data contoh (unit kerja, pegawai, log Agustus 2026 dari file Excel)
php artisan migrate --seed

# 3. Jalankan
php artisan serve
# buka http://127.0.0.1:8000
```

> Aset frontend **sudah ter-build** di `public/build`. Jika mengubah CSS/JS: `npm install && npm run build`.


## Struktur Penting

```
app/
├── Http/Controllers/     # Login (+OTP), Dashboard, LogBook, Presensi, Persetujuan,
│                         # Laporan (cetak & ekspor Excel), Monitoring, Pegawai,
│                         # UnitKerja, ManajemenUser, Pengaturan, Notifikasi,
│                         # Pencarian, Captcha
├── Http/Middleware/      # EnsureRole (RBAC backend)
├── Models/               # User, UnitKerja, LogKegiatan, LogLampiran, ApprovalLog,
│                         # Presensi, AuditLog, AppNotification, TaskType, OutputRef,
│                         # PeriodeLaporan, OtpCode
├── Policies/             # LogKegiatanPolicy (cakupan akses + penguncian status)
└── Services/             # CaptchaService (GD), OtpService (verifikasi 2 langkah),
                          # AuditService, NotifikasiService
database/migrations/      # skema lengkap
database/seeders/         # data contoh realistis (termasuk log dari file Excel)
resources/views/          # Blade UI (layout, auth, 8 modul, komponen)
public/images/            # logo & favicon Kementerian Transmigrasi
public/manifest.json      # PWA manifest
tests/Feature/            # 11 uji otomatis (login+captcha, alur persetujuan, RBAC, dll)
```

## Pengujian

```bash
php artisan test   # 36 test, 239 assertions
```

Mencakup: CAPTCHA salah/dibenarkan, login username & NIP, **alur OTP lengkap (ON/OFF, kode salah/kedaluwarsa, kirim ulang, hash tersimpan)**, **menu Manajemen User (halaman, mapping peran, cabut/aktifkan akses, anti-lockout superadmin, hanya superadmin)**, alur lengkap Log Book (draft → diajukan → revisi → setujui), larangan edit laporan disetujui/diajukan, RBAC semua modul, seluruh halaman, ekspor Excel, presensi.

## Catatan Kebijakan (dari dokumen workflow)

- **Definisi resmi kode KS, KP, KT** menunggu konfirmasi Biro SDM — saat ini ditandai di *Pengaturan → Referensi* dan dapat disesuaikan tanpa memengaruhi data.
- Keputusan bisnis **clock-in/out vs durasi mandiri** masih dibahas dengan Biro SDM — aplikasi mengakomodasi **keduanya** (presensi clock-in/out + durasi jam kerja pada Log Book), dan menu Presensi mudah dinonaktifkan bila kebijakan memilih durasi mandiri.
- Periode pelaporan mengatur jendela pengajuan; pengajuan ditolak otomatis saat periode ditutup (kecuali koreksi Superadmin).

### Catatan teknis PHP 8.5
File `vendor/laravel/framework/config/database.php` pada Laravel 11 masih memuat konstanta `PDO::MYSQL_ATTR_SSL_CA` yang *deprecated* di PHP 8.5. Patch kecil sudah diterapkan pada file vendor tersebut; jika menjalankan `composer install` ulang dan muncul notice deprecation, terapkan kembali (atau jalankan di PHP 8.2–8.4 yang resmi didukung).
# transkinerja
