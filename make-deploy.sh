#!/usr/bin/env bash
# Membuat paket deploy TransKinerja untuk cPanel shared hosting (PHP 8.3)
# Hasil: deploy/transkinerja-deploy.zip + deploy/transkinerja-db.sql
set -e

echo "==> 1/5 Bersihkan cache lokal"
php artisan config:clear > /dev/null
php artisan route:clear > /dev/null
php artisan view:clear > /dev/null
php artisan event:clear > /dev/null 2>&1 || true
# Cache bootstrap berisi PATH ABSOLUT komputer lokal — wajib dihapus agar tidak ikut ter-zip
rm -f bootstrap/cache/*.php

echo "==> 2/5 Build ulang aset frontend (manifest wajib sinkron)"
npm run build

echo "==> 3/5 Dump database (transkinerja)"
mkdir -p deploy
mysqldump --no-tablespaces --routines --single-transaction transkinerja > deploy/transkinerja-db.sql

echo "==> 4/5 Susun paket (tanpa node_modules/.git/dev-files/cache-absolut)"
rm -f deploy/transkinerja-deploy.zip
zip -rq deploy/transkinerja-deploy.zip . \
    -x "node_modules/*" \
    -x ".git/*" \
    -x "deploy/*" \
    -x "storage/logs/*.log" \
    -x "storage/framework/cache/data/*" \
    -x "storage/framework/sessions/*" \
    -x "storage/framework/views/*.php" \
    -x "bootstrap/cache/*.php" \
    -x "tests/*" \
    -x "phpunit.xml" \
    -x ".phpunit.result.cache" \
    -x ".env" \
    -x ".vscode/*" \
    -x "*.DS_Store" \
    -x "make-deploy.sh"

echo "==> 5/5 Selesai"
ls -lh deploy/
echo ""
echo "PENTING: upload folder public/build SECARA LENGKAP (manifest.json + assets) lalu ikuti DEPLOY-CPANEL.md"
