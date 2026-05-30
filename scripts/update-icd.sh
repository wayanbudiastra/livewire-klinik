#!/usr/bin/env bash
# =============================================================================
# update-icd.sh — Update data ICD-10 di VPS
# Penggunaan:
#   ./scripts/update-icd.sh              → import bahasa Indonesia (default)
#   ./scripts/update-icd.sh --lang=en    → import bahasa Inggris
#   ./scripts/update-icd.sh --lang=both  → simpan keduanya (rekomendasi)
#   ./scripts/update-icd.sh --lang=both --mode=replace → hapus lama, isi ulang
# =============================================================================

set -euo pipefail

# ── Konfigurasi ───────────────────────────────────────────────────────────────
APP_DIR="/var/www/livewire-klinik"          # Sesuaikan dengan path project di VPS
PHP_BIN="php8.4"                   # Binary PHP (php8.4 / php / php8.3)
ARTISAN="${APP_DIR}/artisan"
JSON_FILE="${APP_DIR}/master_icd_x.json"
LOG_FILE="${APP_DIR}/storage/logs/icd-import.log"

# ── Warna output ──────────────────────────────────────────────────────────────
RED='\033[0;31m'; GREEN='\033[0;32m'; YELLOW='\033[1;33m'
BLUE='\033[0;34m'; BOLD='\033[1m'; RESET='\033[0m'

info()    { echo -e "${BLUE}[INFO]${RESET} $*"; }
success() { echo -e "${GREEN}[OK]${RESET}   $*"; }
warn()    { echo -e "${YELLOW}[WARN]${RESET} $*"; }
error()   { echo -e "${RED}[ERROR]${RESET} $*" >&2; }

# ── Parse argumen ─────────────────────────────────────────────────────────────
LANG_OPT="id"
MODE_OPT="upsert"

for arg in "$@"; do
    case $arg in
        --lang=*)  LANG_OPT="${arg#*=}" ;;
        --mode=*)  MODE_OPT="${arg#*=}" ;;
        --help|-h)
            echo -e "${BOLD}update-icd.sh${RESET} — Update data ICD-10"
            echo ""
            echo "Penggunaan:"
            echo "  $0 [--lang=id|en|both] [--mode=upsert|replace]"
            echo ""
            echo "Opsi:"
            echo "  --lang=id      Gunakan nama Bahasa Indonesia (default)"
            echo "  --lang=en      Gunakan nama International (English)"
            echo "  --lang=both    Simpan keduanya, aktif Indonesia (rekomendasi)"
            echo "  --mode=upsert  Tambah/perbarui kode yang ada (default)"
            echo "  --mode=replace Hapus semua data lama, isi ulang dari JSON"
            echo ""
            echo "Contoh:"
            echo "  $0 --lang=both"
            echo "  $0 --lang=both --mode=replace"
            exit 0
            ;;
        *)
            error "Opsi tidak dikenal: $arg"
            error "Gunakan --help untuk bantuan."
            exit 1
            ;;
    esac
done

# ── Header ────────────────────────────────────────────────────────────────────
echo ""
echo -e "${BOLD}════════════════════════════════════════${RESET}"
echo -e "${BOLD}  Update Data ICD-10${RESET}"
echo -e "${BOLD}════════════════════════════════════════${RESET}"
echo -e "  Direktori : ${APP_DIR}"
echo -e "  Bahasa    : ${LANG_OPT}"
echo -e "  Mode      : ${MODE_OPT}"
echo -e "  Waktu     : $(date '+%Y-%m-%d %H:%M:%S')"
echo -e "${BOLD}════════════════════════════════════════${RESET}"
echo ""

# ── Cek direktori project ─────────────────────────────────────────────────────
if [ ! -d "$APP_DIR" ]; then
    error "Direktori project tidak ditemukan: ${APP_DIR}"
    error "Edit variabel APP_DIR di bagian atas script ini."
    exit 1
fi

cd "$APP_DIR"

# ── Cek PHP ───────────────────────────────────────────────────────────────────
if ! command -v "$PHP_BIN" &>/dev/null; then
    warn "Binary '${PHP_BIN}' tidak ditemukan, mencoba 'php'..."
    PHP_BIN="php"
fi

if ! command -v "$PHP_BIN" &>/dev/null; then
    error "PHP tidak ditemukan. Install PHP terlebih dahulu."
    exit 1
fi

PHP_VERSION=$($PHP_BIN -r "echo PHP_VERSION;")
info "PHP ditemukan: ${PHP_VERSION}"

# ── Cek file JSON ─────────────────────────────────────────────────────────────
if [ ! -f "$JSON_FILE" ]; then
    error "File JSON tidak ditemukan: ${JSON_FILE}"
    warn "Upload file master_icd_x.json ke root project dengan perintah:"
    echo ""
    echo "  scp master_icd_x.json user@server:/var/www/klinik/"
    echo ""
    exit 1
fi

JSON_SIZE=$(du -h "$JSON_FILE" | cut -f1)
JSON_LINES=$(wc -c < "$JSON_FILE")
success "File JSON ditemukan (${JSON_SIZE})"

# ── Konfirmasi mode replace ───────────────────────────────────────────────────
if [ "$MODE_OPT" = "replace" ]; then
    echo ""
    warn "Mode REPLACE dipilih — seluruh data ICD-10 lama akan dihapus!"
    read -r -p "Ketik 'ya' untuk melanjutkan: " KONFIRMASI
    if [ "$KONFIRMASI" != "ya" ]; then
        warn "Import dibatalkan."
        exit 0
    fi
fi

# ── Jalankan artisan command ──────────────────────────────────────────────────
echo ""
info "Memulai import ICD-10..."
echo ""

START_TIME=$(date +%s)

# Jalankan dan tampilkan output sekaligus simpan ke log
$PHP_BIN "$ARTISAN" icd:import \
    --lang="$LANG_OPT" \
    --mode="$MODE_OPT" \
    --no-interaction \
    2>&1 | tee -a "$LOG_FILE"

EXIT_CODE=${PIPESTATUS[0]}
END_TIME=$(date +%s)
ELAPSED=$((END_TIME - START_TIME))

echo ""
echo -e "${BOLD}────────────────────────────────────────${RESET}"

if [ $EXIT_CODE -eq 0 ]; then
    success "Import selesai dalam ${ELAPSED} detik."
    success "Log disimpan di: ${LOG_FILE}"
else
    error "Import gagal (exit code: ${EXIT_CODE}). Cek log: ${LOG_FILE}"
    exit $EXIT_CODE
fi

echo ""
