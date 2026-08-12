#!/bin/bash

# =============================================================
#  HEALTH CHECK — EMR System
#  Jalankan sebelum demo: sudo bash healthcheck.sh
# =============================================================

PROJECT_DIR="/var/www/livewire-klinik"

GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m'

ok()   { echo -e "  ${GREEN}✓${NC} $1"; }
warn() { echo -e "  ${YELLOW}⚠${NC} $1"; }
fail() { echo -e "  ${RED}✗${NC} $1"; }

echo ""
echo "=============================================="
echo "   Health Check — EMR System"
echo "=============================================="

cd "$PROJECT_DIR" || { fail "Folder $PROJECT_DIR tidak ditemukan"; exit 1; }

# ── Services ────────────────────────────────────────────────
echo ""
echo "── Services ──"
for svc in nginx php8.3-fpm mysql; do
    if systemctl is-active --quiet "$svc"; then
        ok "$svc aktif"
    else
        fail "$svc TIDAK aktif — jalankan: sudo systemctl start $svc"
    fi
done

# ── Kode & Git ──────────────────────────────────────────────
echo ""
echo "── Kode & Git ──"
ok "Commit saat ini: $(git log --oneline -1)"
git fetch origin main --quiet 2>/dev/null
BEHIND=$(git rev-list HEAD..origin/main --count 2>/dev/null || echo "?")
if [ "$BEHIND" = "0" ]; then
    ok "Sudah sinkron dengan origin/main"
else
    warn "Ketinggalan $BEHIND commit dari origin/main — jalankan: sudo bash update.sh"
fi

# ── Database ────────────────────────────────────────────────
echo ""
echo "── Database ──"
if php8.3 artisan db:show > /dev/null 2>&1; then
    ok "Koneksi database OK"
else
    fail "Koneksi database GAGAL — cek .env / jalankan: php8.3 artisan db:show"
fi

PENDING=$(php8.3 artisan migrate:status 2>/dev/null | grep -c "Pending" || true)
if [ "$PENDING" = "0" ]; then
    ok "Tidak ada migration pending"
else
    warn "$PENDING migration pending — jalankan: php8.3 artisan migrate --force"
fi

# ── Konfigurasi ─────────────────────────────────────────────
echo ""
echo "── Konfigurasi ──"
APP_ENV=$(grep "^APP_ENV=" .env | cut -d= -f2)
APP_DEBUG=$(grep "^APP_DEBUG=" .env | cut -d= -f2)
echo "  APP_ENV=$APP_ENV, APP_DEBUG=$APP_DEBUG"
if [ "$APP_DEBUG" = "true" ]; then
    warn "APP_DEBUG=true — kalau ada error saat demo, stack trace mentah (query SQL, path server, dll) akan tampil ke penonton. Pertimbangkan set APP_DEBUG=false untuk demo."
else
    ok "APP_DEBUG=false (aman untuk demo)"
fi

# ── Storage & Permission ───────────────────────────────────
echo ""
echo "── Storage & Permission ──"
if [ -w "$PROJECT_DIR/storage/logs" ]; then
    ok "storage/logs writable"
else
    fail "storage/logs TIDAK writable — jalankan: sudo chown -R www-data:www-data storage bootstrap/cache"
fi

# ── Error Terbaru ───────────────────────────────────────────
echo ""
echo "── Error Terbaru (laravel.log) ──"
if [ -f storage/logs/laravel.log ]; then
    RECENT_ERRORS=$(tail -200 storage/logs/laravel.log | grep -ci "\.ERROR\|Exception")
    if [ "$RECENT_ERRORS" = "0" ]; then
        ok "Tidak ada error di 200 baris log terakhir"
    else
        warn "$RECENT_ERRORS baris error ditemukan di log terakhir — cek: tail -100 storage/logs/laravel.log"
    fi
else
    ok "Belum ada log error (laravel.log belum dibuat)"
fi

# ── Disk Space ──────────────────────────────────────────────
echo ""
echo "── Disk Space ──"
df -h "$PROJECT_DIR" | tail -1 | awk '{print "  Terpakai: " $5 " dari " $2 " (tersisa " $4 ")"}'

echo ""
echo "=============================================="
echo "   Selesai. Cek poin ⚠ / ✗ di atas sebelum demo."
echo "=============================================="
echo ""
