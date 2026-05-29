# Product Requirements Document (PRD)
# Penjualan Obat Ritel (Tanpa Konsultasi Dokter)

| Info | Detail |
|:-----|:-------|
| **Versi** | 1.0.0 |
| **Tanggal** | Mei 2026 |
| **Status** | Draft |
| **Depends On** | `resep_obat.md` · Unifikasi `barang` (selesai) · `modul_kasir.md` |
| **Tech Stack** | Laravel 12 · Livewire 3 · MySQL · Tailwind CSS |
| **Scope** | Transaksi obat ritel langsung di farmasi tanpa registrasi kunjungan dokter |

---

## Daftar Isi

1. [Ringkasan Eksekutif](#1-ringkasan-eksekutif)
2. [Alur Bisnis](#2-alur-bisnis)
3. [Perbedaan Ritel vs Resep Dokter](#3-perbedaan-ritel-vs-resep-dokter)
4. [Fitur & Spesifikasi Fungsional](#4-fitur--spesifikasi-fungsional)
5. [Skema Database & Migration](#5-skema-database--migration)
6. [Model Eloquent](#6-model-eloquent)
7. [Service Layer](#7-service-layer)
8. [Livewire Components](#8-livewire-components)
9. [Route & Navigasi](#9-route--navigasi)
10. [Role & Hak Akses](#10-role--hak-akses)
11. [Business Rules](#11-business-rules)
12. [User Stories](#12-user-stories)
13. [Seeder & Data Awal](#13-seeder--data-awal)
14. [Urutan Implementasi](#14-urutan-implementasi)

---

## 1. Ringkasan Eksekutif

Modul **Penjualan Obat Ritel** memungkinkan pasien/pembeli membeli obat langsung di apotek klinik **tanpa menemui dokter**. Apoteker berperan sebagai pencatat transaksi sekaligus pemberi rekomendasi obat bebas/bebas terbatas.

```
┌───────────────────────────────────────────────────────────────────────────┐
│                     ALUR OBAT RITEL                                       │
│                                                                           │
│   Pembeli datang                                                          │
│        │                                                                  │
│        ▼                                                                  │
│   [1] Apoteker catat identitas pembeli (nama + HP, RM opsional)           │
│        │                                                                  │
│        ▼                                                                  │
│   [2] Apoteker input obat yang diminta (cari dari master barang)          │
│        │                                                                  │
│        ▼                                                                  │
│   [3] Submit → Transaksi masuk antrian kasir                              │
│        │                                                                  │
│        ▼                                                                  │
│   [4] Kasir proses pembayaran (tunai / transfer / kartu)                  │
│        │                                                                  │
│        ▼                                                                  │
│   [5] Status berubah → "Siap Diambil" (tampil di layar antrian)           │
│        │                                                                  │
│        ▼                                                                  │
│   [6] Apoteker serahkan obat → Selesai + potong stok                     │
└───────────────────────────────────────────────────────────────────────────┘
```

---

## 2. Alur Bisnis

### Step 1 — Identifikasi Pembeli (oleh Apoteker)
- Pembeli datang ke loket farmasi
- Apoteker membuka form **Transaksi Ritel Baru**
- Input identitas:
  - **Nama** (wajib)
  - **Nomor HP** (opsional, untuk konfirmasi)
  - **Nomor RM** (opsional — jika pembeli adalah pasien terdaftar, bisa dilink)
- Jika pembeli tidak punya RM → sistem buat identitas anonim dengan nama yang diinput

### Step 2 — Input Obat (oleh Apoteker)
- Apoteker mencari obat dari master `barang` (jenis: `obat` atau `alkes`)
- Sistem menampilkan: nama, kode, harga jual, stok tersedia, satuan
- Apoteker input jumlah per item
- Obat bebas terbatas (`butuh_resep = true`) → tampil warning: *"Obat ini memerlukan resep dokter — pastikan pembeli memiliki resep sebelumnya"*
- Apoteker bisa tambah multiple item dalam satu transaksi
- Sistem hitung subtotal dan total otomatis

### Step 3 — Submit ke Kasir
- Apoteker klik "Submit ke Kasir"
- Status transaksi → `menunggu_kasir`
- Transaksi muncul di antrian kasir
- Nomor antrian ritel digenerate (format: `RIT-YYYYMMDD-XXXX`)

### Step 4 — Pembayaran (oleh Kasir)
- Kasir melihat daftar transaksi ritel menunggu pembayaran
- Kasir pilih metode pembayaran:
  - Tunai (cash)
  - Transfer bank
  - Kartu debit/kredit
  - Kombinasi (split payment)
- Kasir konfirmasi pembayaran → status → `dibayar`
- Struk pembayaran digenerate

### Step 5 — Penyerahan Obat (oleh Apoteker)
- Status `dibayar` muncul di antrian farmasi apoteker
- Apoteker siapkan obat
- Apoteker klik "Serahkan Obat" → status → `selesai`
- Stok barang dipotong saat ini (ketika diserahkan)
- `MutasiStok` dibuat dengan tipe `keluar_ritel`

### Catatan Pembatalan
- Transaksi hanya bisa dibatalkan jika **belum dibayar** (`menunggu_kasir`)
- Apoteker atau kasir bisa batalkan
- Jika dibatalkan → stok tidak terpotong (karena potong dilakukan saat selesai)

---

## 3. Perbedaan Ritel vs Resep Dokter

| Aspek | Resep Dokter | Obat Ritel |
|---|---|---|
| Asal permintaan | Dokter via pemeriksaan | Langsung dari pembeli |
| Nomor RM | Wajib (pasien terdaftar) | Opsional |
| Proses registrasi | Via kunjungan + poli + dokter | Langsung di farmasi |
| Pemotongan stok | Saat konfirmasi apoteker | Saat obat diserahkan |
| Integrasi invoice | `billing` / Invoice kunjungan | `invoice_ritel` tersendiri |
| Racikan | Didukung | Tidak didukung (v1) |
| Penanggungjawab | Dokter + Apoteker | Apoteker saja |

---

## 4. Fitur & Spesifikasi Fungsional

### 4.1 Form Transaksi Ritel Baru
**Komponen:** `RitelForm` (Livewire)

**Section A — Identitas Pembeli:**
```
┌─────────────────────────────────────────────────────┐
│ Nama Pembeli *           │ Nomor HP                 │
│ [___________________]    │ [___________________]    │
├─────────────────────────────────────────────────────┤
│ Nomor RM (opsional)      │ Catatan                  │
│ [___________________]    │ [___________________]    │
│ Biarkan kosong jika tidak│                          │
│ punya RM klinik          │                          │
└─────────────────────────────────────────────────────┘
```

**Section B — Input Obat:**
```
┌─────────────────────────────────────────────────────────────────────┐
│ 🔍 Cari Obat...                                                     │
│ [Nama / Kode / Barcode___________________________] [Tambah]         │
├────────────────────────────────────────────────────────────────────-┤
│ #  Nama Obat        Stok    Harga Satuan   Jumlah   Subtotal  Hapus │
│ 1  Paracetamol 500  250 tab Rp 2.000/tab  [10]     Rp 20.000  [x]  │
│ 2  Antasida        100 tab Rp 3.500/tab  [5 ]     Rp 17.500  [x]  │
├─────────────────────────────────────────────────────────────────────┤
│                                    Total: Rp 37.500                 │
│                              [Simpan Draft] [Submit ke Kasir]       │
└─────────────────────────────────────────────────────────────────────┘
```

**Pencarian Obat:**
- Minimal 2 karakter → muncul dropdown
- Filter: `is_active = true`, `jenis IN ('obat', 'alkes')`, `stok > 0`
- Tampil: nama, kode, harga_jual, stok, satuan
- Obat `butuh_resep = true` → badge merah + warning tooltip
- Tambah ke cart → langsung update total

### 4.2 Daftar Transaksi Ritel
**Komponen:** `RitelTable` (Livewire)

- Filter: status, tanggal (dari–sampai), search (nama pembeli / nomor ritel)
- Kolom: Nomor Ritel, Nama Pembeli, Jumlah Item, Total, Status, Aksi
- Aksi per baris:
  - Draft: [Edit] [Batalkan]
  - Menunggu Kasir: [Lihat]
  - Dibayar: [Serahkan Obat]
  - Selesai: [Lihat Detail]
  - Dibatalkan: [Lihat]

### 4.3 Detail Transaksi Ritel
**Halaman:** `inventory/ritel/{id}` → `RitelDetail` (Livewire)

- Header: nomor ritel, tanggal, nama pembeli, status, total
- Tabel item: nama obat, jumlah, harga satuan, subtotal
- Timeline: draft → menunggu kasir → dibayar → selesai
- Tombol aksi sesuai status

### 4.4 Antrian Kasir — Ritel
- Kasir melihat daftar ritel `menunggu_kasir` di halaman kasir (tab baru atau section)
- Sama dengan alur kasir kunjungan biasa:
  - Pilih metode pembayaran
  - Input nominal
  - Konfirmasi → cetak struk

### 4.5 Antrian Penyerahan Obat
- Apoteker melihat daftar ritel `dibayar` di halaman farmasi
- Klik "Serahkan" → konfirmasi dialog
- Sistem potong stok + buat MutasiStok + status `selesai`

### 4.6 Struk / Receipt
- Digenerate saat status `dibayar`
- Konten: nomor ritel, nama pembeli, tanggal, daftar obat, total, metode bayar
- Bisa cetak ulang dari detail transaksi

---

## 5. Skema Database & Migration

### 5.1 Tabel `transaksi_ritel`

```php
Schema::create('transaksi_ritel', function (Blueprint $table) {
    $table->id();
    $table->string('nomor_ritel', 25)->unique();         // RIT-20260529-0001
    $table->string('nama_pembeli', 100);
    $table->string('nomor_hp', 20)->nullable();
    $table->foreignId('pasien_id')->nullable()            // link ke pasien jika ada RM
          ->constrained('pasien')->nullOnDelete();
    $table->foreignId('apoteker_id')                     // user yang input
          ->constrained('users')->onDelete('restrict');
    $table->foreignId('kasir_id')->nullable()             // user kasir yang proses bayar
          ->constrained('users')->nullOnDelete();
    $table->enum('status', [
        'draft',
        'menunggu_kasir',
        'dibayar',
        'selesai',
        'dibatalkan',
    ])->default('draft');
    $table->enum('metode_bayar', [
        'tunai', 'transfer', 'kartu', 'split',
    ])->nullable();
    $table->decimal('total_harga', 14, 2)->default(0);
    $table->decimal('total_bayar', 14, 2)->nullable();   // nominal yang dibayar kasir
    $table->decimal('kembalian', 14, 2)->nullable();
    $table->string('catatan')->nullable();
    $table->timestamp('dibayar_at')->nullable();
    $table->timestamp('diserahkan_at')->nullable();
    $table->timestamps();

    $table->index(['status', 'created_at']);
    $table->index('nama_pembeli');
});
```

### 5.2 Tabel `transaksi_ritel_item`

```php
Schema::create('transaksi_ritel_item', function (Blueprint $table) {
    $table->id();
    $table->foreignId('transaksi_ritel_id')
          ->constrained('transaksi_ritel')->onDelete('cascade');
    $table->foreignId('barang_id')
          ->constrained('barang')->onDelete('restrict');
    $table->unsignedSmallInteger('jumlah');
    $table->decimal('harga_satuan', 14, 2);              // snapshot harga saat transaksi
    $table->decimal('subtotal', 14, 2);
    $table->string('catatan')->nullable();
});
```

### 5.3 Alter `mutasi_stok` — tambah tipe `keluar_ritel`

```php
DB::statement("ALTER TABLE mutasi_stok MODIFY COLUMN tipe ENUM(
    'masuk_pembelian', 'keluar_resep', 'keluar_tindakan',
    'keluar_bhp', 'keluar_ritel',
    'penyesuaian_masuk', 'penyesuaian_keluar',
    'retur_ke_supplier', 'expired'
) NOT NULL");
```

---

## 6. Model Eloquent

### 6.1 `TransaksiRitel`

```php
class TransaksiRitel extends Model
{
    protected $table = 'transaksi_ritel';

    protected $fillable = [
        'nomor_ritel', 'nama_pembeli', 'nomor_hp', 'pasien_id',
        'apoteker_id', 'kasir_id', 'status', 'metode_bayar',
        'total_harga', 'total_bayar', 'kembalian', 'catatan',
        'dibayar_at', 'diserahkan_at',
    ];

    protected function casts(): array
    {
        return [
            'total_harga'  => 'decimal:2',
            'total_bayar'  => 'decimal:2',
            'kembalian'    => 'decimal:2',
            'dibayar_at'   => 'datetime',
            'diserahkan_at'=> 'datetime',
        ];
    }

    // Relations
    public function items()     { return $this->hasMany(TransaksiRitelItem::class); }
    public function apoteker()  { return $this->belongsTo(User::class, 'apoteker_id'); }
    public function kasir()     { return $this->belongsTo(User::class, 'kasir_id'); }
    public function pasien()    { return $this->belongsTo(Pasien::class); }

    // Helpers
    public function getStatusLabelAttribute(): string
    {
        return match($this->status) {
            'draft'          => 'Draft',
            'menunggu_kasir' => 'Menunggu Kasir',
            'dibayar'        => 'Dibayar',
            'selesai'        => 'Selesai',
            'dibatalkan'     => 'Dibatalkan',
            default          => $this->status,
        };
    }

    public static function generateNomor(): string
    {
        $prefix = 'RIT-' . now()->format('Ymd') . '-';
        $last   = static::where('nomor_ritel', 'like', $prefix . '%')
                        ->orderByDesc('nomor_ritel')->value('nomor_ritel');
        $seq    = $last ? (int) substr($last, -4) + 1 : 1;
        return $prefix . str_pad($seq, 4, '0', STR_PAD_LEFT);
    }
}
```

### 6.2 `TransaksiRitelItem`

```php
class TransaksiRitelItem extends Model
{
    protected $table = 'transaksi_ritel_item';
    public $timestamps = false;

    protected $fillable = [
        'transaksi_ritel_id', 'barang_id',
        'jumlah', 'harga_satuan', 'subtotal', 'catatan',
    ];

    protected function casts(): array
    {
        return [
            'harga_satuan' => 'decimal:2',
            'subtotal'     => 'decimal:2',
        ];
    }

    public function transaksiRitel() { return $this->belongsTo(TransaksiRitel::class); }
    public function barang()         { return $this->belongsTo(Barang::class); }
}
```

---

## 7. Service Layer

### `ObatRitelService`

```php
class ObatRitelService
{
    // Buat transaksi draft baru
    public function buatDraft(array $data): TransaksiRitel;

    // Update item di draft
    public function updateItem(TransaksiRitel $tr, array $items): TransaksiRitel;

    // Submit ke kasir → status: menunggu_kasir
    public function submitKeKasir(TransaksiRitel $tr): TransaksiRitel;

    // Kasir proses bayar → status: dibayar
    public function prosesBayar(TransaksiRitel $tr, array $bayarData): TransaksiRitel;

    // Apoteker serahkan obat → status: selesai + potong stok
    public function serahkanObat(TransaksiRitel $tr, int $userId): TransaksiRitel;

    // Batalkan (hanya saat draft atau menunggu_kasir)
    public function batalkan(TransaksiRitel $tr): TransaksiRitel;

    // Internal: potong stok + buat MutasiStok
    private function potongStok(TransaksiRitel $tr, int $userId): void;
}
```

**Business logic `serahkanObat()`:**
```
DB::transaction:
  foreach items as item:
    Barang::pastikanCukup(item.barang_id, item.jumlah)   ← cegah race condition
    barang.decrement('stok', item.jumlah)
    MutasiStok::create(tipe: 'keluar_ritel', referensi: transaksi_ritel)
  tr.update(status: 'selesai', diserahkan_at: now())
  activity log
```

---

## 8. Livewire Components

### 8.1 `RitelForm` — Buat / Edit transaksi ritel

| Property | Type | Keterangan |
|---|---|---|
| `$namaPembeli` | string | wajib |
| `$nomorHp` | string | opsional |
| `$pasienId` | ?int | jika linked ke RM |
| `$catatan` | string | opsional |
| `$searchObat` | string | input pencarian |
| `$items` | array | cart obat |
| `$transaksiId` | ?int | null = buat baru |

**Methods:**
- `updatedSearchObat()` — live search (debounce 400ms)
- `addItem(int $barangId)` — tambah ke cart
- `removeItem(int $idx)` — hapus dari cart
- `updatedItems()` — recalculate total
- `simpanDraft()` — simpan sebagai draft
- `submitKeKasir()` — kirim ke kasir
- `getTotalHargaProperty()` — computed total

**Computed `suggestionsObat`:**
```php
Barang::aktif()
    ->whereIn('jenis', ['obat', 'alkes'])
    ->where('stok', '>', 0)
    ->search($this->searchObat)
    ->limit(10)
    ->get(['id', 'kode', 'nama', 'harga_jual', 'satuan', 'stok', 'butuh_resep'])
```

### 8.2 `RitelTable` — Daftar transaksi ritel

| Filter | Default |
|---|---|
| `$filterStatus` | `''` (semua) |
| `$filterDari` | tanggal hari ini |
| `$filterSampai` | tanggal hari ini |
| `$search` | `''` |

**Methods:**
- `batalkan(int $id)` — batalkan draft/menunggu_kasir
- `serahkanObat(int $id)` — konfirmasi serah obat

### 8.3 `RitelDetail` — Detail satu transaksi

- Tampilkan header + item + timeline status
- Tombol kondisional per status
- `prosesBayar()` — form input pembayaran (untuk kasir)
- `serahkanObat()` — untuk apoteker

### 8.4 `RitelAntrianKasir` — Widget di halaman kasir

- Tampilkan list transaksi ritel `menunggu_kasir`
- Bisa inline di halaman kasir yang sudah ada (tab tambahan)

---

## 9. Route & Navigasi

```php
Route::prefix('farmasi/ritel')->name('farmasi.ritel.')->group(function () {

    Route::get('/', fn () => view('farmasi.ritel.index'))
         ->name('index')
         ->middleware('permission:obat.view');

    Route::get('/create', fn () => view('farmasi.ritel.create'))
         ->name('create')
         ->middleware('permission:obat.create');

    Route::get('/{id}', function ($id) {
        $tr = TransaksiRitel::with(['items.barang', 'apoteker', 'kasir', 'pasien'])
                            ->findOrFail($id);
        return view('farmasi.ritel.show', compact('tr'));
    })->name('show');

    Route::get('/{id}/edit', function ($id) {
        $tr = TransaksiRitel::findOrFail($id);
        abort_unless($tr->status === 'draft', 403);
        return view('farmasi.ritel.edit', compact('tr'));
    })->name('edit')
      ->middleware('permission:obat.create');
});
```

**Sidebar navigation** — di bawah menu Farmasi:
```blade
@can('obat.view')
<x-sidebar-item route="farmasi.ritel.index" icon="shopping-bag">
    Penjualan Ritel
</x-sidebar-item>
@endcan
```

---

## 10. Role & Hak Akses

| Permission | Deskripsi | Role Default |
|---|---|---|
| `obat.view` | Lihat daftar & detail ritel | apoteker, kasir, manajer |
| `obat.create` | Buat & edit transaksi ritel | apoteker |
| `obat.edit` | Proses bayar (kasir) | kasir |
| `obat.approve` | Serahkan obat & potong stok | apoteker |

> Menggunakan permission yang sudah ada — tidak perlu tambah permission baru.

**Matrix Aksi per Role:**

| Aksi | Apoteker | Kasir | Manajer | Dokter |
|---|:---:|:---:|:---:|:---:|
| Buat transaksi ritel | ✅ | ❌ | ❌ | ❌ |
| Input obat | ✅ | ❌ | ❌ | ❌ |
| Submit ke kasir | ✅ | ❌ | ❌ | ❌ |
| Proses pembayaran | ❌ | ✅ | ❌ | ❌ |
| Serahkan obat | ✅ | ❌ | ❌ | ❌ |
| Batalkan | ✅ | ✅ | ❌ | ❌ |
| Lihat laporan | ✅ | ✅ | ✅ | ❌ |

---

## 11. Business Rules

### BR-001: Identitas Pembeli
- Nama pembeli wajib diisi (minimal 3 karakter)
- Nomor RM opsional — jika diisi, harus valid di tabel `pasien`
- Jika tidak ada RM → transaksi tercatat sebagai "pembeli umum"
- Satu transaksi = satu pembeli (tidak ada multi-pasien)

### BR-002: Input Obat
- Hanya obat dengan `is_active = true` dan `stok > 0` yang bisa dipilih
- Jumlah minimal: 1, tidak boleh melebihi stok tersedia saat input
- Harga yang digunakan adalah `harga_jual` dari tabel `barang` **saat transaksi dibuat** (snapshot)
- Obat dengan `butuh_resep = true` → tampilkan warning, tapi tidak diblokir (apoteker yang bertanggung jawab secara klinis)
- Satu obat tidak bisa ditambah dua kali ke cart (merge jumlah jika duplikat)

### BR-003: Status Flow
```
draft → menunggu_kasir → dibayar → selesai
  │                          │
  └─────── dibatalkan ←──────┘ (hanya dari draft atau menunggu_kasir)
```
- Tidak ada rollback dari `dibayar` ke `menunggu_kasir`
- Tidak ada rollback dari `selesai`
- Pembatalan saat `dibayar` harus melalui proses refund manual (di luar scope v1)

### BR-004: Pemotongan Stok
- Stok dipotong **HANYA** saat status berubah ke `selesai` (saat obat diserahkan)
- Menggunakan `Barang::pastikanCukup()` dengan `lockForUpdate()` untuk cegah race condition
- `MutasiStok` dibuat dengan:
  - `tipe`: `keluar_ritel`
  - `referensi_tipe`: `transaksi_ritel`
  - `referensi_id`: id transaksi
  - `keterangan`: "Ritel: {nomor_ritel}"

### BR-005: Pembatalan
- Hanya bisa batalkan di status `draft` atau `menunggu_kasir`
- Jika sudah `dibayar` → tidak bisa batalkan dari sistem (butuh penanganan kasir manual)
- Tidak ada pengembalian stok karena stok belum dipotong

### BR-006: Nomor Transaksi
- Format: `RIT-YYYYMMDD-XXXX` (contoh: `RIT-20260529-0001`)
- Sequential per hari, reset tiap hari baru
- Generated otomatis oleh sistem, tidak bisa diubah manual

### BR-007: Pembayaran
- Memanfaatkan infrastruktur kasir yang sudah ada
- Total yang dibayar ≥ total harga transaksi (tidak ada piutang untuk ritel)
- Kembalian = total_bayar - total_harga (hanya untuk metode tunai)
- Split payment: bisa kombinasi tunai + transfer

---

## 12. User Stories

### US-001: Apoteker Buat Transaksi Baru
```
SEBAGAI apoteker
SAYA INGIN membuat transaksi ritel baru
AGAR pembeli bisa membeli obat tanpa harus ke dokter dulu

Acceptance Criteria:
✓ Bisa input nama pembeli tanpa nomor RM
✓ Bisa cari obat dengan nama/kode/barcode
✓ Jumlah yang bisa diinput dibatasi oleh stok tersedia
✓ Total harga terhitung otomatis
✓ Bisa simpan draft (jika perlu konfirmasi pembeli dulu)
✓ Bisa submit langsung ke kasir
```

### US-002: Apoteker Input Obat Bebas Terbatas
```
SEBAGAI apoteker
SAYA INGIN melihat peringatan ketika menambah obat yang butuh resep
AGAR saya bisa memastikan pembeli memiliki resep yang valid

Acceptance Criteria:
✓ Badge/label merah muncul di dropdown saat obat dipilih
✓ Dialog konfirmasi: "Obat ini memerlukan resep. Lanjutkan?"
✓ Jika dikonfirmasi → obat tetap bisa ditambah
✓ Warning tetap muncul di cart sebagai reminder
```

### US-003: Kasir Proses Pembayaran Ritel
```
SEBAGAI kasir
SAYA INGIN melihat antrian transaksi ritel yang sudah di-submit apoteker
AGAR saya bisa langsung proses pembayaran tanpa menunggu konfirmasi

Acceptance Criteria:
✓ Tab "Ritel" di halaman kasir menampilkan transaksi menunggu_kasir
✓ Bisa pilih metode bayar: tunai / transfer / kartu / split
✓ Sistem hitung kembalian otomatis untuk tunai
✓ Struk bisa dicetak / email setelah bayar
✓ Status otomatis berubah ke "dibayar" setelah konfirmasi
```

### US-004: Pembeli Tunggu Pengambilan Obat
```
SEBAGAI pembeli
SAYA INGIN tahu kapan obat saya siap diambil
AGAR saya bisa menunggu dengan tenang

Acceptance Criteria:
✓ Setelah bayar, nomor antrian tercetak di struk
✓ Apoteker ubah status ke "selesai" = obat siap
✓ (Opsional v2) Display antrian digital menampilkan nomor yang dipanggil
```

### US-005: Apoteker Serahkan Obat
```
SEBAGAI apoteker
SAYA INGIN melihat daftar transaksi ritel yang sudah dibayar
AGAR saya tahu obat mana yang harus disiapkan dan diserahkan

Acceptance Criteria:
✓ Daftar "Menunggu Penyerahan" tampil di halaman farmasi
✓ Tombol "Serahkan" memunculkan dialog konfirmasi
✓ Setelah konfirmasi → stok dipotong + status selesai
✓ Tidak bisa serahkan jika stok sudah habis (sistem blokir)
```

### US-006: Pembatalan Transaksi
```
SEBAGAI apoteker atau kasir
SAYA INGIN membatalkan transaksi yang belum dibayar
AGAR stok tidak terkunci dan antrian kasir bersih

Acceptance Criteria:
✓ Bisa batalkan dari status draft atau menunggu_kasir
✓ Tidak bisa batalkan dari status dibayar atau selesai
✓ Status berubah ke "dibatalkan" dengan catatan pembatalan
✓ Tidak ada perubahan stok (stok memang belum dipotong)
```

---

## 13. Seeder & Data Awal

Tidak diperlukan seeder tambahan. Modul menggunakan:
- `barang` — sudah ada data dari unifikasi obat
- `users` — sudah ada dari UserSeeder
- `RolePermissionSeeder` — pastikan permission `obat.view`, `obat.create`, `obat.edit`, `obat.approve` ada

---

## 14. Urutan Implementasi

### Fase 1 — Database & Model (Estimasi: 2 jam)
1. Migration `create_transaksi_ritel_table`
2. Migration `create_transaksi_ritel_item_table`
3. Migration `alter_mutasi_stok_add_keluar_ritel`
4. Model `TransaksiRitel` + `TransaksiRitelItem`
5. Update `MutasiStok::getTipeLabels()` untuk keluar_ritel

### Fase 2 — Service Layer (Estimasi: 2 jam)
6. `ObatRitelService::buatDraft()`
7. `ObatRitelService::submitKeKasir()`
8. `ObatRitelService::prosesBayar()`
9. `ObatRitelService::serahkanObat()` + potong stok + MutasiStok
10. `ObatRitelService::batalkan()`

### Fase 3 — Livewire & Views (Estimasi: 4 jam)
11. `RitelForm` + `ritel-form.blade.php` (buat/edit, cart, search obat)
12. `RitelTable` + `ritel-table.blade.php` (daftar + filter)
13. `RitelDetail` + `ritel-detail.blade.php` (detail + aksi per status)
14. Wrapper views: `farmasi/ritel/index.blade.php`, `create.blade.php`, `show.blade.php`

### Fase 4 — Integrasi Kasir & Navigation (Estimasi: 1 jam)
15. Update halaman kasir — tambah section/tab ritel `menunggu_kasir`
16. Update sidebar `app.blade.php` — tambah menu Penjualan Ritel
17. Update routes `web.php`

### Fase 5 — Testing & Polish (Estimasi: 1 jam)
18. Test flow end-to-end: buat → kasir → serahkan
19. Test pembatalan
20. Test validasi stok saat serahkan (kondisi stok habis di tengah jalan)
21. Clear cache, commit, push

---

## Appendix: Diagram Status Transaksi

```
         ┌───────────────────────────────────────────────────┐
         │                                                   │
    [Apoteker]           [Kasir]            [Apoteker]       │
         │                  │                    │           │
         ▼                  ▼                    ▼           │
     [DRAFT] ──────► [MENUNGGU_KASIR] ──► [DIBAYAR] ──► [SELESAI]
         │                  │
         │                  │
         ▼                  ▼
    [DIBATALKAN]      [DIBATALKAN]
    
    * Panah ke DIBATALKAN hanya dari DRAFT atau MENUNGGU_KASIR
    * DIBAYAR dan SELESAI tidak bisa dibatalkan
    * Stok dipotong HANYA saat → SELESAI
```
