# Product Requirements Document (PRD)
# Setup Asuransi & Manajemen Penjamin (Insurance / Guarantor)

| Info | Detail |
|:-----|:-------|
| **Versi** | 1.0.0 |
| **Tanggal** | Mei 2026 |
| **Status** | Draft |
| **Depends On** | `PRD_EMR_Laravel.md` · `setup_pasien.md` · `PRD_Modul_Kasir_Update.md` · `PRD_Manajemen_Inventory.md` |
| **Tech Stack** | Laravel 12 · Livewire 3 · MySQL · Tailwind CSS · DomPDF · Maatwebsite Excel |
| **Scope** | BPJS (kerjasama on/off) · Master Asuransi · Asuransi per Pasien (many-to-many) · Pemilihan saat Registrasi · Split Cover saat Pembayaran · Piutang Asuransi · Penagihan & Pelunasan |

---

## Daftar Isi

1. [Ringkasan Eksekutif](#1-ringkasan-eksekutif)
2. [Tujuan & Sasaran](#2-tujuan--sasaran)
3. [Role & Hak Akses](#3-role--hak-akses)
4. [Konsep & Alur End-to-End](#4-konsep--alur-end-to-end)
5. [Skema Database & Migration](#5-skema-database--migration)
6. [Modul 1 — Konfigurasi BPJS](#6-modul-1--konfigurasi-bpjs)
7. [Modul 2 — Master Data Asuransi](#7-modul-2--master-data-asuransi)
8. [Modul 3 — Asuransi per Pasien](#8-modul-3--asuransi-per-pasien)
9. [Modul 4 — Pemilihan Asuransi saat Registrasi](#9-modul-4--pemilihan-asuransi-saat-registrasi)
10. [Modul 5 — Split Cover saat Pembayaran](#10-modul-5--split-cover-saat-pembayaran)
11. [Modul 6 — Piutang Asuransi](#11-modul-6--piutang-asuransi)
12. [Modul 7 — Penagihan & Pelunasan](#12-modul-7--penagihan--pelunasan)
13. [Model Eloquent](#13-model-eloquent)
14. [Service Layer](#14-service-layer)
15. [Livewire Components](#15-livewire-components)
16. [Route & Controller](#16-route--controller)
17. [Struktur Folder](#17-struktur-folder)
18. [User Stories & Business Rules](#18-user-stories--business-rules)
19. [Seeder Data Awal](#19-seeder-data-awal)
20. [Catatan Integrasi Akuntansi (Tahap Lanjut)](#20-catatan-integrasi-akuntansi-tahap-lanjut)

---

## 1. Ringkasan Eksekutif

Modul Setup Asuransi mengelola seluruh siklus penjaminan biaya pasien — dari konfigurasi BPJS, pendaftaran asuransi swasta, penentuan cover per kategori layanan, hingga pengakuan piutang dan pelunasan dari pihak penjamin. Modul ini mengubah alur "bayar tunai langsung lunas" menjadi alur yang mendukung **penjaminan pihak ketiga** dengan tracking piutang.

```
┌────────────────────────────────────────────────────────────────────┐
│              SIKLUS PENJAMINAN ASURANSI                            │
│                                                                    │
│  Setup BPJS (kerjasama?) ──► aktif/nonaktif                       │
│  Master Asuransi Swasta ──► diskon % per kategori                 │
│        │                                                           │
│        ▼                                                           │
│  Pasien daftarkan asuransi (bisa > 1) ────────────────────────┐   │
│        │                                                       │   │
│        ▼                                                       │   │
│  Registrasi Kunjungan → pilih asuransi / umum                 │   │
│        │                                                       │   │
│        ▼                                                       │   │
│  Billing dihitung:                                             │   │
│   ├── Item ter-cover asuransi → jadi PIUTANG asuransi         │   │
│   └── Item tidak ter-cover    → WAJIB bayar Tunai/Non-Tunai   │   │
│        │                                                       │   │
│        ▼                                                       │   │
│  Piutang Asuransi tercatat (belum jadi pendapatan)            │   │
│        │                                                       │   │
│        ▼                                                       │   │
│  Keuangan tagih ke asuransi → asuransi bayar                  │   │
│        │                                                       │   │
│        ▼                                                       │   │
│  Piutang lunas → masuk PENDAPATAN                             │   │
│        │                                                       │   │
│        └──► (Tahap lanjut: jurnal, buku besar, neraca)        │   │
└────────────────────────────────────────────────────────────────────┘
```

---

## 2. Tujuan & Sasaran

### Tujuan Utama
- Mendukung pembayaran tagihan dengan penjaminan pihak ketiga (BPJS & asuransi swasta)
- Memisahkan piutang asuransi dari pendapatan kas riil
- Memberikan visibilitas penagihan dan pelunasan ke pihak keuangan

### Sasaran Teknis
- BPJS dapat di-on/off berdasarkan status kerjasama klinik
- Master asuransi dengan diskon/cover berbeda per kategori (Prosedur, Lab, Radiologi, Peralatan)
- Relasi pasien ↔ asuransi **many-to-many** (1 pasien bisa banyak asuransi)
- Pemisahan billing: porsi cover → piutang, porsi tidak cover → wajib bayar langsung
- Tracking lifecycle piutang: `tertagih → ditagih → dibayar_sebagian → lunas`

### KPI
- Akurasi perhitungan cover per kategori 100%
- Zero pendapatan diakui sebelum piutang dibayar
- Aging piutang asuransi termonitor (umur piutang)

---

## 3. Role & Hak Akses

| Aksi | super_admin | admin | keuangan | kasir | front_office |
|------|:-----------:|:-----:|:--------:|:-----:|:------------:|
| Konfigurasi BPJS | ✅ | ✅ | ❌ | ❌ | ❌ |
| Master Asuransi | ✅ | ✅ | ✅ | ❌ | ❌ |
| Daftarkan asuransi pasien | ✅ | ✅ | ❌ | ✅ | ✅ |
| Pilih asuransi saat registrasi | ✅ | ✅ | ❌ | ✅ | ✅ |
| Proses pembayaran split cover | ✅ | ✅ | ❌ | ✅ | ❌ |
| Lihat piutang asuransi | ✅ | ✅ | ✅ | 👁 | ❌ |
| Proses penagihan | ✅ | ✅ | ✅ | ❌ | ❌ |
| Catat pelunasan asuransi | ✅ | ✅ | ✅ | ❌ | ❌ |

> **keuangan** adalah role baru untuk bagian keuangan/finance. Ditambahkan ke `RolePermissionSeeder`.

### Permissions (Spatie)

```php
$permissions = [
    'asuransi.config_bpjs',
    'asuransi.master.view', 'asuransi.master.manage',
    'asuransi.pasien.manage',
    'piutang.view', 'piutang.tagih', 'piutang.lunas',
    'laporan.piutang.view',
];
```

---

## 4. Konsep & Alur End-to-End

### 4.1 Kategori Cover Layanan

Setiap asuransi mendefinisikan diskon/cover berbeda untuk 4 kategori:

| Kategori | Contoh Item | Sumber Tarif |
|----------|-------------|--------------|
| **Prosedur** | Konsultasi, tindakan medis | `master_tindakan` |
| **Laboratorium** | Tes darah, urin, dll | master lab (kategori `lab`) |
| **Radiologi** | Rontgen, USG, CT scan | master radiologi (kategori `radiologi`) |
| **Peralatan** | Obat, alkes, BHP | `barang` (jenis obat/alkes) |

### 4.2 Logika Perhitungan Cover

```
Untuk setiap item di billing:
  1. Tentukan kategori item (prosedur/lab/radiologi/peralatan)
  2. Ambil diskon % asuransi untuk kategori tsb
  3. Jumlah ter-cover = harga_item × (diskon_persen / 100)
  4. Jumlah sisa pasien = harga_item - jumlah ter-cover

Total billing:
  - Total Cover Asuransi → jadi PIUTANG
  - Total Sisa Pasien    → WAJIB bayar Tunai/Non-Tunai saat itu juga
```

**Contoh:**

```
Invoice items:
  Konsултasi (prosedur)   Rp 150.000  → cover 100% → Rp 150.000 piutang, Rp 0 pasien
  Lab darah (lab)         Rp 200.000  → cover  80% → Rp 160.000 piutang, Rp 40.000 pasien
  Obat (peralatan)        Rp 100.000  → cover  50% → Rp  50.000 piutang, Rp 50.000 pasien
  ──────────────────────────────────────────────────────────────────────────────────
  TOTAL                   Rp 450.000     Piutang: Rp 360.000  |  Pasien bayar: Rp 90.000

Pasien bayar Rp 90.000 (tunai/non-tunai) → invoice "lunas sebagian (cover)"
Rp 360.000 → piutang asuransi → ditagih → dibayar → masuk pendapatan
```

---

## 5. Skema Database & Migration

### 5.1 Urutan Migration

```
2026_01_01_000400_create_config_bpjs_table.php
2026_01_01_000401_create_asuransi_table.php
2026_01_01_000402_create_pasien_asuransi_table.php       ← many-to-many
2026_01_01_000403_update_kunjungan_add_asuransi.php
2026_01_01_000404_update_billing_add_asuransi.php
2026_01_01_000405_create_piutang_asuransi_table.php
2026_01_01_000406_create_penagihan_asuransi_table.php
2026_01_01_000407_create_penagihan_item_table.php
2026_01_01_000408_create_pembayaran_asuransi_table.php
```

### 5.2 Tabel `config_bpjs`

```php
// 2026_01_01_000400_create_config_bpjs_table.php
// Single-row config (hanya 1 record)

Schema::create('config_bpjs', function (Blueprint $table) {
    $table->id();
    $table->boolean('kerjasama')->default(false);       // klinik kerjasama BPJS?
    $table->boolean('is_active')->default(false);        // BPJS aktif sebagai opsi penjamin?
    $table->string('kode_faskes', 30)->nullable();       // kode faskes BPJS
    $table->string('nama_faskes', 150)->nullable();
    $table->date('tanggal_kerjasama')->nullable();
    $table->date('tanggal_berakhir')->nullable();
    $table->text('catatan')->nullable();
    $table->timestamps();
});
```

> **Aturan:** Jika `kerjasama = false`, maka `is_active` dipaksa `false` dan BPJS tidak muncul sebagai opsi penjamin di manapun.

### 5.3 Tabel `asuransi`

```php
// 2026_01_01_000401_create_asuransi_table.php

Schema::create('asuransi', function (Blueprint $table) {
    $table->id();
    $table->string('kode', 30)->unique();                // ASR-001
    $table->string('nama', 150);                          // Prudential, AXA, dll
    $table->enum('tipe', ['swasta', 'bpjs', 'pemerintah', 'corporate'])
          ->default('swasta');

    // Periode kontrak / kerjasama
    $table->date('periode_mulai')->nullable();
    $table->date('periode_berakhir')->nullable();        // masa berlaku kerjasama

    // Diskon / cover % per kategori
    $table->decimal('cover_prosedur', 5, 2)->default(0);    // 0-100 %
    $table->decimal('cover_laboratorium', 5, 2)->default(0);
    $table->decimal('cover_radiologi', 5, 2)->default(0);
    $table->decimal('cover_peralatan', 5, 2)->default(0);

    // Batas plafon (opsional)
    $table->decimal('plafon_per_kunjungan', 14, 2)->nullable();
    $table->decimal('plafon_per_tahun', 16, 2)->nullable();

    // Kontak penagihan
    $table->string('pic', 100)->nullable();
    $table->string('telepon', 20)->nullable();
    $table->string('email')->nullable();
    $table->text('alamat')->nullable();
    $table->unsignedSmallInteger('term_pembayaran_hari')->default(30); // TOP

    $table->boolean('is_active')->default(true);
    $table->timestamps();

    $table->index(['nama', 'is_active']);
});
```

### 5.4 Tabel `pasien_asuransi` (Many-to-Many)

```php
// 2026_01_01_000402_create_pasien_asuransi_table.php
// 1 pasien bisa punya banyak asuransi

Schema::create('pasien_asuransi', function (Blueprint $table) {
    $table->id();
    $table->foreignId('pasien_id')
          ->constrained('pasien')->onDelete('cascade');
    $table->foreignId('asuransi_id')
          ->constrained('asuransi')->onDelete('cascade');

    $table->string('nomor_polis', 50);                   // nomor kepesertaan
    $table->string('nama_pemegang_polis', 100)->nullable();
    $table->date('berlaku_mulai')->nullable();
    $table->date('berlaku_sampai')->nullable();
    $table->boolean('is_primary')->default(false);       // asuransi utama
    $table->boolean('is_active')->default(true);
    $table->timestamps();

    $table->unique(['pasien_id', 'asuransi_id', 'nomor_polis']);
    $table->index('pasien_id');
});
```

### 5.5 Update `kunjungan` — Asuransi Terpilih

```php
// 2026_01_01_000403_update_kunjungan_add_asuransi.php

Schema::table('kunjungan', function (Blueprint $table) {
    // null = pasien umum (tidak pakai asuransi)
    $table->foreignId('pasien_asuransi_id')->nullable()
          ->after('tipe_pembayaran')
          ->constrained('pasien_asuransi')->nullOnDelete();
});
```

### 5.6 Update `billing` — Pemisahan Cover

```php
// 2026_01_01_000404_update_billing_add_asuransi.php

Schema::table('billing', function (Blueprint $table) {
    $table->decimal('total_cover_asuransi', 14, 2)->default(0)->after('total_tagihan');
    $table->decimal('total_tanggungan_pasien', 14, 2)->default(0)->after('total_cover_asuransi');
    $table->foreignId('asuransi_id')->nullable()->after('total_tanggungan_pasien')
          ->constrained('asuransi')->nullOnDelete();
});
```

### 5.7 Tabel `piutang_asuransi`

```php
// 2026_01_01_000405_create_piutang_asuransi_table.php

Schema::create('piutang_asuransi', function (Blueprint $table) {
    $table->id();
    $table->string('nomor_piutang', 30)->unique();       // PIT-2026-05-0001
    $table->foreignId('billing_id')
          ->constrained('billing')->onDelete('restrict');
    $table->foreignId('asuransi_id')
          ->constrained('asuransi')->onDelete('restrict');
    $table->foreignId('pasien_id')
          ->constrained('pasien')->onDelete('restrict');

    $table->decimal('jumlah_piutang', 14, 2);            // = total_cover_asuransi
    $table->decimal('jumlah_dibayar', 14, 2)->default(0);
    $table->decimal('sisa_piutang', 14, 2);

    $table->date('tanggal_piutang');                      // tanggal billing
    $table->date('tanggal_jatuh_tempo')->nullable();      // berdasarkan TOP asuransi

    $table->enum('status', [
        'tertagih',          // baru terbentuk, belum diajukan klaim
        'diajukan',          // sudah diajukan ke asuransi (ditagih)
        'dibayar_sebagian',  // asuransi bayar sebagian
        'lunas',             // asuransi bayar penuh
        'ditolak',           // klaim ditolak asuransi
    ])->default('tertagih');

    $table->foreignId('penagihan_id')->nullable()         // link ke batch penagihan
          ->constrained('penagihan_asuransi')->nullOnDelete();
    $table->text('catatan')->nullable();
    $table->timestamps();

    $table->index(['asuransi_id', 'status']);
    $table->index('tanggal_jatuh_tempo');
});
```

### 5.8 Tabel `penagihan_asuransi` (Batch Klaim)

```php
// 2026_01_01_000406_create_penagihan_asuransi_table.php
// Sekumpulan piutang yang ditagih bersamaan ke 1 asuransi

Schema::create('penagihan_asuransi', function (Blueprint $table) {
    $table->id();
    $table->string('nomor_penagihan', 30)->unique();     // TAG-2026-05-0001
    $table->foreignId('asuransi_id')
          ->constrained('asuransi')->onDelete('restrict');
    $table->foreignId('dibuat_oleh')
          ->constrained('users')->onDelete('restrict');

    $table->date('tanggal_penagihan');
    $table->date('periode_mulai')->nullable();            // periode klaim
    $table->date('periode_akhir')->nullable();

    $table->decimal('total_tagihan', 16, 2)->default(0);
    $table->decimal('total_dibayar', 16, 2)->default(0);

    $table->enum('status', ['draft', 'diajukan', 'dibayar_sebagian', 'lunas', 'ditutup'])
          ->default('draft');
    $table->text('catatan')->nullable();
    $table->timestamps();

    $table->index(['asuransi_id', 'status']);
});
```

### 5.9 Tabel `penagihan_item`

```php
// 2026_01_01_000407_create_penagihan_item_table.php

Schema::create('penagihan_item', function (Blueprint $table) {
    $table->id();
    $table->foreignId('penagihan_id')
          ->constrained('penagihan_asuransi')->onDelete('cascade');
    $table->foreignId('piutang_asuransi_id')
          ->constrained('piutang_asuransi')->onDelete('restrict');
    $table->decimal('jumlah_diajukan', 14, 2);
    $table->decimal('jumlah_disetujui', 14, 2)->nullable(); // hasil verifikasi asuransi
    $table->timestamps();

    $table->index('penagihan_id');
});
```

### 5.10 Tabel `pembayaran_asuransi` (Pelunasan dari Asuransi)

```php
// 2026_01_01_000408_create_pembayaran_asuransi_table.php

Schema::create('pembayaran_asuransi', function (Blueprint $table) {
    $table->id();
    $table->string('nomor_pembayaran', 30)->unique();    // BYR-2026-05-0001
    $table->foreignId('penagihan_id')
          ->constrained('penagihan_asuransi')->onDelete('restrict');
    $table->foreignId('asuransi_id')
          ->constrained('asuransi')->onDelete('restrict');
    $table->foreignId('dicatat_oleh')
          ->constrained('users')->onDelete('restrict');

    $table->decimal('jumlah_bayar', 16, 2);
    $table->date('tanggal_bayar');
    $table->enum('metode', ['transfer', 'cek', 'giro', 'tunai'])->default('transfer');
    $table->string('nomor_referensi', 100)->nullable();   // no. transfer/cek
    $table->text('catatan')->nullable();
    $table->timestamps();

    $table->index('penagihan_id');
});
```

---

## 6. Modul 1 — Konfigurasi BPJS

### 6.1 Aturan Bisnis

```
JIKA kerjasama = TRUE:
  → is_active boleh diaktifkan
  → BPJS muncul sebagai opsi penjamin di registrasi

JIKA kerjasama = FALSE:
  → is_active dipaksa FALSE (otomatis)
  → BPJS TIDAK muncul di manapun
  → Toggle is_active disabled di UI
```

### 6.2 Livewire — Config BPJS

```php
// app/Livewire/Pengaturan/Asuransi/ConfigBpjsForm.php

namespace App\Livewire\Pengaturan\Asuransi;

use Livewire\Component;
use App\Models\ConfigBpjs;

class ConfigBpjsForm extends Component
{
    public bool   $kerjasama        = false;
    public bool   $isActive         = false;
    public string $kodeFaskes       = '';
    public string $namaFaskes       = '';
    public string $tanggalKerjasama = '';
    public string $tanggalBerakhir  = '';
    public string $catatan          = '';

    public function mount(): void
    {
        $config = ConfigBpjs::firstOrCreate(['id' => 1], [
            'kerjasama' => false, 'is_active' => false,
        ]);
        $this->kerjasama        = $config->kerjasama;
        $this->isActive         = $config->is_active;
        $this->kodeFaskes       = $config->kode_faskes ?? '';
        $this->namaFaskes       = $config->nama_faskes ?? '';
        $this->tanggalKerjasama = $config->tanggal_kerjasama?->format('Y-m-d') ?? '';
        $this->tanggalBerakhir  = $config->tanggal_berakhir?->format('Y-m-d') ?? '';
        $this->catatan          = $config->catatan ?? '';
    }

    // Jika kerjasama dimatikan → paksa is_active false
    public function updatedKerjasama($value): void
    {
        if (!$value) {
            $this->isActive = false;
        }
    }

    public function simpan(): void
    {
        $this->validate([
            'kodeFaskes'       => ['nullable', 'string', 'max:30'],
            'namaFaskes'       => ['nullable', 'string', 'max:150'],
            'tanggalKerjasama' => ['nullable', 'date'],
            'tanggalBerakhir'  => ['nullable', 'date', 'after_or_equal:tanggalKerjasama'],
        ]);

        // Enforce aturan: tanpa kerjasama tidak boleh aktif
        $isActive = $this->kerjasama ? $this->isActive : false;

        ConfigBpjs::updateOrCreate(['id' => 1], [
            'kerjasama'         => $this->kerjasama,
            'is_active'         => $isActive,
            'kode_faskes'       => $this->kodeFaskes ?: null,
            'nama_faskes'       => $this->namaFaskes ?: null,
            'tanggal_kerjasama' => $this->tanggalKerjasama ?: null,
            'tanggal_berakhir'  => $this->tanggalBerakhir ?: null,
            'catatan'           => $this->catatan ?: null,
        ]);

        $this->isActive = $isActive;
        session()->flash('success', 'Konfigurasi BPJS berhasil disimpan.');
    }

    public function render()
    {
        return view('livewire.pengaturan.asuransi.config-bpjs-form');
    }
}
```

### 6.3 Blade — Config BPJS

```blade
{{-- resources/views/livewire/pengaturan/asuransi/config-bpjs-form.blade.php --}}
<div class="card max-w-2xl">
    <div class="card-header">
        <h3 class="text-sm font-semibold text-gray-700">🏥 Konfigurasi BPJS Kesehatan</h3>
    </div>
    <div class="card-body space-y-5">

        {{-- Toggle Kerjasama --}}
        <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
            <div>
                <p class="font-medium text-gray-900">Kerjasama dengan BPJS</p>
                <p class="text-xs text-gray-500">Apakah klinik/RS bekerjasama dengan BPJS Kesehatan?</p>
            </div>
            <label class="relative inline-flex items-center cursor-pointer">
                <input type="checkbox" wire:model.live="kerjasama" class="sr-only peer" />
                <div class="w-11 h-6 bg-gray-300 rounded-full peer peer-checked:bg-primary-600
                            peer-checked:after:translate-x-full after:content-[''] after:absolute
                            after:top-0.5 after:left-0.5 after:bg-white after:rounded-full
                            after:h-5 after:w-5 after:transition-all"></div>
            </label>
        </div>

        {{-- Toggle Aktif (hanya jika kerjasama) --}}
        <div class="flex items-center justify-between p-4 rounded-lg
                    {{ $kerjasama ? 'bg-emerald-50' : 'bg-gray-100 opacity-50' }}">
            <div>
                <p class="font-medium text-gray-900">Aktifkan BPJS sebagai Penjamin</p>
                <p class="text-xs text-gray-500">
                    @if($kerjasama)
                        BPJS akan muncul sebagai opsi penjamin saat registrasi
                    @else
                        Aktifkan kerjasama terlebih dahulu
                    @endif
                </p>
            </div>
            <label class="relative inline-flex items-center {{ $kerjasama ? 'cursor-pointer' : 'cursor-not-allowed' }}">
                <input type="checkbox" wire:model="isActive" class="sr-only peer" @disabled(!$kerjasama) />
                <div class="w-11 h-6 bg-gray-300 rounded-full peer peer-checked:bg-emerald-600
                            peer-checked:after:translate-x-full after:content-[''] after:absolute
                            after:top-0.5 after:left-0.5 after:bg-white after:rounded-full
                            after:h-5 after:w-5 after:transition-all"></div>
            </label>
        </div>

        {{-- Detail Faskes (hanya jika kerjasama) --}}
        @if($kerjasama)
        <div class="space-y-4 animate-fade-in">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="form-group">
                    <label class="form-label">Kode Faskes</label>
                    <input type="text" wire:model="kodeFaskes" class="form-input" placeholder="Kode faskes BPJS" />
                </div>
                <div class="form-group">
                    <label class="form-label">Nama Faskes</label>
                    <input type="text" wire:model="namaFaskes" class="form-input" />
                </div>
                <div class="form-group">
                    <label class="form-label">Tanggal Kerjasama</label>
                    <input type="date" wire:model="tanggalKerjasama" class="form-input" />
                </div>
                <div class="form-group">
                    <label class="form-label">Berakhir</label>
                    <input type="date" wire:model="tanggalBerakhir" class="form-input" />
                </div>
            </div>
        </div>
        @endif

        <div class="flex justify-end">
            <button wire:click="simpan" class="btn-primary">Simpan Konfigurasi</button>
        </div>
    </div>
</div>
```

---

## 7. Modul 2 — Master Data Asuransi

### 7.1 Lokasi Menu

```
Pengaturan
└── Penjamin / Asuransi (dropdown)
    ├── Konfigurasi BPJS
    └── Master Asuransi
```

### 7.2 Fitur
- CRUD asuransi (nama, tipe, periode, masa berlaku)
- Diskon/cover % per 4 kategori (Prosedur, Lab, Radiologi, Peralatan)
- Plafon per kunjungan & per tahun (opsional)
- Kontak penagihan + TOP (term of payment)
- Nonaktifkan asuransi

### 7.3 Livewire — Form Asuransi

```php
// app/Livewire/Pengaturan/Asuransi/AsuransiForm.php

namespace App\Livewire\Pengaturan\Asuransi;

use Livewire\Component;
use App\Models\Asuransi;
use App\Services\Asuransi\AsuransiService;
use Illuminate\Validation\Rule;

class AsuransiForm extends Component
{
    public ?Asuransi $asuransi = null;
    public bool      $isEdit   = false;

    public string $kode            = '';
    public string $nama            = '';
    public string $tipe            = 'swasta';
    public string $periodeMulai    = '';
    public string $periodeBerakhir = '';

    // Cover per kategori
    public float  $coverProsedur     = 0;
    public float  $coverLaboratorium = 0;
    public float  $coverRadiologi    = 0;
    public float  $coverPeralatan    = 0;

    // Plafon
    public string $plafonPerKunjungan = '';
    public string $plafonPerTahun     = '';

    // Kontak
    public string $pic                 = '';
    public string $telepon             = '';
    public string $email               = '';
    public string $alamat              = '';
    public int    $termPembayaranHari  = 30;

    protected function rules(): array
    {
        return [
            'kode'  => ['required', 'string', 'max:30',
                        Rule::unique('asuransi', 'kode')->ignore($this->asuransi?->id)],
            'nama'  => ['required', 'string', 'max:150'],
            'tipe'  => ['required', Rule::in(['swasta','bpjs','pemerintah','corporate'])],
            'periodeMulai'    => ['nullable', 'date'],
            'periodeBerakhir' => ['nullable', 'date', 'after_or_equal:periodeMulai'],
            'coverProsedur'     => ['numeric', 'min:0', 'max:100'],
            'coverLaboratorium' => ['numeric', 'min:0', 'max:100'],
            'coverRadiologi'    => ['numeric', 'min:0', 'max:100'],
            'coverPeralatan'    => ['numeric', 'min:0', 'max:100'],
            'termPembayaranHari'=> ['integer', 'min:0', 'max:365'],
        ];
    }

    public function mount(?Asuransi $asuransi = null): void
    {
        if ($asuransi?->exists) {
            $this->isEdit   = true;
            $this->asuransi = $asuransi;
            $this->fill([
                'kode' => $asuransi->kode, 'nama' => $asuransi->nama, 'tipe' => $asuransi->tipe,
                'periodeMulai'    => $asuransi->periode_mulai?->format('Y-m-d') ?? '',
                'periodeBerakhir' => $asuransi->periode_berakhir?->format('Y-m-d') ?? '',
                'coverProsedur'     => $asuransi->cover_prosedur,
                'coverLaboratorium' => $asuransi->cover_laboratorium,
                'coverRadiologi'    => $asuransi->cover_radiologi,
                'coverPeralatan'    => $asuransi->cover_peralatan,
                'plafonPerKunjungan'=> $asuransi->plafon_per_kunjungan ?? '',
                'plafonPerTahun'    => $asuransi->plafon_per_tahun ?? '',
                'pic'     => $asuransi->pic ?? '', 'telepon' => $asuransi->telepon ?? '',
                'email'   => $asuransi->email ?? '', 'alamat' => $asuransi->alamat ?? '',
                'termPembayaranHari' => $asuransi->term_pembayaran_hari,
            ]);
        } else {
            $this->kode = app(AsuransiService::class)->generateKode();
        }
    }

    public function simpan(AsuransiService $service): void
    {
        $this->validate();

        $data = [
            'kode' => $this->kode, 'nama' => $this->nama, 'tipe' => $this->tipe,
            'periode_mulai'    => $this->periodeMulai ?: null,
            'periode_berakhir' => $this->periodeBerakhir ?: null,
            'cover_prosedur'     => $this->coverProsedur,
            'cover_laboratorium' => $this->coverLaboratorium,
            'cover_radiologi'    => $this->coverRadiologi,
            'cover_peralatan'    => $this->coverPeralatan,
            'plafon_per_kunjungan' => $this->plafonPerKunjungan ?: null,
            'plafon_per_tahun'     => $this->plafonPerTahun ?: null,
            'pic' => $this->pic ?: null, 'telepon' => $this->telepon ?: null,
            'email' => $this->email ?: null, 'alamat' => $this->alamat ?: null,
            'term_pembayaran_hari' => $this->termPembayaranHari,
        ];

        if ($this->isEdit) {
            $service->update($this->asuransi, $data);
            session()->flash('success', 'Asuransi berhasil diperbarui.');
        } else {
            $service->create($data);
            session()->flash('success', 'Asuransi berhasil ditambahkan.');
        }

        $this->redirectRoute('pengaturan.asuransi.index');
    }

    public function render()
    {
        return view('livewire.pengaturan.asuransi.asuransi-form');
    }
}
```

### 7.4 Blade — Form Asuransi (bagian cover)

```blade
{{-- bagian cover per kategori di asuransi-form.blade.php --}}
<div class="card">
    <div class="card-header">
        <h3 class="text-sm font-semibold text-gray-700">Cover / Diskon per Kategori (%)</h3>
    </div>
    <div class="card-body grid grid-cols-1 md:grid-cols-2 gap-4">
        @foreach([
            'coverProsedur'     => '🩺 Prosedur',
            'coverLaboratorium' => '🧪 Laboratorium',
            'coverRadiologi'    => '📷 Radiologi',
            'coverPeralatan'    => '💊 Peralatan (Obat/Alkes)',
        ] as $field => $label)
        <div class="form-group">
            <label class="form-label">{{ $label }}</label>
            <div class="relative">
                <input type="number" wire:model="{{ $field }}"
                    class="form-input pr-8" min="0" max="100" step="0.01" placeholder="0" />
                <span class="absolute inset-y-0 right-3 flex items-center text-gray-400 text-sm">%</span>
            </div>
            @error($field) <p class="form-error">{{ $message }}</p> @enderror
        </div>
        @endforeach
    </div>
</div>
```

---

## 8. Modul 3 — Asuransi per Pasien

### 8.1 Konsep

Satu pasien bisa memiliki **lebih dari 1 asuransi** (relasi many-to-many via `pasien_asuransi`). Dikelola di halaman detail pasien.

### 8.2 Livewire — Kelola Asuransi Pasien

```php
// app/Livewire/Pasien/AsuransiPasienManager.php

namespace App\Livewire\Pasien;

use Livewire\Component;
use App\Models\{Pasien, Asuransi, PasienAsuransi};

class AsuransiPasienManager extends Component
{
    public Pasien $pasien;
    public array  $daftarAsuransi = [];

    // Form tambah
    public bool   $showForm        = false;
    public int    $asuransiId      = 0;
    public string $nomorPolis      = '';
    public string $namaPemegang    = '';
    public string $berlakuMulai    = '';
    public string $berlakuSampai   = '';
    public bool   $isPrimary       = false;

    public function mount(Pasien $pasien): void
    {
        $this->pasien = $pasien;
        $this->loadData();
    }

    public function loadData(): void
    {
        $this->daftarAsuransi = PasienAsuransi::with('asuransi')
            ->where('pasien_id', $this->pasien->id)
            ->where('is_active', true)
            ->get()
            ->toArray();
    }

    protected function rules(): array
    {
        return [
            'asuransiId'  => ['required', 'exists:asuransi,id'],
            'nomorPolis'  => ['required', 'string', 'max:50'],
            'namaPemegang'=> ['nullable', 'string', 'max:100'],
            'berlakuMulai'  => ['nullable', 'date'],
            'berlakuSampai' => ['nullable', 'date', 'after_or_equal:berlakuMulai'],
        ];
    }

    public function tambah(): void
    {
        $this->validate();

        // Cek duplikat asuransi+polis
        $exists = PasienAsuransi::where('pasien_id', $this->pasien->id)
            ->where('asuransi_id', $this->asuransiId)
            ->where('nomor_polis', $this->nomorPolis)
            ->exists();

        if ($exists) {
            $this->addError('nomorPolis', 'Asuransi dengan polis ini sudah terdaftar.');
            return;
        }

        // Jika set primary → unset yang lain
        if ($this->isPrimary) {
            PasienAsuransi::where('pasien_id', $this->pasien->id)
                ->update(['is_primary' => false]);
        }

        PasienAsuransi::create([
            'pasien_id'           => $this->pasien->id,
            'asuransi_id'         => $this->asuransiId,
            'nomor_polis'         => $this->nomorPolis,
            'nama_pemegang_polis' => $this->namaPemegang ?: null,
            'berlaku_mulai'       => $this->berlakuMulai ?: null,
            'berlaku_sampai'      => $this->berlakuSampai ?: null,
            'is_primary'          => $this->isPrimary,
        ]);

        $this->reset(['asuransiId','nomorPolis','namaPemegang','berlakuMulai','berlakuSampai','isPrimary','showForm']);
        $this->loadData();
        session()->flash('success', 'Asuransi pasien ditambahkan.');
    }

    public function setPrimary(int $id): void
    {
        PasienAsuransi::where('pasien_id', $this->pasien->id)->update(['is_primary' => false]);
        PasienAsuransi::where('id', $id)->update(['is_primary' => true]);
        $this->loadData();
    }

    public function hapus(int $id): void
    {
        PasienAsuransi::where('id', $id)->update(['is_active' => false]);
        $this->loadData();
    }

    public function render()
    {
        return view('livewire.pasien.asuransi-pasien-manager', [
            'opsiAsuransi' => Asuransi::where('is_active', true)->orderBy('nama')->get(),
        ]);
    }
}
```

---

## 9. Modul 4 — Pemilihan Asuransi saat Registrasi

### 9.1 Konsep

Saat registrasi kunjungan, FO memilih: **Umum (tanpa asuransi)** atau salah satu asuransi yang dimiliki pasien.

```php
// app/Livewire/Kunjungan/PendaftaranForm.php — TAMBAHAN

public string $tipePenjamin   = 'umum';  // umum | asuransi
public ?int   $pasienAsuransiId = null;
public array  $asuransiPasien  = [];

public function updatedPasienId($pasienId): void
{
    // Load daftar asuransi aktif milik pasien
    $this->asuransiPasien = \App\Models\PasienAsuransi::with('asuransi')
        ->where('pasien_id', $pasienId)
        ->where('is_active', true)
        ->whereHas('asuransi', fn($q) => $q->where('is_active', true))
        ->get()
        ->map(fn($pa) => [
            'id'           => $pa->id,
            'nama'         => $pa->asuransi->nama,
            'nomor_polis'  => $pa->nomor_polis,
            'is_primary'   => $pa->is_primary,
            'berlaku'      => $pa->berlaku_sampai
                ? \Carbon\Carbon::parse($pa->berlaku_sampai)->isFuture()
                : true,
        ])
        ->toArray();

    // Auto-select asuransi primary jika ada
    $primary = collect($this->asuransiPasien)->firstWhere('is_primary', true);
    if ($primary) {
        $this->tipePenjamin     = 'asuransi';
        $this->pasienAsuransiId = $primary['id'];
    }
}

// Saat simpan kunjungan:
// 'tipe_pembayaran'    => $this->tipePenjamin,
// 'pasien_asuransi_id' => $this->tipePenjamin === 'asuransi' ? $this->pasienAsuransiId : null,
```

### 9.2 Blade — Pilih Penjamin

```blade
{{-- section penjamin di pendaftaran-form.blade.php --}}
<div class="card">
    <div class="card-header"><h3 class="text-sm font-semibold text-gray-700">Penjamin Biaya</h3></div>
    <div class="card-body space-y-3">

        {{-- Pilih Umum / Asuransi --}}
        <div class="flex gap-3">
            <button type="button" wire:click="$set('tipePenjamin', 'umum')"
                class="flex-1 py-2.5 rounded-lg border text-sm font-medium
                    {{ $tipePenjamin === 'umum' ? 'bg-primary-50 border-primary-500 text-primary-700' : 'border-gray-300' }}">
                💵 Umum (Bayar Sendiri)
            </button>
            <button type="button" wire:click="$set('tipePenjamin', 'asuransi')"
                @disabled(empty($asuransiPasien))
                class="flex-1 py-2.5 rounded-lg border text-sm font-medium
                    {{ $tipePenjamin === 'asuransi' ? 'bg-emerald-50 border-emerald-500 text-emerald-700' : 'border-gray-300' }}
                    {{ empty($asuransiPasien) ? 'opacity-40 cursor-not-allowed' : '' }}">
                🏥 Asuransi / Penjamin
            </button>
        </div>

        {{-- Pilih asuransi spesifik --}}
        @if($tipePenjamin === 'asuransi')
        <div class="space-y-2 animate-fade-in">
            @forelse($asuransiPasien as $pa)
            <label class="flex items-center justify-between p-3 rounded-lg border cursor-pointer
                {{ $pasienAsuransiId == $pa['id'] ? 'border-emerald-500 bg-emerald-50' : 'border-gray-200' }}">
                <div class="flex items-center gap-3">
                    <input type="radio" wire:model="pasienAsuransiId" value="{{ $pa['id'] }}" class="form-radio" />
                    <div>
                        <span class="font-medium text-gray-900">{{ $pa['nama'] }}</span>
                        @if($pa['is_primary']) <span class="badge badge-primary ml-1">Utama</span> @endif
                        <div class="text-xs text-gray-500">Polis: {{ $pa['nomor_polis'] }}</div>
                    </div>
                </div>
                @if(!$pa['berlaku'])
                    <span class="badge badge-danger">Kadaluarsa</span>
                @endif
            </label>
            @empty
            <p class="text-sm text-gray-400">Pasien belum memiliki asuransi terdaftar.</p>
            @endforelse
        </div>
        @endif
    </div>
</div>
```

---

## 10. Modul 5 — Split Cover saat Pembayaran

### 10.1 Service — Hitung Cover Billing

```php
// app/Services/Asuransi/CoverCalculatorService.php

namespace App\Services\Asuransi;

use App\Models\{Billing, Asuransi};

class CoverCalculatorService
{
    /**
     * Hitung porsi cover asuransi & tanggungan pasien per item
     */
    public function hitungCover(Billing $billing, Asuransi $asuransi): array
    {
        $items       = $this->kumpulkanItem($billing);
        $totalCover  = 0;
        $totalPasien = 0;
        $rincian     = [];

        foreach ($items as $item) {
            $coverPersen = $this->getCoverPersen($asuransi, $item['kategori']);
            $jumlahCover = round($item['subtotal'] * ($coverPersen / 100), 2);
            $jumlahPasien= $item['subtotal'] - $jumlahCover;

            $totalCover  += $jumlahCover;
            $totalPasien += $jumlahPasien;

            $rincian[] = array_merge($item, [
                'cover_persen'  => $coverPersen,
                'jumlah_cover'  => $jumlahCover,
                'jumlah_pasien' => $jumlahPasien,
            ]);
        }

        // Terapkan plafon per kunjungan jika ada
        if ($asuransi->plafon_per_kunjungan && $totalCover > $asuransi->plafon_per_kunjungan) {
            $selisih = $totalCover - $asuransi->plafon_per_kunjungan;
            $totalCover  = $asuransi->plafon_per_kunjungan;
            $totalPasien += $selisih;
        }

        return [
            'total_tagihan'     => $billing->total_tagihan,
            'total_cover'       => $totalCover,
            'total_pasien'      => $totalPasien,
            'rincian'           => $rincian,
        ];
    }

    private function getCoverPersen(Asuransi $asuransi, string $kategori): float
    {
        return match ($kategori) {
            'prosedur'      => $asuransi->cover_prosedur,
            'laboratorium'  => $asuransi->cover_laboratorium,
            'radiologi'     => $asuransi->cover_radiologi,
            'peralatan'     => $asuransi->cover_peralatan,
            default         => 0,
        };
    }

    /**
     * Kumpulkan semua item billing + tentukan kategorinya
     */
    private function kumpulkanItem(Billing $billing): array
    {
        $items = [];

        // Tindakan → prosedur / lab / radiologi (lihat kategori master_tindakan)
        foreach ($billing->kunjungan->tindakan ?? [] as $t) {
            $kategori = match ($t->masterTindakan->kategori) {
                'lab', 'laboratorium' => 'laboratorium',
                'radiologi'           => 'radiologi',
                default               => 'prosedur',
            };
            $items[] = [
                'nama'     => $t->masterTindakan->nama,
                'kategori' => $kategori,
                'subtotal' => $t->jumlah * $t->masterTindakan->tarif,
            ];
        }

        // Obat/Alkes → peralatan
        foreach ($billing->kunjungan->resep->items ?? [] as $ri) {
            $items[] = [
                'nama'     => $ri->obat->nama,
                'kategori' => 'peralatan',
                'subtotal' => $ri->jumlah * $ri->obat->harga_jual,
            ];
        }

        return $items;
    }
}
```

### 10.2 Service — Proses Pembayaran dengan Asuransi

Memperluas `BillingService` dari `PRD_Modul_Kasir_Update.md`.

```php
// app/Services/Asuransi/PembayaranAsuransiService.php

namespace App\Services\Asuransi;

use App\Models\{Billing, Asuransi, PiutangAsuransi, PembayaranSplit, SesiKas};
use Illuminate\Support\Facades\DB;

class PembayaranAsuransiService
{
    public function __construct(
        private CoverCalculatorService $calculator
    ) {}

    /**
     * Proses billing dengan penjamin asuransi:
     *  - Porsi cover → piutang asuransi
     *  - Porsi pasien → wajib bayar tunai/non-tunai sekarang
     */
    public function prosesPembayaranAsuransi(
        Billing $billing,
        Asuransi $asuransi,
        array    $pembayaranPasien,  // split tunai/non-tunai untuk porsi pasien
        int      $userId,
        SesiKas  $sesiKas
    ): Billing {
        $hitung = $this->calculator->hitungCover($billing, $asuransi);

        $totalCover  = $hitung['total_cover'];
        $totalPasien = $hitung['total_pasien'];

        // Validasi: pembayaran pasien harus = porsi tanggungan pasien
        $totalBayarPasien = collect($pembayaranPasien)->sum('jumlah');
        if (abs($totalBayarPasien - $totalPasien) > 0.01) {
            throw new \InvalidArgumentException(
                "Pembayaran pasien (Rp " . number_format($totalBayarPasien,0,',','.') . ") " .
                "harus sama dengan tanggungan pasien (Rp " . number_format($totalPasien,0,',','.') . ")."
            );
        }

        // Validasi: porsi pasien hanya boleh tunai/non-tunai (bukan asuransi/deposit lain)
        $metodeValid = ['tunai','debit','kredit','transfer','qris'];
        foreach ($pembayaranPasien as $bayar) {
            if (!in_array($bayar['metode'], $metodeValid)) {
                throw new \InvalidArgumentException(
                    "Item tidak ter-cover wajib dibayar tunai/non-tunai. Metode '{$bayar['metode']}' tidak diizinkan."
                );
            }
        }

        return DB::transaction(function () use (
            $billing, $asuransi, $pembayaranPasien, $userId, $sesiKas, $totalCover, $totalPasien
        ) {
            // 1. Catat pembayaran porsi pasien (tunai/non-tunai)
            foreach ($pembayaranPasien as $bayar) {
                PembayaranSplit::create([
                    'billing_id'  => $billing->id,
                    'sesi_kas_id' => $sesiKas->id,
                    'user_id'     => $userId,
                    'metode'      => $bayar['metode'],
                    'jumlah'      => $bayar['jumlah'],
                    'referensi'   => $bayar['referensi'] ?? null,
                ]);
            }

            // 2. Buat piutang asuransi untuk porsi cover
            if ($totalCover > 0) {
                $jatuhTempo = now()->addDays($asuransi->term_pembayaran_hari);

                PiutangAsuransi::create([
                    'nomor_piutang'        => $this->generateNomorPiutang(),
                    'billing_id'           => $billing->id,
                    'asuransi_id'          => $asuransi->id,
                    'pasien_id'            => $billing->kunjungan->pasien_id,
                    'jumlah_piutang'       => $totalCover,
                    'jumlah_dibayar'       => 0,
                    'sisa_piutang'         => $totalCover,
                    'tanggal_piutang'      => now(),
                    'tanggal_jatuh_tempo'  => $jatuhTempo,
                    'status'               => 'tertagih',
                ]);
            }

            // 3. Update billing
            $billing->update([
                'total_cover_asuransi'    => $totalCover,
                'total_tanggungan_pasien' => $totalPasien,
                'asuransi_id'             => $asuransi->id,
                'total_bayar'             => $totalPasien,  // hanya porsi pasien = pendapatan kas
                'sisa'                    => 0,             // dari sisi pasien sudah lunas
                'status'                  => 'lunas',       // lunas dari sisi pasien
                'sesi_kas_id'             => $sesiKas->id,
            ]);

            return $billing->fresh();
        });
    }

    private function generateNomorPiutang(): string
    {
        $prefix = 'PIT-' . now()->format('Y-m-');
        $last   = PiutangAsuransi::where('nomor_piutang', 'like', $prefix.'%')
                    ->orderByDesc('nomor_piutang')->value('nomor_piutang');
        $seq    = $last ? (int) substr($last, -4) + 1 : 1;
        return $prefix . str_pad($seq, 4, '0', STR_PAD_LEFT);
    }
}
```

### 10.3 Aturan Penting

```
✓ Porsi cover asuransi → TIDAK masuk kas, jadi PIUTANG
✓ Porsi pasien → WAJIB dibayar tunai/non-tunai (debit/kredit/transfer/qris)
✓ Porsi pasien TIDAK boleh dibayar pakai metode 'asuransi' atau 'deposit' lain
✓ Billing status = lunas (dari sisi pasien), namun piutang asuransi masih open
✓ total_bayar di billing = hanya porsi pasien (pendapatan kas riil)
✓ total_cover_asuransi dicatat terpisah (belum jadi pendapatan)
```

---

## 11. Modul 6 — Piutang Asuransi

### 11.1 Lifecycle Piutang

```
tertagih ──► diajukan ──► dibayar_sebagian ──► lunas
                              │
                              └──► (atau langsung lunas)
                   
                 ditolak (jika klaim ditolak asuransi)
```

### 11.2 Livewire — Daftar Piutang

```php
// app/Livewire/Keuangan/Piutang/PiutangTable.php

namespace App\Livewire\Keuangan\Piutang;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\PiutangAsuransi;

class PiutangTable extends Component
{
    use WithPagination;

    public string $search        = '';
    public int    $filterAsuransi = 0;
    public string $filterStatus  = '';
    public array  $selected      = [];   // untuk batch penagihan

    public function getPiutangProperty()
    {
        return PiutangAsuransi::query()
            ->with(['asuransi', 'pasien', 'billing'])
            ->when($this->filterAsuransi, fn($q) => $q->where('asuransi_id', $this->filterAsuransi))
            ->when($this->filterStatus, fn($q) => $q->where('status', $this->filterStatus))
            ->when($this->search, fn($q) => $q
                ->where('nomor_piutang', 'like', "%{$this->search}%")
                ->orWhereHas('pasien', fn($q2) => $q2->where('nama','like',"%{$this->search}%"))
            )
            ->orderByDesc('tanggal_piutang')
            ->paginate(20);
    }

    public function toggleSelect(int $id): void
    {
        in_array($id, $this->selected)
            ? $this->selected = array_diff($this->selected, [$id])
            : $this->selected[] = $id;
    }

    public function render()
    {
        return view('livewire.keuangan.piutang.piutang-table', [
            'piutang'  => $this->piutang,
            'summary'  => [
                'total_outstanding' => PiutangAsuransi::whereIn('status', ['tertagih','diajukan','dibayar_sebagian'])->sum('sisa_piutang'),
                'total_tertagih'    => PiutangAsuransi::where('status','tertagih')->sum('sisa_piutang'),
                'jatuh_tempo'       => PiutangAsuransi::whereIn('status',['tertagih','diajukan'])
                                        ->whereDate('tanggal_jatuh_tempo','<',today())->count(),
            ],
            'opsiAsuransi' => \App\Models\Asuransi::where('is_active',true)->orderBy('nama')->get(),
        ]);
    }
}
```

---

## 12. Modul 7 — Penagihan & Pelunasan

### 12.1 Alur

```
1. Keuangan pilih piutang (status tertagih) per asuransi
        │
2. Buat batch Penagihan (nomor TAG-xxx) → status piutang jadi "diajukan"
        │
3. Ajukan klaim ke asuransi (cetak/export berkas)
        │
4. Asuransi verifikasi & bayar (sebagian/penuh)
        │
5. Keuangan catat Pembayaran Asuransi (nomor BYR-xxx)
        │
        ├── Update sisa_piutang per piutang
        ├── Jika sisa = 0 → status piutang "lunas"
        ├── Jika sebagian → status "dibayar_sebagian"
        │
6. Saat piutang LUNAS → diakui sebagai PENDAPATAN
        │
        └──► (Tahap lanjut: posting ke jurnal & buku besar)
```

### 12.2 Service — Penagihan

```php
// app/Services/Asuransi/PenagihanService.php

namespace App\Services\Asuransi;

use App\Models\{PenagihanAsuransi, PiutangAsuransi, PembayaranAsuransi};
use Illuminate\Support\Facades\DB;

class PenagihanService
{
    /**
     * Buat batch penagihan dari sekumpulan piutang
     */
    public function buatPenagihan(int $asuransiId, array $piutangIds, int $userId): PenagihanAsuransi
    {
        return DB::transaction(function () use ($asuransiId, $piutangIds, $userId) {
            $piutangList = PiutangAsuransi::whereIn('id', $piutangIds)
                ->where('asuransi_id', $asuransiId)
                ->where('status', 'tertagih')
                ->get();

            if ($piutangList->isEmpty()) {
                throw new \RuntimeException('Tidak ada piutang valid untuk ditagih.');
            }

            $total = $piutangList->sum('sisa_piutang');

            $penagihan = PenagihanAsuransi::create([
                'nomor_penagihan'  => $this->generateNomorPenagihan(),
                'asuransi_id'      => $asuransiId,
                'dibuat_oleh'      => $userId,
                'tanggal_penagihan'=> now(),
                'total_tagihan'    => $total,
                'status'           => 'diajukan',
            ]);

            foreach ($piutangList as $piutang) {
                $penagihan->items()->create([
                    'piutang_asuransi_id' => $piutang->id,
                    'jumlah_diajukan'     => $piutang->sisa_piutang,
                ]);

                $piutang->update([
                    'status'       => 'diajukan',
                    'penagihan_id' => $penagihan->id,
                ]);
            }

            return $penagihan->load('items.piutang');
        });
    }

    /**
     * Catat pembayaran dari asuransi → update piutang
     */
    public function catatPembayaran(
        PenagihanAsuransi $penagihan,
        float   $jumlahBayar,
        string  $metode,
        string  $tanggalBayar,
        ?string $nomorReferensi,
        int     $userId
    ): PembayaranAsuransi {
        return DB::transaction(function () use (
            $penagihan, $jumlahBayar, $metode, $tanggalBayar, $nomorReferensi, $userId
        ) {
            // Catat pembayaran
            $pembayaran = PembayaranAsuransi::create([
                'nomor_pembayaran' => $this->generateNomorPembayaran(),
                'penagihan_id'     => $penagihan->id,
                'asuransi_id'      => $penagihan->asuransi_id,
                'dicatat_oleh'     => $userId,
                'jumlah_bayar'     => $jumlahBayar,
                'tanggal_bayar'    => $tanggalBayar,
                'metode'           => $metode,
                'nomor_referensi'  => $nomorReferensi,
            ]);

            // Distribusikan pembayaran ke piutang (FIFO)
            $sisaBayar = $jumlahBayar;
            foreach ($penagihan->items as $item) {
                if ($sisaBayar <= 0) break;

                $piutang = $item->piutang;
                $alokasi = min($sisaBayar, $piutang->sisa_piutang);

                $piutang->increment('jumlah_dibayar', $alokasi);
                $piutang->decrement('sisa_piutang', $alokasi);

                $piutang->update([
                    'status' => $piutang->sisa_piutang <= 0 ? 'lunas' : 'dibayar_sebagian',
                ]);

                $sisaBayar -= $alokasi;

                // ── HOOK: saat piutang LUNAS → akui sebagai pendapatan ──
                if ($piutang->fresh()->status === 'lunas') {
                    $this->akuiPendapatan($piutang);
                }
            }

            // Update status penagihan
            $penagihan->increment('total_dibayar', $jumlahBayar);
            $penagihan->update([
                'status' => $penagihan->total_dibayar >= $penagihan->total_tagihan
                    ? 'lunas' : 'dibayar_sebagian',
            ]);

            return $pembayaran;
        });
    }

    /**
     * Hook pengakuan pendapatan saat piutang lunas
     * (Tahap lanjut: posting jurnal)
     */
    private function akuiPendapatan(PiutangAsuransi $piutang): void
    {
        // Placeholder untuk integrasi akuntansi:
        // - Buat entri jurnal: Debit Kas/Bank, Kredit Piutang Asuransi
        // - Akui pendapatan dari porsi cover
        // Lihat Section 20 untuk detail integrasi akuntansi.

        \App\Models\AuditKasir::create([
            'user_id'        => auth()->id(),
            'aksi'           => 'pakai_deposit', // gunakan enum yang sesuai / tambah 'akui_pendapatan'
            'referensi_tipe' => 'piutang_asuransi',
            'referensi_id'   => $piutang->id,
            'detail'         => [
                'nomor_piutang' => $piutang->nomor_piutang,
                'jumlah'        => $piutang->jumlah_piutang,
                'keterangan'    => 'Piutang asuransi lunas → diakui pendapatan',
            ],
        ]);
    }

    private function generateNomorPenagihan(): string
    {
        $prefix = 'TAG-' . now()->format('Y-m-');
        $last = PenagihanAsuransi::where('nomor_penagihan','like',$prefix.'%')
                  ->orderByDesc('nomor_penagihan')->value('nomor_penagihan');
        $seq = $last ? (int) substr($last, -4) + 1 : 1;
        return $prefix . str_pad($seq, 4, '0', STR_PAD_LEFT);
    }

    private function generateNomorPembayaran(): string
    {
        $prefix = 'BYR-' . now()->format('Y-m-');
        $last = PembayaranAsuransi::where('nomor_pembayaran','like',$prefix.'%')
                  ->orderByDesc('nomor_pembayaran')->value('nomor_pembayaran');
        $seq = $last ? (int) substr($last, -4) + 1 : 1;
        return $prefix . str_pad($seq, 4, '0', STR_PAD_LEFT);
    }
}
```

---

## 13. Model Eloquent

```php
// app/Models/Asuransi.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsToMany, HasMany};

class Asuransi extends Model
{
    protected $table    = 'asuransi';
    protected $fillable = [
        'kode','nama','tipe','periode_mulai','periode_berakhir',
        'cover_prosedur','cover_laboratorium','cover_radiologi','cover_peralatan',
        'plafon_per_kunjungan','plafon_per_tahun',
        'pic','telepon','email','alamat','term_pembayaran_hari','is_active',
    ];
    protected $casts = [
        'periode_mulai'    => 'date',
        'periode_berakhir' => 'date',
        'is_active'        => 'boolean',
    ];

    public function pasien(): BelongsToMany
    {
        return $this->belongsToMany(Pasien::class, 'pasien_asuransi')
                    ->withPivot(['nomor_polis','is_primary','is_active'])
                    ->withTimestamps();
    }

    public function piutang(): HasMany { return $this->hasMany(PiutangAsuransi::class); }

    public function scopeActive($q) { return $q->where('is_active', true); }

    public function getIsBerlakuAttribute(): bool
    {
        if (!$this->periode_berakhir) return true;
        return $this->periode_berakhir->isFuture();
    }
}
```

```php
// app/Models/PasienAsuransi.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PasienAsuransi extends Model
{
    protected $table    = 'pasien_asuransi';
    protected $fillable = [
        'pasien_id','asuransi_id','nomor_polis','nama_pemegang_polis',
        'berlaku_mulai','berlaku_sampai','is_primary','is_active',
    ];
    protected $casts = [
        'berlaku_mulai'  => 'date',
        'berlaku_sampai' => 'date',
        'is_primary'     => 'boolean',
        'is_active'      => 'boolean',
    ];

    public function pasien():   BelongsTo { return $this->belongsTo(Pasien::class); }
    public function asuransi(): BelongsTo { return $this->belongsTo(Asuransi::class); }
}
```

```php
// app/Models/PiutangAsuransi.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PiutangAsuransi extends Model
{
    protected $table    = 'piutang_asuransi';
    protected $fillable = [
        'nomor_piutang','billing_id','asuransi_id','pasien_id',
        'jumlah_piutang','jumlah_dibayar','sisa_piutang',
        'tanggal_piutang','tanggal_jatuh_tempo','status','penagihan_id','catatan',
    ];
    protected $casts = [
        'tanggal_piutang'     => 'date',
        'tanggal_jatuh_tempo' => 'date',
    ];

    public function asuransi():  BelongsTo { return $this->belongsTo(Asuransi::class); }
    public function pasien():    BelongsTo { return $this->belongsTo(Pasien::class); }
    public function billing():   BelongsTo { return $this->belongsTo(Billing::class); }
    public function penagihan(): BelongsTo { return $this->belongsTo(PenagihanAsuransi::class); }

    public function getIsJatuhTempoAttribute(): bool
    {
        return $this->tanggal_jatuh_tempo
            && $this->tanggal_jatuh_tempo->isPast()
            && in_array($this->status, ['tertagih','diajukan']);
    }

    public function getUmurPiutangAttribute(): int
    {
        return $this->tanggal_piutang->diffInDays(now());
    }
}
```

```php
// app/Models/ConfigBpjs.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ConfigBpjs extends Model
{
    protected $table    = 'config_bpjs';
    protected $fillable = [
        'kerjasama','is_active','kode_faskes','nama_faskes',
        'tanggal_kerjasama','tanggal_berakhir','catatan',
    ];
    protected $casts = [
        'kerjasama'        => 'boolean',
        'is_active'        => 'boolean',
        'tanggal_kerjasama'=> 'date',
        'tanggal_berakhir' => 'date',
    ];

    public static function aktif(): bool
    {
        $config = static::first();
        return $config && $config->kerjasama && $config->is_active;
    }
}
```

```php
// app/Models/PenagihanAsuransi.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

class PenagihanAsuransi extends Model
{
    protected $table    = 'penagihan_asuransi';
    protected $fillable = [
        'nomor_penagihan','asuransi_id','dibuat_oleh','tanggal_penagihan',
        'periode_mulai','periode_akhir','total_tagihan','total_dibayar','status','catatan',
    ];
    protected $casts = [
        'tanggal_penagihan' => 'date',
        'periode_mulai'     => 'date',
        'periode_akhir'     => 'date',
    ];

    public function asuransi(): BelongsTo { return $this->belongsTo(Asuransi::class); }
    public function items():    HasMany   { return $this->hasMany(PenagihanItem::class, 'penagihan_id'); }
    public function pembayaran():HasMany   { return $this->hasMany(PembayaranAsuransi::class, 'penagihan_id'); }
}
```

---

## 14. Service Layer

```
app/Services/Asuransi/
├── AsuransiService.php             # CRUD master asuransi + generateKode
├── CoverCalculatorService.php      # hitung cover per kategori
├── PembayaranAsuransiService.php   # split cover → piutang + bayar pasien
└── PenagihanService.php            # buat penagihan + catat pelunasan
```

---

## 15. Livewire Components

```
app/Livewire/
├── Pengaturan/Asuransi/
│   ├── ConfigBpjsForm.php          # toggle kerjasama & aktif
│   ├── AsuransiTable.php           # daftar master asuransi
│   └── AsuransiForm.php            # CRUD asuransi + cover per kategori
│
├── Pasien/
│   └── AsuransiPasienManager.php   # kelola asuransi pasien (many-to-many)
│
├── Kunjungan/
│   └── PendaftaranForm.php         # + pilih penjamin (umum/asuransi)
│
├── Kasir/Billing/
│   └── PembayaranAsuransiForm.php  # split cover + bayar porsi pasien
│
└── Keuangan/
    ├── Piutang/
    │   └── PiutangTable.php         # daftar piutang + aging
    ├── Penagihan/
    │   ├── PenagihanForm.php        # buat batch penagihan
    │   └── PenagihanDetail.php      # detail + catat pembayaran
    └── Pembayaran/
        └── CatatPembayaranForm.php  # input pelunasan asuransi
```

---

## 16. Route & Controller

```php
// routes/web.php

Route::middleware(['auth'])->group(function () {

    // Pengaturan Asuransi
    Route::prefix('pengaturan/asuransi')->name('pengaturan.asuransi.')->group(function () {
        Route::get('/bpjs',    [AsuransiController::class, 'configBpjs'])->name('bpjs')
             ->middleware('permission:asuransi.config_bpjs');
        Route::get('/',        [AsuransiController::class, 'index'])->name('index')
             ->middleware('permission:asuransi.master.view');
        Route::get('/create',  [AsuransiController::class, 'create'])->name('create')
             ->middleware('permission:asuransi.master.manage');
        Route::get('/{asuransi}/edit', [AsuransiController::class, 'edit'])->name('edit')
             ->middleware('permission:asuransi.master.manage');
    });

    // Keuangan — Piutang & Penagihan
    Route::prefix('keuangan')->name('keuangan.')->middleware('permission:piutang.view')->group(function () {
        Route::get('/piutang',              [PiutangController::class, 'index'])->name('piutang.index');
        Route::get('/penagihan',            [PenagihanController::class, 'index'])->name('penagihan.index');
        Route::post('/penagihan',           [PenagihanController::class, 'store'])->name('penagihan.store')
             ->middleware('permission:piutang.tagih');
        Route::get('/penagihan/{penagihan}',[PenagihanController::class, 'show'])->name('penagihan.show');
        Route::post('/penagihan/{penagihan}/bayar', [PenagihanController::class, 'catatBayar'])
             ->name('penagihan.bayar')
             ->middleware('permission:piutang.lunas');
    });
});
```

---

## 17. Struktur Folder

```
app/
├── Models/
│   ├── ConfigBpjs.php
│   ├── Asuransi.php
│   ├── PasienAsuransi.php
│   ├── PiutangAsuransi.php
│   ├── PenagihanAsuransi.php
│   ├── PenagihanItem.php
│   └── PembayaranAsuransi.php
│
├── Livewire/                       # (lihat Section 15)
├── Services/Asuransi/              # (lihat Section 14)
│
├── Http/
│   ├── Controllers/
│   │   ├── AsuransiController.php
│   │   ├── PiutangController.php
│   │   └── PenagihanController.php
│   └── Requests/Asuransi/
│       ├── StoreAsuransiRequest.php
│       ├── StorePasienAsuransiRequest.php
│       └── CatatPembayaranRequest.php
│
database/
├── migrations/                     # 9 file (Section 5.1)
└── seeders/
    ├── ConfigBpjsSeeder.php
    └── AsuransiSeeder.php

resources/views/
├── livewire/
│   ├── pengaturan/asuransi/
│   ├── pasien/asuransi-pasien-manager.blade.php
│   ├── kasir/billing/pembayaran-asuransi-form.blade.php
│   └── keuangan/
└── pengaturan/asuransi/
```

---

## 18. User Stories & Business Rules

| ID | Persona | Skenario | Expected Behavior |
|----|---------|----------|-------------------|
| US01 | Admin | Klinik tidak kerjasama BPJS | Toggle aktif disabled, BPJS tidak muncul sebagai opsi penjamin |
| US02 | Admin | Aktifkan kerjasama BPJS | Toggle aktif menjadi enabled, bisa diaktifkan |
| US03 | Admin | Matikan kerjasama BPJS | `is_active` otomatis dipaksa false |
| US04 | Admin | Buat asuransi "Prudential" cover Prosedur 100%, Lab 80%, Radiologi 70%, Peralatan 50% | Tersimpan ke master, muncul di pilihan asuransi pasien |
| US05 | FO | Daftarkan 2 asuransi ke 1 pasien | Keduanya tersimpan, salah satu bisa di-set primary |
| US06 | FO | Registrasi kunjungan, pilih asuransi | `kunjungan.pasien_asuransi_id` terisi |
| US07 | FO | Registrasi pasien tanpa asuransi | `tipe_pembayaran = umum`, tidak ada piutang |
| US08 | Kasir | Bayar invoice Rp 450.000 dengan asuransi cover Rp 360.000 | Pasien wajib bayar Rp 90.000 tunai/non-tunai, Rp 360.000 jadi piutang |
| US09 | Kasir | Coba bayar porsi pasien pakai metode "deposit" | Error: porsi tidak cover wajib tunai/non-tunai |
| US10 | Kasir | Pembayaran porsi pasien kurang dari tanggungan | Error: jumlah harus = tanggungan pasien |
| US11 | Keuangan | Lihat daftar piutang | Tampil outstanding, aging, jatuh tempo per asuransi |
| US12 | Keuangan | Buat batch penagihan 10 piutang | Status piutang → "diajukan", nomor TAG- terbentuk |
| US13 | Keuangan | Asuransi bayar penuh | Piutang → "lunas", diakui sebagai pendapatan |
| US14 | Keuangan | Asuransi bayar sebagian | Piutang → "dibayar_sebagian", sisa tetap outstanding |
| US15 | Keuangan | Lihat piutang jatuh tempo | Filter piutang dengan `tanggal_jatuh_tempo < today` |

---

## 19. Seeder Data Awal

```php
// database/seeders/ConfigBpjsSeeder.php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ConfigBpjs;

class ConfigBpjsSeeder extends Seeder
{
    public function run(): void
    {
        ConfigBpjs::updateOrCreate(['id' => 1], [
            'kerjasama' => false,   // default: belum kerjasama
            'is_active' => false,
        ]);
        $this->command->info('✓ Config BPJS diinisialisasi (default: tidak kerjasama)');
    }
}
```

```php
// database/seeders/AsuransiSeeder.php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Asuransi;

class AsuransiSeeder extends Seeder
{
    public function run(): void
    {
        $list = [
            [
                'kode' => 'ASR-001', 'nama' => 'Prudential', 'tipe' => 'swasta',
                'cover_prosedur' => 100, 'cover_laboratorium' => 80,
                'cover_radiologi' => 70, 'cover_peralatan' => 50,
                'plafon_per_kunjungan' => 5000000, 'term_pembayaran_hari' => 30,
                'pic' => 'Customer Service', 'telepon' => '0215551234',
            ],
            [
                'kode' => 'ASR-002', 'nama' => 'AXA Mandiri', 'tipe' => 'swasta',
                'cover_prosedur' => 90, 'cover_laboratorium' => 90,
                'cover_radiologi' => 80, 'cover_peralatan' => 60,
                'plafon_per_kunjungan' => 3000000, 'term_pembayaran_hari' => 45,
            ],
            [
                'kode' => 'ASR-003', 'nama' => 'Asuransi Corporate PT Sehat', 'tipe' => 'corporate',
                'cover_prosedur' => 100, 'cover_laboratorium' => 100,
                'cover_radiologi' => 100, 'cover_peralatan' => 100,
                'term_pembayaran_hari' => 60,
            ],
        ];

        foreach ($list as $a) {
            Asuransi::updateOrCreate(['kode' => $a['kode']], $a);
            $this->command->info("✓ Asuransi: {$a['nama']}");
        }
    }
}
```

```php
// DatabaseSeeder.php
$this->call([ConfigBpjsSeeder::class, AsuransiSeeder::class]);
```

```bash
php artisan migrate
php artisan db:seed --class=ConfigBpjsSeeder
php artisan db:seed --class=AsuransiSeeder
```

---

## 20. Catatan Integrasi Akuntansi (Tahap Lanjut)

Modul ini menyiapkan fondasi untuk integrasi akuntansi yang akan dikembangkan di PRD terpisah (`PRD_Akuntansi.md`). Berikut pemetaan konsep akuntansi yang relevan:

### 20.1 Pengakuan Transaksi

| Event | Jurnal (Debit) | Jurnal (Kredit) |
|-------|----------------|------------------|
| Billing dengan asuransi terbentuk | Piutang Asuransi (porsi cover) + Kas (porsi pasien) | Pendapatan Jasa / Penjualan |
| Asuransi membayar piutang | Kas / Bank | Piutang Asuransi |
| Klaim ditolak asuransi | Beban Klaim Ditolak / Piutang Pasien | Piutang Asuransi |

> **Catatan:** Pada modul ini, pendapatan dari porsi pasien (tunai/non-tunai) langsung diakui saat pembayaran. Porsi cover asuransi menjadi piutang dan **baru diakui penuh saat dilunasi** (sesuai permintaan: "jika pihak asuransi sudah membayar maka baru masuk ke pendapatan").

### 20.2 Hook Pengakuan Pendapatan

Method `PenagihanService::akuiPendapatan()` adalah titik integrasi untuk:
- Membuat entri jurnal otomatis (Debit Kas, Kredit Piutang)
- Posting ke buku besar (general ledger)
- Update neraca & laporan laba rugi

### 20.3 Aging Piutang (untuk Neraca)

Piutang dikelompokkan berdasarkan umur untuk pelaporan:

```
0-30 hari    : Lancar
31-60 hari   : Perhatian khusus
61-90 hari   : Kurang lancar
> 90 hari    : Diragukan / macet
```

> Implementasi laporan aging, jurnal, buku besar, dan neraca akan dirinci di **PRD_Akuntansi.md** sebagai tahap pengembangan selanjutnya.

---

*PRD_Setup_Asuransi.md v1.0.0*  
*Konsisten dengan PRD_EMR_Laravel.md · setup_pasien.md · PRD_Modul_Kasir_Update.md · PRD_Manajemen_Inventory.md*  
*(Laravel 12 · Livewire 3 · MySQL · Tailwind CSS · DomPDF)*