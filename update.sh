#!/bin/bash

# =============================================================
#  UPDATE SCRIPT — EMR System (Laravel 12 + PHP 8.3)
#  Jalankan setiap ada update dari GitHub
#  Cara pakai: sudo bash update.sh
# =============================================================

set -e

PROJECT_DIR="/var/www/livewire-klinik"

# Dipakai HANYA saat auto-provisioning database (STEP 3), yaitu ketika
# koneksi ke .env yang ada gagal. Kalau .env sudah benar & terhubung,
# nilai di bawah ini tidak dipakai sama sekali.
DB_NAME="emr_db"
DB_USER="emr_user"

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

# ── 3. Cek koneksi database (auto-provision kalau belum nyambung) ──
# config:clear dulu supaya config cache lama (mis. dari .env.example
# yang default-nya DB_CONNECTION=sqlite) tidak menutupi .env terbaru.
info "STEP 3: Cek koneksi database..."
php8.3 artisan config:clear > /dev/null 2>&1

if php8.3 artisan db:show > /dev/null 2>&1; then
    info "Koneksi database OK."
else
    warning "Database belum terhubung. Membuat database, user & password baru otomatis..."

    DB_PASS_NEW=$(openssl rand -base64 32 | tr -dc 'A-Za-z0-9' | head -c 24)

    if mysql -u root -e "
        CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\`
            CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
        CREATE USER IF NOT EXISTS '${DB_USER}'@'127.0.0.1' IDENTIFIED BY '${DB_PASS_NEW}';
        CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS_NEW}';
        ALTER USER '${DB_USER}'@'127.0.0.1' IDENTIFIED BY '${DB_PASS_NEW}';
        ALTER USER '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS_NEW}';
        GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'127.0.0.1';
        GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'localhost';
        FLUSH PRIVILEGES;
    " 2>/dev/null; then
        sed -i "s|DB_CONNECTION=.*|DB_CONNECTION=mysql|" .env
        sed -i "s|DB_HOST=.*|DB_HOST=127.0.0.1|" .env
        sed -i "s|DB_DATABASE=.*|DB_DATABASE=${DB_NAME}|" .env
        sed -i "s|DB_USERNAME=.*|DB_USERNAME=${DB_USER}|" .env
        sed -i "s|DB_PASSWORD=.*|DB_PASSWORD=${DB_PASS_NEW}|" .env
        php8.3 artisan config:clear > /dev/null 2>&1

        if php8.3 artisan db:show > /dev/null 2>&1; then
            info "Database '${DB_NAME}' & user '${DB_USER}' siap, .env sudah diupdate."
            echo -e "  ${YELLOW}Password database (baru digenerate)${NC}: ${DB_PASS_NEW}"
            echo "  (sudah otomatis tersimpan di .env, catat juga untuk jaga-jaga)"
        else
            echo -e "${RED}[ERROR]${NC} Database & user berhasil dibuat, tapi koneksi masih gagal."
            echo "  Cek manual: cd $PROJECT_DIR && php8.3 artisan db:show"
            exit 1
        fi
    else
        echo -e "${RED}[ERROR]${NC} Gagal membuat database/user otomatis via 'mysql -u root'."
        echo ""
        echo "  Kemungkinan penyebab: root MySQL butuh password (bukan auth_socket)."
        echo "  Buat manual lalu jalankan ulang update.sh:"
        echo "    sudo mysql -u root -p"
        echo "    CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\`;"
        echo "    CREATE USER '${DB_USER}'@'127.0.0.1' IDENTIFIED BY 'password_kamu';"
        echo "    GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'127.0.0.1';"
        echo "    FLUSH PRIVILEGES;"
        echo "  Lalu isi .env manual & jalankan: sudo bash $PROJECT_DIR/update.sh"
        echo ""
        exit 1
    fi
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
