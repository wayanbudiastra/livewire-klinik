#!/bin/bash

# =============================================================
#  SETUP AWAL ICD-10 BILINGUAL — EMR System (Laravel 12 + PHP 8.3)
#  Jalankan SATU KALI SAJA, bukan bagian rutin update.sh.
#
#  Kenapa terpisah dari update.sh (lihat prd/seeder_icd10_who_resmi.md §5.3):
#  - icd:import mengisi ulang nama/nama_en/nama_id dari master_icd_x.json.
#    Aman dijalankan berkali-kali (upsert), TAPI kalau tabel icd10 di
#    server ini pernah diedit manual lewat menu Pengaturan > Data ICD-10
#    (custom text untuk kode yang SAMA dengan yang ada di master_icd_x.json),
#    perubahan manual itu bisa tertimpa. Makanya ini langkah sadar/manual,
#    bukan otomatis tiap deploy.
#  - icd:set-bahasa-aktif adalah keputusan tampilan (Indonesia/Inggris)
#    yang admin bisa juga ubah lewat UI (menu Pengaturan > Data ICD-10 >
#    tombol "Ganti Bahasa") -- kalau dipaksa jalan tiap update.sh, pilihan
#    admin di UI akan ketimpa balik tiap deploy. Jadi ini SATU KALI saja.
#
#  Cara pakai: sudo bash icd10_setup_awal.sh
# =============================================================

set -e

GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

info()    { echo -e "${GREEN}[INFO]${NC} $1"; }
warning() { echo -e "${YELLOW}[WARN]${NC} $1"; }

echo ""
echo "=============================================="
echo "   Setup Awal ICD-10 Bilingual (Indonesia/Inggris)"
echo "=============================================="
echo ""

# ── 1. Cek kondisi data sekarang ──────────────────────────────
info "STEP 1: Cek kondisi tabel icd10 saat ini..."
php8.3 artisan tinker --execute="
echo 'Total kode      : ' . App\Models\IcdDiagnosis::count() . PHP_EOL;
echo 'Punya nama_en   : ' . App\Models\IcdDiagnosis::whereNotNull('nama_en')->where('nama_en','!=','')->count() . PHP_EOL;
echo 'bahasa_icd saat ini: ' . (App\Models\Klinik::first()?->bahasa_icd ?? 'id') . PHP_EOL;
"
echo ""
warning "Kalau 'Punya nama_en' jauh lebih kecil dari 'Total kode' (mis. cuma ratusan dari ribuan), berarti dataset bilingual lengkap dari master_icd_x.json belum pernah diimpor penuh -- lanjutkan skrip ini untuk mengisinya."
echo ""
read -p "Lanjutkan import + koreksi + ganti bahasa ke Inggris? (y/n) " -n 1 -r
echo ""
if [[ ! $REPLY =~ ^[Yy]$ ]]; then
    warning "Dibatalkan oleh user."
    exit 0
fi

# ── 2. Import ulang dataset bilingual dari master_icd_x.json ──
# Idempotent (upsert) -- baris yang tidak ada di file tidak disentuh/dihapus.
info "STEP 2: Import dataset bilingual dari master_icd_x.json..."
php8.3 artisan icd:import \
    --lang=id \
    --sumber="master_icd_x.json (Kemenkes bilingual mapping, WHO-derived)" \
    --versi="initial-import"

# ── 3. Kategori level blok + koreksi data (idempotent, aman diulang) ──
info "STEP 3: Backfill kategori blok & koreksi data (apostrof eponim, dst)..."
php8.3 artisan icd:kategori-backfill
php8.3 artisan icd:koreksi-manual

# ── 4. Ganti bahasa aktif ke Inggris ───────────────────────────
info "STEP 4: Ganti bahasa aktif ICD-10 ke Inggris..."
php8.3 artisan icd:set-bahasa-aktif en

# ── 5. Bersihkan cache ──────────────────────────────────────────
info "STEP 5: Bersihkan cache..."
php8.3 artisan config:clear
php8.3 artisan view:clear

echo ""
echo "=============================================="
echo -e "   ${GREEN}SETUP ICD-10 SELESAI!${NC}"
echo "=============================================="
echo ""
echo "  Waktu   : $(date '+%d-%m-%Y %H:%M:%S')"
echo ""
info "Mulai sekarang, icd:kategori-backfill & icd:koreksi-manual otomatis"
info "jalan tiap 'sudo bash update.sh' (STEP 5d) -- skrip ini TIDAK perlu"
info "dijalankan lagi kecuali admin ingin ganti bahasa aktif lagi manual"
info "(lewat UI Pengaturan > Data ICD-10, atau ulangi STEP 4 di atas)."
