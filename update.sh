#!/bin/bash

# =============================================================
#  UPDATE SCRIPT — EMR System (Laravel 12 + PHP 8.3)
#  Jalankan setiap ada update dari GitHub
#  Cara pakai: sudo bash update.sh
# =============================================================

set -e

PROJECT_DIR="/var/www/livewire-klinik"

GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m'

info()    { echo -e "${GREEN}[INFO]${NC} $1"; }
warning() { echo -e "${YELLOW}[WARN]${NC} $1"; }

echo ""
echo "=============================================="
echo "   Update EMR System Demo"
echo "=============================================="
echo ""

cd "$PROJECT_DIR" || { echo "Folder $PROJECT_DIR tidak ditemukan!"; exit 1; }

# ── 1. Pull dari GitHub ──────────────────────────────────────
info "STEP 1: Pull update dari GitHub..."
git pull origin main
info "Kode berhasil diupdate."

# ── 2. Fix permission storage (sebelum artisan dijalankan) ───
# Dilakukan di awal agar artisan tidak gagal baca/tulis log
info "STEP 2: Fix permission storage & cache..."
chown -R www-data:www-data "$PROJECT_DIR/storage" "$PROJECT_DIR/bootstrap/cache"
chmod -R 775 "$PROJECT_DIR/storage" "$PROJECT_DIR/bootstrap/cache"

# ── 3. Cek koneksi database ───────────────────────────────────
# Dicek lebih dulu supaya kalau .env belum diisi / database belum
# dibuat, script berhenti dengan pesan jelas — bukan error mentah
# Laravel di tengah migrate.
info "STEP 3: Cek koneksi database..."
if php8.3 artisan db:show > /dev/null 2>&1; then
    info "Koneksi database OK."
else
    echo -e "${RED}[ERROR]${NC} Database belum terhubung!"
    echo ""
    echo "  Kemungkinan penyebab:"
    echo "  - .env belum diisi / salah (DB_DATABASE, DB_USERNAME, DB_PASSWORD)"
    echo "  - Database belum dibuat"
    echo ""
    echo "  Perbaiki lalu jalankan ulang update.sh:"
    echo "    1. nano $PROJECT_DIR/.env"
    echo "    2. sudo bash $PROJECT_DIR/deploy.sh --create-db   (kalau database belum ada)"
    echo "    3. sudo bash $PROJECT_DIR/update.sh"
    echo ""
    exit 1
fi

# ── 4. Install dependency baru (jika ada) ─────────────────────
info "STEP 4: Update Composer dependencies..."
php8.3 $(which composer) install \
  --no-dev \
  --optimize-autoloader \
  --no-interaction 2>&1 | tail -3

# ── 5. Migrasi database (jika ada migration baru) ─────────────
info "STEP 5: Jalankan migrasi database..."
php8.3 artisan migrate --force

# ── 5b. Seed data referensi jika belum ada ────────────────────
ICD_COUNT=$(php8.3 artisan tinker --execute="echo App\Models\IcdDiagnosis::count();" 2>/dev/null | tail -1)
if [ "$ICD_COUNT" = "0" ] || [ -z "$ICD_COUNT" ]; then
    info "STEP 5b: Seed data ICD-10 (belum ada data)..."
    php8.3 artisan db:seed --class=Icd10Seeder --force
else
    info "STEP 5b: ICD-10 sudah ada ($ICD_COUNT kode), skip seed."
fi

PENUNJANG_COUNT=$(php8.3 artisan tinker --execute="echo App\Models\ItemPenunjang::count();" 2>/dev/null | tail -1)
if [ "$PENUNJANG_COUNT" = "0" ] || [ -z "$PENUNJANG_COUNT" ]; then
    info "STEP 5b: Seed data Item Penunjang (belum ada data)..."
    php8.3 artisan db:seed --class=PenunjangSeeder --force
else
    info "STEP 5b: Item Penunjang sudah ada ($PENUNJANG_COUNT item), skip seed."
fi

# ── 6. Clear & rebuild cache ───────────────────────────────────
info "STEP 6: Rebuild cache..."
php8.3 artisan config:clear
php8.3 artisan route:clear
php8.3 artisan view:clear
php8.3 artisan config:cache
php8.3 artisan route:cache
php8.3 artisan view:cache

# ── 7. Build frontend (jika ada perubahan CSS/JS) ─────────────
info "STEP 7: Build frontend assets..."
npm install --silent
npm run build

# ── 8. Fix permission (setelah artisan & build membuat file baru) ──
# artisan/npm berjalan sebagai root → file baru bisa owned root
info "STEP 8: Fix permission storage & cache (final)..."
chown -R www-data:www-data "$PROJECT_DIR/storage" "$PROJECT_DIR/bootstrap/cache"
chmod -R 775 "$PROJECT_DIR/storage" "$PROJECT_DIR/bootstrap/cache"

# ── 9. Restart PHP FPM ─────────────────────────────────────────
info "STEP 9: Restart PHP 8.3 FPM..."
systemctl restart php8.3-fpm

echo ""
echo "=============================================="
echo -e "   ${GREEN}UPDATE SELESAI!${NC}"
echo "=============================================="
echo ""
echo "  Waktu   : $(date '+%d-%m-%Y %H:%M:%S')"
echo "  Commit  : $(git log --oneline -1)"
echo ""
