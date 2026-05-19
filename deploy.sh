#!/bin/bash

# =============================================================
#  DEPLOY SCRIPT — EMR System (Laravel 12 + PHP 8.2)
#  Ubuntu 22.04 LTS — AMAN untuk VPS yang sudah ada PHP 7.4
#  Jalankan: bash deploy.sh
# =============================================================

set -e  # Hentikan jika ada error

# ── Konfigurasi — SESUAIKAN INI ─────────────────────────────
SERVER_IP="xxx.xxx.xxx.xxx"            # ganti dengan IP VPS Anda
PORT="8080"                            # port untuk EMR (hindari 80 jika sudah dipakai project lain)
PROJECT_DIR="/var/www/livewire-klinik" # folder project di VPS
REPO_URL="https://github.com/wayanbudiastra/livewire-klinik.git"
DB_NAME="emr_db"
DB_USER="emr_user"
DB_PASS="ganti_password_kuat"         # ganti dengan password yang kuat
# ────────────────────────────────────────────────────────────

GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m'

info()    { echo -e "${GREEN}[INFO]${NC} $1"; }
warning() { echo -e "${YELLOW}[WARN]${NC} $1"; }
error()   { echo -e "${RED}[ERROR]${NC} $1"; exit 1; }

echo ""
echo "=============================================="
echo "   Deploy EMR System — Laravel 12 + PHP 8.2"
echo "=============================================="
echo ""

# ── STEP 1: Tambah repo PHP (tidak hapus PHP 7.4) ───────────
info "STEP 1: Menambah repository PHP ondrej (multi-version)..."
apt update -qq
apt install -y software-properties-common curl git unzip > /dev/null 2>&1
add-apt-repository ppa:ondrej/php -y > /dev/null 2>&1
apt update -qq
info "Repository PHP berhasil ditambahkan."

# ── STEP 2: Install PHP 8.2 saja (tidak ganggu 7.4) ─────────
info "STEP 2: Install PHP 8.2 + extensions..."
apt install -y \
  php8.2 \
  php8.2-fpm \
  php8.2-mysql \
  php8.2-mbstring \
  php8.2-xml \
  php8.2-curl \
  php8.2-zip \
  php8.2-bcmath \
  php8.2-intl \
  php8.2-gd \
  php8.2-tokenizer \
  php8.2-fileinfo \
  php8.2-opcache \
  > /dev/null 2>&1

info "PHP 8.2 berhasil diinstall."
php8.2 -v | head -1

# Verifikasi PHP 7.4 masih aman
if command -v php7.4 &> /dev/null; then
    info "PHP 7.4 masih aman: $(php7.4 -v | head -1)"
else
    warning "PHP 7.4 tidak ditemukan di PATH, tapi ini normal jika menggunakan php-fpm."
fi

# ── STEP 3: Install Composer (skip jika sudah ada) ──────────
info "STEP 3: Memeriksa Composer..."
if ! command -v composer &> /dev/null; then
    info "Composer belum ada, menginstall..."
    curl -sS https://getcomposer.org/installer | php8.2 -- --install-dir=/usr/local/bin --filename=composer > /dev/null 2>&1
    info "Composer berhasil diinstall."
else
    info "Composer sudah ada: $(composer --version 2>&1 | head -1)"
fi

# ── STEP 4: Setup database MySQL ────────────────────────────
info "STEP 4: Membuat database MySQL '$DB_NAME'..."
mysql -u root -e "
  CREATE DATABASE IF NOT EXISTS \`$DB_NAME\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
  CREATE USER IF NOT EXISTS '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASS';
  GRANT ALL PRIVILEGES ON \`$DB_NAME\`.* TO '$DB_USER'@'localhost';
  FLUSH PRIVILEGES;
" 2>/dev/null || warning "Gagal buat DB otomatis, buat manual jika perlu."
info "Database '$DB_NAME' siap."

# ── STEP 5: Clone / update repo ─────────────────────────────
info "STEP 5: Deploy project dari GitHub..."
if [ -d "$PROJECT_DIR/.git" ]; then
    info "Folder sudah ada, melakukan git pull..."
    cd "$PROJECT_DIR"
    git pull origin main
else
    info "Clone repo baru..."
    git clone "$REPO_URL" "$PROJECT_DIR"
    cd "$PROJECT_DIR"
fi

# ── STEP 6: Install PHP dependencies ────────────────────────
info "STEP 6: Install Composer dependencies (PHP 8.2)..."
php8.2 $(which composer) install \
  --no-dev \
  --optimize-autoloader \
  --no-interaction \
  2>&1 | tail -5

# ── STEP 7: Setup file .env ──────────────────────────────────
info "STEP 7: Setup file .env..."
if [ ! -f "$PROJECT_DIR/.env" ]; then
    cp "$PROJECT_DIR/.env.example" "$PROJECT_DIR/.env"
    info ".env dibuat dari .env.example"
fi

# Update nilai .env
sed -i "s|APP_NAME=.*|APP_NAME=\"EMR System\"|" .env
sed -i "s|APP_ENV=.*|APP_ENV=local|" .env
sed -i "s|APP_DEBUG=.*|APP_DEBUG=true|" .env
sed -i "s|APP_URL=.*|APP_URL=http://$SERVER_IP:$PORT|" .env
sed -i "s|DB_CONNECTION=.*|DB_CONNECTION=mysql|" .env
sed -i "s|DB_HOST=.*|DB_HOST=127.0.0.1|" .env
sed -i "s|DB_DATABASE=.*|DB_DATABASE=$DB_NAME|" .env
sed -i "s|DB_USERNAME=.*|DB_USERNAME=$DB_USER|" .env
sed -i "s|DB_PASSWORD=.*|DB_PASSWORD=$DB_PASS|" .env
sed -i "s|LOG_CHANNEL=.*|LOG_CHANNEL=daily|" .env
sed -i "s|SESSION_DRIVER=.*|SESSION_DRIVER=database|" .env
sed -i "s|CACHE_STORE=.*|CACHE_STORE=database|" .env

# Generate app key
php8.2 artisan key:generate --force
info ".env berhasil dikonfigurasi."

# ── STEP 8: Permission storage ───────────────────────────────
info "STEP 8: Set permission folder storage..."
chown -R www-data:www-data "$PROJECT_DIR/storage" "$PROJECT_DIR/bootstrap/cache"
chmod -R 775 "$PROJECT_DIR/storage" "$PROJECT_DIR/bootstrap/cache"

# ── STEP 9: Install Node & build assets ─────────────────────
info "STEP 9: Build frontend assets (Tailwind + Vite)..."
if ! command -v node &> /dev/null; then
    info "Install Node.js..."
    curl -fsSL https://deb.nodesource.com/setup_20.x | bash - > /dev/null 2>&1
    apt install -y nodejs > /dev/null 2>&1
fi
npm install --silent
npm run build
info "Frontend assets berhasil dibuild."

# ── STEP 10: Migrate & seed database ────────────────────────
info "STEP 10: Jalankan migrasi database..."
php8.2 artisan migrate --force
php8.2 artisan db:seed --force
info "Database berhasil dimigrate & di-seed."

# ── STEP 11: Optimize Laravel ────────────────────────────────
info "STEP 11: Optimasi Laravel untuk production..."
php8.2 artisan config:cache
php8.2 artisan route:cache
php8.2 artisan view:cache
info "Cache berhasil dibuat."

# ── STEP 12: Buat konfigurasi Nginx ─────────────────────────
info "STEP 12: Membuat konfigurasi Nginx..."

NGINX_CONF="/etc/nginx/sites-available/emr-klinik"

cat > "$NGINX_CONF" <<NGINXCONF
server {
    listen $PORT;
    server_name $SERVER_IP;
    root $PROJECT_DIR/public;
    index index.php;

    # Security headers
    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    charset utf-8;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    # PHP 8.2 FPM — tidak mengganggu project lain yang pakai PHP 7.4
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
NGINXCONF

# Aktifkan site
ln -sf "$NGINX_CONF" /etc/nginx/sites-enabled/emr-klinik

# Test konfigurasi nginx
nginx -t && systemctl reload nginx
info "Nginx berhasil dikonfigurasi untuk domain: $DOMAIN"

# ── STEP 13: PHP 8.2 FPM aktifkan ───────────────────────────
info "STEP 13: Aktifkan PHP 8.2 FPM..."
systemctl enable php8.2-fpm
systemctl restart php8.2-fpm

# Pastikan PHP 7.4 FPM masih jalan (jika ada)
if systemctl is-active --quiet php7.4-fpm 2>/dev/null; then
    info "PHP 7.4 FPM masih berjalan dengan normal."
fi

# ── SELESAI ──────────────────────────────────────────────────
echo ""
echo "=============================================="
echo -e "   ${GREEN}DEPLOY SELESAI!${NC}"
echo "=============================================="
echo ""
echo -e "  URL       : http://$SERVER_IP:$PORT"
echo -e "  Project   : $PROJECT_DIR"
echo -e "  PHP EMR   : 8.2 (php8.2-fpm)"
echo -e "  Database  : $DB_NAME"
echo ""
echo "  Login default:"
echo "  Email    : superadmin@emr.app"
echo "  Password : password"
echo ""
echo -e "  ${YELLOW}PENTING:${NC}"
echo "  1. Ganti password default setelah login pertama"
echo "  2. Pastikan port $PORT terbuka di firewall VPS:"
echo "     sudo ufw allow $PORT"
echo "  3. Edit .env untuk konfigurasi email (SMTP)"
echo "  4. Jika nanti sudah punya domain, ubah SERVER_IP ke domain"
echo "     dan tambah SSL: sudo certbot --nginx -d nama-domain.com"
echo ""
echo "  Project lain (PHP 7.4) TIDAK terganggu."
echo "=============================================="
