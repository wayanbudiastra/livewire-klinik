#!/bin/bash

# =============================================================
#  DEPLOY SCRIPT — EMR System (Laravel 12 + PHP 8.2)
#  Ubuntu 22.04 LTS — AMAN untuk VPS yang sudah ada PHP 7.4
#
#  CARA PAKAI:
#  Tahap 1 (tanpa database): sudo bash deploy.sh --skip-db
#  Tahap 2 (setup database): sudo bash deploy.sh --only-db
#  Full sekaligus          : sudo bash deploy.sh
# =============================================================

set -e

# ── Konfigurasi — SESUAIKAN INI ─────────────────────────────
SERVER_IP="xxx.xxx.xxx.xxx"            # ganti dengan IP VPS Anda
PORT="8080"                            # port untuk EMR (hindari 80 jika sudah dipakai project lain)
PROJECT_DIR="/var/www/livewire-klinik" # folder project di VPS
REPO_URL="https://github.com/wayanbudiastra/livewire-klinik.git"
DB_NAME="emr_db"
DB_USER="emr_user"
DB_PASS="ganti_password_kuat"         # ganti dengan password yang kuat
DB_ROOT_PASS=""                        # password root MySQL (kosongkan jika belum ada)
# ────────────────────────────────────────────────────────────

GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
RED='\033[0;31m'
NC='\033[0m'

info()    { echo -e "${GREEN}[INFO]${NC} $1"; }
warning() { echo -e "${YELLOW}[WARN]${NC} $1"; }
step()    { echo -e "\n${BLUE}━━━ $1 ━━━${NC}"; }
error()   { echo -e "${RED}[ERROR]${NC} $1"; exit 1; }

# ── Parse argumen ────────────────────────────────────────────
SKIP_DB=false
ONLY_DB=false

for arg in "$@"; do
    case $arg in
        --skip-db) SKIP_DB=true ;;
        --only-db) ONLY_DB=true ;;
    esac
done

echo ""
echo "╔══════════════════════════════════════════════╗"
echo "║     Deploy EMR System — Laravel 12 + PHP 8.2 ║"
echo "╚══════════════════════════════════════════════╝"

if $SKIP_DB; then
    echo -e "  Mode: ${YELLOW}Tahap 1 — Tanpa Database${NC}"
elif $ONLY_DB; then
    echo -e "  Mode: ${YELLOW}Tahap 2 — Setup Database Saja${NC}"
else
    echo -e "  Mode: ${GREEN}Full Deploy${NC}"
fi
echo ""

# ════════════════════════════════════════════════════════════
#  TAHAP 2 — ONLY DB (setup database saja)
# ════════════════════════════════════════════════════════════
if $ONLY_DB; then

    step "Install MySQL"
    if ! command -v mysql &> /dev/null; then
        info "Menginstall MySQL Server..."
        apt update -qq
        apt install -y mysql-server > /dev/null 2>&1
        systemctl enable mysql
        systemctl start mysql
        info "MySQL berhasil diinstall."
    else
        info "MySQL sudah terinstall: $(mysql --version)"
    fi

    step "Buat Database & User"
    mysql -u root ${DB_ROOT_PASS:+-p"$DB_ROOT_PASS"} -e "
        CREATE DATABASE IF NOT EXISTS \`$DB_NAME\`
            CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
        CREATE USER IF NOT EXISTS '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASS';
        GRANT ALL PRIVILEGES ON \`$DB_NAME\`.* TO '$DB_USER'@'localhost';
        FLUSH PRIVILEGES;
    "
    info "Database '$DB_NAME' dan user '$DB_USER' siap."

    step "Update .env dengan Konfigurasi Database"
    cd "$PROJECT_DIR"
    sed -i "s|DB_CONNECTION=.*|DB_CONNECTION=mysql|" .env
    sed -i "s|DB_HOST=.*|DB_HOST=127.0.0.1|" .env
    sed -i "s|DB_DATABASE=.*|DB_DATABASE=$DB_NAME|" .env
    sed -i "s|DB_USERNAME=.*|DB_USERNAME=$DB_USER|" .env
    sed -i "s|DB_PASSWORD=.*|DB_PASSWORD=$DB_PASS|" .env
    info ".env database diupdate."

    step "Migrasi & Seed Database"
    php8.2 artisan migrate --force
    php8.2 artisan db:seed --force
    info "Database berhasil dimigrate & di-seed."

    step "Rebuild Cache"
    php8.2 artisan config:clear
    php8.2 artisan config:cache
    systemctl restart php8.2-fpm

    echo ""
    echo "╔══════════════════════════════════════════════╗"
    echo -e "║     ${GREEN}DATABASE SETUP SELESAI!${NC}                   ║"
    echo "╚══════════════════════════════════════════════╝"
    echo ""
    echo "  Akses : http://$SERVER_IP:$PORT"
    echo "  Login : superadmin@emr.app / password"
    echo ""
    exit 0
fi

# ════════════════════════════════════════════════════════════
#  TAHAP 1 — INSTALL SERVER & DEPLOY APP
# ════════════════════════════════════════════════════════════

step "STEP 1: Update sistem & install tools dasar"
apt update -qq
apt install -y software-properties-common curl git unzip > /dev/null 2>&1
info "Tools dasar siap."

step "STEP 2: Install PHP 8.2 (tidak mengganggu PHP lain)"
add-apt-repository ppa:ondrej/php -y > /dev/null 2>&1
apt update -qq
apt install -y \
    php8.2 php8.2-fpm \
    php8.2-mysql php8.2-mbstring \
    php8.2-xml php8.2-curl \
    php8.2-zip php8.2-bcmath \
    php8.2-intl php8.2-gd \
    php8.2-tokenizer php8.2-fileinfo \
    php8.2-opcache \
    > /dev/null 2>&1
info "PHP 8.2 terinstall: $(php8.2 -v | head -1)"

step "STEP 3: Install Nginx"
if ! command -v nginx &> /dev/null; then
    apt install -y nginx > /dev/null 2>&1
    systemctl enable nginx
    systemctl start nginx
    info "Nginx berhasil diinstall."
else
    info "Nginx sudah ada: $(nginx -v 2>&1)"
fi

step "STEP 4: Install Composer"
if ! command -v composer &> /dev/null; then
    info "Download Composer installer..."

    # Pastikan php8.2-cli tersedia
    apt install -y php8.2-cli > /dev/null 2>&1

    # Download installer dengan retry
    EXPECTED_CHECKSUM="$(php8.2 -r 'copy("https://composer.github.io/installer.sig", "php://stdout");')"
    php8.2 -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
    ACTUAL_CHECKSUM="$(php8.2 -r "echo hash_file('sha384', 'composer-setup.php');")"

    if [ "$EXPECTED_CHECKSUM" != "$ACTUAL_CHECKSUM" ]; then
        # Checksum gagal, coba cara alternatif
        warning "Checksum tidak cocok, coba download ulang..."
        rm -f composer-setup.php
        curl -sS https://getcomposer.org/installer -o composer-setup.php
    fi

    php8.2 composer-setup.php --install-dir=/usr/local/bin --filename=composer --quiet
    rm -f composer-setup.php

    # Verifikasi
    if command -v composer &> /dev/null; then
        info "Composer berhasil diinstall: $(composer --version 2>&1 | head -1)"
    else
        error "Gagal install Composer. Coba manual: curl -sS https://getcomposer.org/installer | php8.2 -- --install-dir=/usr/local/bin --filename=composer"
    fi
else
    info "Composer sudah ada: $(composer --version 2>&1 | head -1)"
fi

step "STEP 5: Install Node.js"
if ! command -v node &> /dev/null; then
    curl -fsSL https://deb.nodesource.com/setup_20.x | bash - > /dev/null 2>&1
    apt install -y nodejs > /dev/null 2>&1
    info "Node.js terinstall: $(node -v)"
else
    info "Node.js sudah ada: $(node -v)"
fi

step "STEP 6: Clone / Update repo dari GitHub"
if [ -d "$PROJECT_DIR/.git" ]; then
    info "Folder sudah ada, melakukan git pull..."
    cd "$PROJECT_DIR"
    git pull origin main
else
    git clone "$REPO_URL" "$PROJECT_DIR"
    cd "$PROJECT_DIR"
    info "Repo berhasil di-clone."
fi

step "STEP 7: Install Composer dependencies"
php8.2 $(which composer) install \
    --no-dev \
    --optimize-autoloader \
    --no-interaction 2>&1 | tail -3

step "STEP 8: Setup file .env"
if [ ! -f "$PROJECT_DIR/.env" ]; then
    cp "$PROJECT_DIR/.env.example" "$PROJECT_DIR/.env"
fi

sed -i "s|APP_NAME=.*|APP_NAME=\"EMR System\"|" .env
sed -i "s|APP_ENV=.*|APP_ENV=local|" .env
sed -i "s|APP_DEBUG=.*|APP_DEBUG=true|" .env
sed -i "s|APP_URL=.*|APP_URL=http://$SERVER_IP:$PORT|" .env
sed -i "s|LOG_CHANNEL=.*|LOG_CHANNEL=daily|" .env

# Jika --skip-db, kosongkan konfigurasi DB (isi nanti)
if $SKIP_DB; then
    sed -i "s|DB_CONNECTION=.*|DB_CONNECTION=mysql|" .env
    sed -i "s|DB_HOST=.*|DB_HOST=127.0.0.1|" .env
    sed -i "s|DB_DATABASE=.*|DB_DATABASE=$DB_NAME|" .env
    sed -i "s|DB_USERNAME=.*|DB_USERNAME=$DB_USER|" .env
    sed -i "s|DB_PASSWORD=.*|DB_PASSWORD=|" .env
    warning "Konfigurasi DB dikosongkan — isi nanti dengan: sudo bash update.sh --only-db"
else
    sed -i "s|DB_CONNECTION=.*|DB_CONNECTION=mysql|" .env
    sed -i "s|DB_HOST=.*|DB_HOST=127.0.0.1|" .env
    sed -i "s|DB_DATABASE=.*|DB_DATABASE=$DB_NAME|" .env
    sed -i "s|DB_USERNAME=.*|DB_USERNAME=$DB_USER|" .env
    sed -i "s|DB_PASSWORD=.*|DB_PASSWORD=$DB_PASS|" .env
fi

php8.2 artisan key:generate --force
info ".env berhasil dikonfigurasi."

step "STEP 9: Set permission storage"
chown -R www-data:www-data "$PROJECT_DIR/storage" "$PROJECT_DIR/bootstrap/cache"
chmod -R 775 "$PROJECT_DIR/storage" "$PROJECT_DIR/bootstrap/cache"

step "STEP 10: Build frontend assets"
npm install --silent
npm run build
info "Frontend assets berhasil dibuild."

# Install & migrate DB hanya jika tidak --skip-db
if ! $SKIP_DB; then

    step "STEP 11: Install MySQL"
    if ! command -v mysql &> /dev/null; then
        apt install -y mysql-server > /dev/null 2>&1
        systemctl enable mysql
        systemctl start mysql
        info "MySQL berhasil diinstall."
    else
        info "MySQL sudah ada."
    fi

    step "STEP 12: Buat database"
    mysql -u root ${DB_ROOT_PASS:+-p"$DB_ROOT_PASS"} -e "
        CREATE DATABASE IF NOT EXISTS \`$DB_NAME\`
            CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
        CREATE USER IF NOT EXISTS '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASS';
        GRANT ALL PRIVILEGES ON \`$DB_NAME\`.* TO '$DB_USER'@'localhost';
        FLUSH PRIVILEGES;
    "
    info "Database '$DB_NAME' siap."

    step "STEP 13: Migrasi & Seed"
    php8.2 artisan migrate --force
    php8.2 artisan db:seed --force
    info "Database berhasil dimigrate & di-seed."
fi

step "STEP $(($SKIP_DB ? 11 : 14)): Konfigurasi Nginx"
NGINX_CONF="/etc/nginx/sites-available/emr-klinik"
cat > "$NGINX_CONF" <<NGINXCONF
server {
    listen $PORT;
    server_name $SERVER_IP;
    root $PROJECT_DIR/public;
    index index.php;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";
    charset utf-8;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }
    error_page 404 /index.php;

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

ln -sf "$NGINX_CONF" /etc/nginx/sites-enabled/emr-klinik
nginx -t && systemctl reload nginx
info "Nginx dikonfigurasi untuk $SERVER_IP:$PORT"

step "Aktifkan PHP 8.2 FPM"
systemctl enable php8.2-fpm
systemctl restart php8.2-fpm

# Buka firewall
ufw allow "$PORT" > /dev/null 2>&1 || true

# Cache (skip jika DB belum ada)
if ! $SKIP_DB; then
    php8.2 artisan config:cache
    php8.2 artisan route:cache
    php8.2 artisan view:cache
fi

# ── Selesai ──────────────────────────────────────────────────
echo ""
echo "╔══════════════════════════════════════════════╗"
echo -e "║     ${GREEN}DEPLOY SELESAI!${NC}                            ║"
echo "╚══════════════════════════════════════════════╝"
echo ""
echo "  URL     : http://$SERVER_IP:$PORT"
echo "  Project : $PROJECT_DIR"
echo "  PHP     : 8.2-fpm"
echo ""

if $SKIP_DB; then
    echo -e "  ${YELLOW}⚠ Database belum dikonfigurasi.${NC}"
    echo "  Setelah MySQL siap, jalankan:"
    echo ""
    echo "    sudo bash /root/deploy.sh --only-db"
    echo ""
else
    echo "  Login  : superadmin@emr.app / password"
    echo ""
    echo -e "  ${YELLOW}PENTING:${NC}"
    echo "  1. Ganti password default setelah login"
    echo "  2. Buka firewall: sudo ufw allow $PORT"
fi
echo ""
echo "  Update berikutnya: sudo bash /var/www/livewire-klinik/update.sh"
echo ""
