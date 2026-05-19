#!/bin/bash

# =============================================================
#  UPDATE SCRIPT — EMR System (Laravel 12 + PHP 8.2)
#  Jalankan setiap ada update dari GitHub
#  Cara pakai: sudo bash update.sh
# =============================================================

set -e

PROJECT_DIR="/var/www/livewire-klinik"

GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

info()    { echo -e "${GREEN}[INFO]${NC} $1"; }
warning() { echo -e "${YELLOW}[WARN]${NC} $1"; }

echo ""
echo "=============================================="
echo "   Update EMR System"
echo "=============================================="
echo ""

cd "$PROJECT_DIR" || { echo "Folder $PROJECT_DIR tidak ditemukan!"; exit 1; }

# ── 1. Pull dari GitHub ──────────────────────────────────────
info "STEP 1: Pull update dari GitHub..."
git pull origin main
info "Kode berhasil diupdate."

# ── 2. Install dependency baru (jika ada) ────────────────────
info "STEP 2: Update Composer dependencies..."
php8.2 $(which composer) install \
  --no-dev \
  --optimize-autoloader \
  --no-interaction 2>&1 | tail -3

# ── 3. Migrasi database (jika ada migration baru) ────────────
info "STEP 3: Jalankan migrasi database..."
php8.2 artisan migrate --force

# ── 4. Clear & rebuild cache ─────────────────────────────────
info "STEP 4: Rebuild cache..."
php8.2 artisan config:clear
php8.2 artisan route:clear
php8.2 artisan view:clear
php8.2 artisan config:cache
php8.2 artisan route:cache
php8.2 artisan view:cache

# ── 5. Build frontend (jika ada perubahan CSS/JS) ────────────
info "STEP 5: Build frontend assets..."
npm install --silent
npm run build

# ── 6. Permission storage ────────────────────────────────────
info "STEP 6: Set permission storage..."
chown -R www-data:www-data "$PROJECT_DIR/storage" "$PROJECT_DIR/bootstrap/cache"
chmod -R 775 "$PROJECT_DIR/storage" "$PROJECT_DIR/bootstrap/cache"

# ── 7. Restart PHP FPM ───────────────────────────────────────
info "STEP 7: Restart PHP 8.2 FPM..."
systemctl restart php8.2-fpm

echo ""
echo "=============================================="
echo -e "   ${GREEN}UPDATE SELESAI!${NC}"
echo "=============================================="
echo ""
echo "  Waktu   : $(date '+%d-%m-%Y %H:%M:%S')"
echo "  Commit  : $(git log --oneline -1)"
echo ""
