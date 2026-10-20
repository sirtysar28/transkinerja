# Panduan Deploy ke cPanel — TransKinerja

Paket: `deploy/transkinerja-deploy.zip` + `deploy/transkinerja-db.sql`

## A. Upload Pertama / Lengkap

1. **Upload & ekstrak** `transkinerja-deploy.zip` ke folder project di cPanel
   (mis. `/home/USER/transkinerja/` — di luar `public_html`).
2. **Arahkan domain/subdomain** (cPanel → *Setup Node.js App tidak perlu* → cukup *Domains/Subdomains* atau *Addon Domains*) ke folder `public/`
   — atau pindahkan isi `public/` ke `public_html/` dan sesuaikan path di `public/index.php`:
   ```php
   require __DIR__.'/../transkinerja/vendor/autoload.php';
   $app = require_once __DIR__.'/../transkinerja/bootstrap/app.php';
   ```
3. **Buat `.env`** di root project (salin dari `.env.production`), isi:
   `APP_URL`, `APP_KEY` (`php artisan key:generate --show` bila kosong), `DB_*`, `MAIL_*`.
4. **Import database** `transkinerja-db.sql` via phpMyAdmin, samakan `DB_DATABASE/DB_USERNAME/DB_PASSWORD` dengan `.env`.
5. **Permission** (via Terminal cPanel atau File Manager → klik kanan → Change Permissions):
   ```
   chmod -R 775 storage bootstrap/cache
   ```
   Bila masih error tulis log: `chmod -R 777 storage` (khusus shared hosting).
6. **Bersihkan cache** (Terminal cPanel; pilih PHP 8.3):
   ```
   php artisan optimize:clear
   ```
   Tanpa Terminal: lewat File Manager hapus manual `bootstrap/cache/*.php`,
   `storage/framework/views/*.php`, `storage/framework/cache/data/*`.
7. **PENTING — aset frontend**: folder `public/build/` **wajib utuh** berisi
   `manifest.json` + seluruh file di `assets/` (termasuk `dashboard-charts-*.js`).
   Manifest lama tanpa entry chart membuat grafik dashboard tidak tampil
   (halaman tetap aman, tidak error).

## B. Perbaikan Cepat (server sudah terlanjur error setelah update)

Gejala umum: HTTP 500 di halaman Dashboard, atau error
`Unable to locate file in Vite manifest`.

1. Upload **utuh** folder `public/build/` (timpa yang lama, jangan ada file tersisa
   dari build sebelumnya di `public/build/assets/` — lebih aman hapus dulu foldernya).
2. Upload file yang berubah:
   - `app/Http/Controllers/DashboardController.php`
   - `resources/views/dashboard.blade.php`
   - `resources/views/layouts/app.blade.php`, `layouts/partials/*`
   - `resources/views/auth/*.blade.php`, `emails/*.blade.php`, `laporan/pdf.blade.php`
   - `tailwind.config.js`, `vite.config.js`, `package.json`, `package-lock.json`
   - `resources/css/app.css`, `resources/js/app.js`, `resources/js/dashboard-charts.js`
3. Hapus cache di server: `php artisan optimize:clear`
   (atau hapus manual `bootstrap/cache/*.php` + `storage/framework/views/*.php`).

## C. Cek Masalah Umum

| Gejala | Penyebab | Solusi |
|---|---|---|
| 500 di semua halaman | `bootstrap/cache/services.php` berisi path lokal (`/Users/...`) | Hapus `bootstrap/cache/*.php` di server |
| 500 hanya di Dashboard | `public/build/manifest.json` lama | Upload ulang folder `public/build` utuh |
| Halaman putih / tanpa style | CSS `app-*.css` tidak ikut ter-upload | Pastikan `public/build/assets/` utuh |
| "No application encryption key" | `.env` belum dibuat | Buat `.env` + isi `APP_KEY` |
| Tidak bisa login/session | `SESSION_DRIVER=database` tapi tabel session belum ada | Jalankan migrasi / import DB ulang |
| Grafik tidak muncul (kartu & tabel normal) | Entry chart belum ada di manifest server | Lihat poin B-1 |

> Setelah deploy sukses, cek `storage/logs/laravel.log` bila masih ada kendala —
> pesan error aktual ada di sana.
