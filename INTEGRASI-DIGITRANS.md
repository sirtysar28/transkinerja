# Integrasi DigiTrans ↔ TransKinerja

> Status implementasi: **sudah siap pakai** (nonaktif secara bawaan — aman di-deploy
> kapan saja tanpa mengubah perilaku). Aktifkan hanya dengan mengubah `.env`.

## Konsep

Dua aplikasi berada di **satu server cPanel yang sama** (`triventk`), sehingga
TransKinerja dapat membaca database DigiTrans **secara langsung (read-only)**:

```
┌─────────────────────┐        ┌──────────────────────────┐
│  DigiTrans (Biro)   │        │  TransKinerja            │
│  DB: *_digitrans    │◄───────│  DB: *_transkinerja      │
│  users / pegawai    │  BACA  │  - login fallback        │
│                    │        │  - auto-provision akun   │
└─────────────────────┘        │  - sinkron data pegawai  │
                               └──────────────────────────┘
```

**Yang disepakati bersama (shared):** identitas login (username/NIP/email + password
DigiTrans sebagai kebenaran utama) dan data dasar pegawai (nama, email, jabatan, unit).

**Yang tetap milik TransKinerja:** role (pegawai/atasan/admin unit/superadmin),
relasi atasan–bawahan, `is_aktif`, log kinerja, presensi, dsb.

## Cara Kerja Login (setelah diaktifkan)

1. User submit username/NIP + password seperti biasa (CAPTCHA & throttle tetap jalan).
2. TransKinerja cek ke **DB lokal** dulu → bila cocok, langsung lolos (tanpa sentuh DigiTrans).
3. Bila user tidak ada / password lokal salah → verifikasi ke **DB DigiTrans**:
   - cocok → akun lokal **dibuat/diperbarui otomatis** (password lokal disalin dari
     DigiTrans), lanjut alur normal (OTP, audit `login-digitrans`, dst.)
   - tidak cocok → pesan error seperti biasa.
4. DigiTrans sedang down? Login akun yang **pernah login/seedsync** tetap bisa
   karena password lokal sudah tersalin.

## Langkah Aktivasi di cPanel

### 1. Cari tahu nama database & skema DigiTrans
`php artisan digitrans:cek` menampilkan kolom asli tabel DigiTrans sekaligus
validasi mapping otomatis.

**Skema terkonfirmasi** (`triventk_dashboard_osdmrb`, tabel `users`):
`id, name, email, password, employee_id (≈ NIP), is_active` —
tanpa kolom `username`, `jabatan`, `unit_kerja`. Mapping bawaan sudah sesuai;
login pegawai DigiTrans memakai **email atau NIP (employee_id)**.

### 2. Buat user MySQL READ-ONLY khusus (JANGAN pakai user utama DigiTrans)
cPanel → **MySQL Databases** → buat user baru, mis. `triventk_tkro`, lalu
*Add User To Database* → pilih DB DigiTrans → centang **SELECT saja**.

### 3. Isi `.env` TransKinerja
```env
DIGITRANS_AKTIF=true
DIGITRANS_DB_HOST=127.0.0.1
DIGITRANS_DB_DATABASE=triventk_digitrans      # sesuaikan
DIGITRANS_DB_USERNAME=triventk_tkro           # read-only user
DIGITRANS_DB_PASSWORD=•••••
# Bila nama kolom DigiTrans beda, sesuaikan (contoh nama → full_name):
DIGITRANS_KOLOM_NAMA=name
DIGITRANS_KOLOM_UNIT=unit_kerja
```

### 4. Migrasi + uji
Terminal cPanel (atau via `php artisan` cron):
```
php artisan migrate              # tambah kolom digitrans_id
php artisan config:clear
php artisan digitrans:cek        # harus: ✅ koneksi BERHASIL
php artisan digitrans:sync       # tarik semua pegawai DigiTrans
```

### 5. (Opsional) Cron sinkronisasi harian
cPanel → **Cron Jobs** → 1x/hari:
```
/usr/local/bin/php /home/triventk/transkinerja/artisan digitrans:sync
```

## Perintah Artisan

| Perintah | Fungsi |
|---|---|
| `php artisan digitrans:cek` | Tampilkan status konfigurasi + tes koneksi |
| `php artisan digitrans:sync` | Sinkron massal pegawai DigiTrans → lokal |

## Catatan Penting

- **Password DigiTrans harus bcrypt** (bawaan Laravel). Bila ternyata memakai
  hashing lain, matikan `DIGITRANS_LOGIN=false` (pakai sinkron data saja).
- **Unit kerja** cocok berdasarkan NAMA yang sama persis. Buat dulu unit kerja di
  TransKinerja dengan nama serupa DigiTrans agar otomatis tertaut.
- Pegawai baru hasil provision mendapat role `pegawai` — naikkan peran/atur atasan
  lewat menu Manajemen Pegawai.
- **Rollback instan**: `DIGITRANS_AKTIF=false` → semua perilaku kembali murni lokal.
- Audit trail: setiap login via DigiTrans tercatat (`login-digitrans`), begitu pula
  hasil sinkronisasi (`digitrans-sync`).

## Alternatif Arsitektur (bila ke depan dibutuhkan)

| Opsi | Kelebihan | Kekurangan |
|---|---|---|
| **Share DB read-only** *(dipakai sekarang)* | Tanpa mengubah DigiTrans, cepat, sederhana | Harus satu server; kunci pada skema |
| SSO OAuth (DigiTrans = IdP, Laravel Passport) | Dua arah, logout terpusat, aman lintas server | Perlu ubah kode DigiTrans |
| API sinkron (REST berkala) | Longgar-coupling, bisa lintas server | Perlu endpoint API di DigiTrans |

Migrasi ke opsi lain nanti tidak mengubah alur login — cukup ganti implementasi
`DigitransService` dengan sumber data lain.
