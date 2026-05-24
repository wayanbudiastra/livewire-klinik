# Product Requirements Document (PRD)
# Modul Kasir — Update & Enhancement

| Info | Detail |
|:-----|:-------|
| **Versi** | 2.0.0 |
| **Tanggal** | Mei 2026 |
| **Status** | Draft |
| **Depends On** | `PRD_EMR_Laravel.md` · `modul_kasir.md` · `setup_pasien.md` |
| **Tech Stack** | Laravel 12 · Livewire 3 · MySQL · Tailwind CSS |
| **Scope** | Deposit Pasien · Split Payment · Cetak Invoice (Original/Copy) · Pembatalan Tagihan · Buka/Tutup Kas dengan Password SuperAdmin |

---

## Daftar Isi

1. [Ringkasan Eksekutif](#1-ringkasan-eksekutif)
2. [Perubahan dari Modul Kasir Sebelumnya](#2-perubahan-dari-modul-kasir-sebelumnya)
3. [Role & Hak Akses](#3-role--hak-akses)
4. [Skema Database & Migration](#4-skema-database--migration)
5. [Modul Deposit Pasien](#5-modul-deposit-pasien)
6. [Modul Split Payment](#6-modul-split-payment)
7. [Modul Cetak Invoice (Original & Copy)](#7-modul-cetak-invoice-original--copy)
8. [Modul Pembatalan Tagihan](#8-modul-pembatalan-tagihan)
9. [Modul Buka/Tutup Kas](#9-modul-bukatutup-kas)
10. [Model Eloquent](#10-model-eloquent)
11. [Repository Layer](#11-repository-layer)
12. [Service Layer](#12-service-layer)
13. [Form Request Validation](#13-form-request-validation)
14. [Livewire Components](#14-livewire-components)
15. [Route & Controller](#15-route--controller)
16. [Struktur Folder](#16-struktur-folder)
17. [User Stories & Business Rules](#17-user-stories--business-rules)
18. [Seeder Data Awal](#18-seeder-data-awal)

---

## 1. Ringkasan Eksekutif

Modul Kasir Update merupakan peningkatan signifikan dari modul kasir dasar pada `PRD_EMR_Laravel.md`. Enam fitur utama ditambahkan untuk memenuhi kebutuhan operasional kasir rumah sakit / klinik:

```
┌─────────────────────────────────────────────────────────────────┐
│                    ALUR KASIR (v2.0)                            │
│                                                                 │
│  Pasien Registrasi                                              │
│       │                                                         │
│       ├──► Deposit Saldo ──────────────────────────────────┐   │
│       │    (kapan saja sebelum/sesudah kunjungan)           │   │
│       │                                                     │   │
│       ▼                                                     │   │
│  Kunjungan Selesai → Generate Invoice                       │   │
│       │                                                     │   │
│       ▼                                                     │   │
│  Split Payment ◄────────────────── Gunakan Saldo Deposit ◄──┘   │
│   ├── Cash (tunai/non-tunai)                                │   │
│   ├── BPJS (cover sebagian)                                 │   │
│   ├── Asuransi Swasta (cover sebagian)                      │   │
│   └── Deposit Pasien (potong saldo)                         │   │
│       │                                                     │   │
│       ▼                                                     │   │
│  Invoice Lunas → Cetak (Original / Copy)                    │   │
│       │                                                     │   │
│       ▼                                                     │   │
│  Tutup Kas (End of Day)                                     │   │
│       │                                                     │   │
│       ├──► Buka Kas Kembali (Password SuperAdmin)           │   │
│       └──► Batalkan Tagihan (Password SuperAdmin)           │   │
└─────────────────────────────────────────────────────────────────┘
```

---

## 2. Perubahan dari Modul Kasir Sebelumnya

| Fitur | v1.0 (modul_kasir.md) | v2.0 (update ini) |
|-------|----------------------|-------------------|
| Deposit Pasien | ❌ Belum ada | ✅ Saldo deposit per pasien, bisa top-up kapan saja |
| Metode Pembayaran | Single metode per invoice | ✅ **Split Payment** — multi metode dalam 1 invoice |
| Cetak Invoice | Selalu sama | ✅ Cetak ke-1 = **ORIGINAL**, cetak berikutnya = **COPY** |
| Pembatalan Tagihan | Bisa bebas | ✅ Hanya bisa jika **kas belum tutup** + **password SuperAdmin** |
| Buka Kas | Sekali tutup = final | ✅ Bisa **buka kembali** dengan password SuperAdmin |
| Audit Trail | Minimal | ✅ Log detail setiap aksi sensitif (batal, buka kas) |

---

## 3. Role & Hak Akses

| Aksi | super_admin | admin | kasir | perawat | dokter |
|------|:-----------:|:-----:|:-----:|:-------:|:------:|
| Lihat daftar invoice | ✅ | ✅ | ✅ | ❌ | ❌ |
| Input deposit pasien | ✅ | ✅ | ✅ | ❌ | ❌ |
| Lihat saldo deposit pasien | ✅ | ✅ | ✅ | ❌ | ❌ |
| Proses split payment | ✅ | ✅ | ✅ | ❌ | ❌ |
| Cetak invoice | ✅ | ✅ | ✅ | ❌ | ❌ |
| Buka/Tutup kas | ✅ | ✅ | ✅ | ❌ | ❌ |
| Batalkan tagihan | ✅ (password) | ✅ (password) | ❌ | ❌ | ❌ |
| Buka kas sudah tutup | ✅ (password) | ❌ | ❌ | ❌ | ❌ |
| Lihat log audit kasir | ✅ | ✅ | ❌ | ❌ | ❌ |

> **Catatan:** Pembatalan tagihan & buka kas memerlukan verifikasi password SuperAdmin real-time (bukan sekadar role check).

---

## 4. Skema Database & Migration

### 4.1 Urutan Migration Baru

```
2026_01_01_000200_create_sesi_kas_table.php
2026_01_01_000201_create_deposit_pasien_table.php
2026_01_01_000202_create_transaksi_deposit_table.php
2026_01_01_000203_update_billing_table_add_columns.php
2026_01_01_000204_update_pembayaran_table_add_columns.php
2026_01_01_000205_create_pembayaran_split_table.php
2026_01_01_000206_create_cetak_invoice_log_table.php
2026_01_01_000207_create_audit_kasir_table.php
```

---

### 4.2 Tabel `sesi_kas`

```php
// database/migrations/2026_01_01_000200_create_sesi_kas_table.php

Schema::create('sesi_kas', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')                         // kasir yang membuka
          ->constrained('users')->onDelete('restrict');
    $table->date('tanggal');
    $table->dateTime('dibuka_pada');
    $table->decimal('saldo_awal', 16, 2)->default(0);   // kas awal dari setor kasir
    $table->dateTime('ditutup_pada')->nullable();
    $table->decimal('saldo_akhir', 16, 2)->nullable();  // kalkulasi saat tutup kas
    $table->decimal('total_cash', 16, 2)->nullable();
    $table->decimal('total_non_cash', 16, 2)->nullable();
    $table->decimal('total_deposit', 16, 2)->nullable();
    $table->decimal('total_bpjs', 16, 2)->nullable();
    $table->decimal('total_asuransi', 16, 2)->nullable();
    $table->decimal('total_pembatalan', 16, 2)->nullable()->default(0);

    // Buka kembali kas
    $table->enum('status', ['buka', 'tutup'])->default('buka');
    $table->foreignId('dibuka_kembali_oleh')->nullable()
          ->constrained('users')->nullOnDelete();        // superadmin yang buka kembali
    $table->dateTime('dibuka_kembali_pada')->nullable();
    $table->text('alasan_dibuka_kembali')->nullable();

    $table->foreignId('ditutup_oleh')->nullable()
          ->constrained('users')->nullOnDelete();
    $table->text('catatan')->nullable();
    $table->timestamps();

    $table->unique(['user_id', 'tanggal', 'dibuka_pada']); // boleh lebih dari 1 sesi per hari jika kasir beda
    $table->index(['tanggal', 'status']);
});
```

---

### 4.3 Tabel `deposit_pasien`

```php
// database/migrations/2026_01_01_000201_create_deposit_pasien_table.php
// Satu record per pasien — menyimpan saldo terkini

Schema::create('deposit_pasien', function (Blueprint $table) {
    $table->id();
    $table->foreignId('pasien_id')->unique()             // 1 pasien 1 rekening deposit
          ->constrained('pasien')->onDelete('restrict');
    $table->decimal('saldo', 16, 2)->default(0);        // saldo terkini
    $table->decimal('total_topup', 16, 2)->default(0);  // lifetime total top-up
    $table->decimal('total_terpakai', 16, 2)->default(0);
    $table->timestamps();
});
```

---

### 4.4 Tabel `transaksi_deposit`

```php
// database/migrations/2026_01_01_000202_create_transaksi_deposit_table.php

Schema::create('transaksi_deposit', function (Blueprint $table) {
    $table->id();
    $table->foreignId('pasien_id')
          ->constrained('pasien')->onDelete('restrict');
    $table->foreignId('sesi_kas_id')->nullable()
          ->constrained('sesi_kas')->nullOnDelete();
    $table->foreignId('user_id')                         // kasir yang memproses
          ->constrained('users')->onDelete('restrict');

    $table->string('nomor_transaksi', 30)->unique();     // DEP-2026-05-0001
    $table->enum('tipe', ['topup', 'pemakaian', 'refund', 'koreksi']);
    $table->decimal('jumlah', 16, 2);                   // selalu positif
    $table->decimal('saldo_sebelum', 16, 2);
    $table->decimal('saldo_sesudah', 16, 2);

    // Referensi pemakaian
    $table->string('referensi_tipe', 50)->nullable();   // billing, topup_manual
    $table->unsignedBigInteger('referensi_id')->nullable();

    $table->text('keterangan')->nullable();
    $table->timestamps();

    $table->index(['pasien_id', 'created_at']);
    $table->index(['referensi_tipe', 'referensi_id']);
});
```

---

### 4.5 Update Tabel `billing` (Tambah Kolom)

```php
// database/migrations/2026_01_01_000203_update_billing_table_add_columns.php

Schema::table('billing', function (Blueprint $table) {
    $table->foreignId('sesi_kas_id')->nullable()->after('nomor_invoice')
          ->constrained('sesi_kas')->nullOnDelete();
    $table->decimal('total_deposit_dipakai', 14, 2)->default(0)->after('total_bayar');
    $table->boolean('sudah_cetak')->default(false)->after('status');
    $table->unsignedSmallInteger('jumlah_cetak')->default(0)->after('sudah_cetak');
    $table->string('dibatalkan_alasan')->nullable()->after('status');
    $table->foreignId('dibatalkan_oleh')->nullable()
          ->after('dibatalkan_alasan')
          ->constrained('users')->nullOnDelete();
    $table->dateTime('dibatalkan_pada')->nullable()->after('dibatalkan_oleh');
});
```

---

### 4.6 Tabel `pembayaran_split`

```php
// database/migrations/2026_01_01_000205_create_pembayaran_split_table.php
// Setiap baris = 1 metode pembayaran dalam 1 invoice (split payment)

Schema::create('pembayaran_split', function (Blueprint $table) {
    $table->id();
    $table->foreignId('billing_id')
          ->constrained('billing')->onDelete('cascade');
    $table->foreignId('sesi_kas_id')->nullable()
          ->constrained('sesi_kas')->nullOnDelete();
    $table->foreignId('user_id')
          ->constrained('users')->onDelete('restrict');

    $table->enum('metode', [
        'tunai',          // bayar cash
        'debit',          // kartu debit / EDC
        'kredit',         // kartu kredit
        'transfer',       // transfer bank
        'qris',           // QRIS / e-wallet
        'bpjs',           // cover BPJS
        'asuransi',       // cover asuransi swasta
        'deposit',        // potong saldo deposit pasien
    ]);

    $table->decimal('jumlah', 14, 2);
    $table->string('referensi', 100)->nullable();       // nomor SEP, nomor klaim, no. rek tujuan

    // Untuk metode asuransi / BPJS
    $table->string('nama_asuransi', 100)->nullable();
    $table->string('nomor_polis', 50)->nullable();
    $table->decimal('jumlah_cover', 14, 2)->nullable(); // nilai yang di-cover
    $table->decimal('jumlah_pasien', 14, 2)->nullable();// sisa bayar pasien

    $table->dateTime('tanggal_bayar')->useCurrent();
    $table->timestamps();

    $table->index('billing_id');
});
```

---

### 4.7 Tabel `cetak_invoice_log`

```php
// database/migrations/2026_01_01_000206_create_cetak_invoice_log_table.php

Schema::create('cetak_invoice_log', function (Blueprint $table) {
    $table->id();
    $table->foreignId('billing_id')
          ->constrained('billing')->onDelete('cascade');
    $table->foreignId('user_id')
          ->constrained('users')->onDelete('restrict');

    $table->unsignedSmallInteger('nomor_cetak');        // 1 = original, 2+ = copy
    $table->enum('jenis', ['original', 'copy']);        // auto dari nomor_cetak
    $table->string('ip_address', 45)->nullable();
    $table->timestamps();

    $table->index('billing_id');
});
```

---

### 4.8 Tabel `audit_kasir`

```php
// database/migrations/2026_01_01_000207_create_audit_kasir_table.php

Schema::create('audit_kasir', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')                        // user yang melakukan aksi
          ->constrained('users')->onDelete('restrict');
    $table->foreignId('superadmin_id')->nullable()      // superadmin yang verifikasi (jika diperlukan)
          ->constrained('users')->nullOnDelete();

    $table->enum('aksi', [
        'buka_kas',
        'tutup_kas',
        'buka_kas_kembali',        // superadmin buka kas yang sudah tutup
        'batalkan_tagihan',        // superadmin batalkan billing
        'topup_deposit',
        'pakai_deposit',
        'refund_deposit',
        'proses_split_payment',
        'cetak_invoice',
    ]);

    $table->string('referensi_tipe', 50)->nullable();
    $table->unsignedBigInteger('referensi_id')->nullable();
    $table->json('detail')->nullable();                 // data sebelum & sesudah, alasan, dll
    $table->string('ip_address', 45)->nullable();
    $table->timestamps();

    $table->index(['aksi', 'created_at']);
    $table->index('user_id');
});
```

---

## 5. Modul Deposit Pasien

### 5.1 Konsep

Setiap pasien terdaftar dapat memiliki **rekening deposit** di klinik. Saldo dapat di-top-up kapan saja oleh kasir, dan otomatis terpotong saat digunakan sebagai salah satu metode pembayaran invoice.

```
Alur Deposit:
  Top-up deposit (kasir input) ──► saldo naik (+)
  Pemakaian di split payment   ──► saldo turun (-)
  Refund (jika billing batal)  ──► saldo naik (+)
```

### 5.2 Service — Deposit

```php
// app/Services/Kasir/DepositService.php

namespace App\Services\Kasir;

use App\Models\{Pasien, DepositPasien, TransaksiDeposit, SesiKas};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DepositService
{
    /**
     * Top-up saldo deposit pasien
     */
    public function topup(
        Pasien  $pasien,
        float   $jumlah,
        int     $userId,
        ?SesiKas $sesiKas = null,
        ?string $keterangan = null
    ): TransaksiDeposit {
        if ($jumlah <= 0) {
            throw new \InvalidArgumentException('Jumlah top-up harus lebih dari 0.');
        }

        return DB::transaction(function () use ($pasien, $jumlah, $userId, $sesiKas, $keterangan) {
            // Get or create rekening deposit pasien
            $deposit = DepositPasien::firstOrCreate(
                ['pasien_id' => $pasien->id],
                ['saldo' => 0, 'total_topup' => 0, 'total_terpakai' => 0]
            );

            $saldoSebelum = $deposit->saldo;
            $saldoSesudah = $saldoSebelum + $jumlah;

            $deposit->increment('saldo', $jumlah);
            $deposit->increment('total_topup', $jumlah);

            $trx = TransaksiDeposit::create([
                'pasien_id'      => $pasien->id,
                'sesi_kas_id'    => $sesiKas?->id,
                'user_id'        => $userId,
                'nomor_transaksi'=> $this->generateNomorTransaksi(),
                'tipe'           => 'topup',
                'jumlah'         => $jumlah,
                'saldo_sebelum'  => $saldoSebelum,
                'saldo_sesudah'  => $saldoSesudah,
                'referensi_tipe' => 'topup_manual',
                'keterangan'     => $keterangan,
            ]);

            // Audit log
            AuditKasirService::log('topup_deposit', $userId, 'transaksi_deposit', $trx->id, [
                'pasien_id'     => $pasien->id,
                'nama_pasien'   => $pasien->nama,
                'nomor_rm'      => $pasien->nomor_rm,
                'jumlah'        => $jumlah,
                'saldo_sesudah' => $saldoSesudah,
            ]);

            return $trx;
        });
    }

    /**
     * Pakai saldo deposit sebagai bagian dari split payment
     * Dipanggil dari BillingService::prosesSplitPayment()
     */
    public function pakai(
        Pasien $pasien,
        float  $jumlah,
        int    $billingId,
        int    $userId
    ): TransaksiDeposit {
        return DB::transaction(function () use ($pasien, $jumlah, $billingId, $userId) {
            $deposit = DepositPasien::where('pasien_id', $pasien->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($deposit->saldo < $jumlah) {
                throw new \RuntimeException(
                    "Saldo deposit tidak cukup. Saldo: Rp " . number_format($deposit->saldo, 0, ',', '.')
                );
            }

            $saldoSebelum = $deposit->saldo;
            $saldoSesudah = $saldoSebelum - $jumlah;

            $deposit->decrement('saldo', $jumlah);
            $deposit->increment('total_terpakai', $jumlah);

            return TransaksiDeposit::create([
                'pasien_id'      => $pasien->id,
                'user_id'        => $userId,
                'nomor_transaksi'=> $this->generateNomorTransaksi(),
                'tipe'           => 'pemakaian',
                'jumlah'         => $jumlah,
                'saldo_sebelum'  => $saldoSebelum,
                'saldo_sesudah'  => $saldoSesudah,
                'referensi_tipe' => 'billing',
                'referensi_id'   => $billingId,
                'keterangan'     => "Pembayaran invoice billing #{$billingId}",
            ]);
        });
    }

    /**
     * Refund deposit saat billing dibatalkan
     */
    public function refund(
        Pasien $pasien,
        float  $jumlah,
        int    $billingId,
        int    $userId
    ): TransaksiDeposit {
        return DB::transaction(function () use ($pasien, $jumlah, $billingId, $userId) {
            $deposit = DepositPasien::where('pasien_id', $pasien->id)
                ->lockForUpdate()
                ->firstOrFail();

            $saldoSebelum = $deposit->saldo;
            $saldoSesudah = $saldoSebelum + $jumlah;

            $deposit->increment('saldo', $jumlah);
            $deposit->decrement('total_terpakai', $jumlah);

            return TransaksiDeposit::create([
                'pasien_id'      => $pasien->id,
                'user_id'        => $userId,
                'nomor_transaksi'=> $this->generateNomorTransaksi(),
                'tipe'           => 'refund',
                'jumlah'         => $jumlah,
                'saldo_sebelum'  => $saldoSebelum,
                'saldo_sesudah'  => $saldoSesudah,
                'referensi_tipe' => 'billing',
                'referensi_id'   => $billingId,
                'keterangan'     => "Refund pembatalan invoice billing #{$billingId}",
            ]);
        });
    }

    private function generateNomorTransaksi(): string
    {
        $prefix = 'DEP-' . now()->format('Y-m-');
        $last   = TransaksiDeposit::where('nomor_transaksi', 'like', $prefix . '%')
                    ->orderByDesc('nomor_transaksi')
                    ->value('nomor_transaksi');
        $seq    = $last ? (int) substr($last, -4) + 1 : 1;
        return $prefix . str_pad($seq, 4, '0', STR_PAD_LEFT);
    }
}
```

### 5.3 Livewire — Form Top-up Deposit

```php
// app/Livewire/Kasir/Deposit/TopupDepositForm.php

namespace App\Livewire\Kasir\Deposit;

use Livewire\Component;
use App\Models\{Pasien, DepositPasien};
use App\Services\Kasir\{DepositService, SesiKasService};

class TopupDepositForm extends Component
{
    // Search pasien
    public string $searchPasien  = '';
    public array  $hasilSearch   = [];
    public ?int   $pasienId      = null;
    public ?array $pasienDipilih = null;
    public ?array $depositInfo   = null;

    // Form top-up
    public string $jumlah      = '';
    public string $keterangan  = '';

    protected function rules(): array
    {
        return [
            'pasienId'   => ['required', 'exists:pasien,id'],
            'jumlah'     => ['required', 'numeric', 'min:1000', 'max:100000000'],
            'keterangan' => ['nullable', 'string', 'max:200'],
        ];
    }

    protected $messages = [
        'jumlah.min' => 'Minimal top-up Rp 1.000',
    ];

    public function searchPasien(): void
    {
        if (strlen($this->searchPasien) < 2) {
            $this->hasilSearch = [];
            return;
        }

        $this->hasilSearch = Pasien::where('is_active', true)
            ->where(fn($q) => $q
                ->where('nama',     'like', "%{$this->searchPasien}%")
                ->orWhere('nomor_rm', 'like', "%{$this->searchPasien}%")
                ->orWhere('telepon',  'like', "%{$this->searchPasien}%")
            )
            ->with('depositPasien')
            ->limit(8)
            ->get()
            ->map(fn($p) => [
                'id'        => $p->id,
                'nomor_rm'  => $p->nomor_rm,
                'nama'      => $p->nama,
                'telepon'   => $p->telepon ?? '-',
                'saldo'     => $p->depositPasien?->saldo ?? 0,
            ])
            ->toArray();
    }

    public function pilihPasien(int $id): void
    {
        $pasien = Pasien::with('depositPasien')->findOrFail($id);
        $this->pasienId     = $pasien->id;
        $this->pasienDipilih = [
            'id'       => $pasien->id,
            'nama'     => $pasien->nama,
            'nomor_rm' => $pasien->nomor_rm,
        ];
        $this->depositInfo = [
            'saldo' => $pasien->depositPasien?->saldo ?? 0,
            'total_topup'    => $pasien->depositPasien?->total_topup ?? 0,
            'total_terpakai' => $pasien->depositPasien?->total_terpakai ?? 0,
        ];
        $this->searchPasien  = '';
        $this->hasilSearch   = [];
    }

    public function simpan(DepositService $depositService, SesiKasService $sesiKasService): void
    {
        $this->validate();

        $sesiKas = $sesiKasService->getSesiAktif(auth()->id());
        if (!$sesiKas) {
            $this->addError('jumlah', 'Kas belum dibuka. Buka kas terlebih dahulu.');
            return;
        }

        $pasien = Pasien::findOrFail($this->pasienId);

        try {
            $trx = $depositService->topup(
                pasien:      $pasien,
                jumlah:      (float) $this->jumlah,
                userId:      auth()->id(),
                sesiKas:     $sesiKas,
                keterangan:  $this->keterangan ?: null,
            );

            // Refresh deposit info
            $this->pilihPasien($this->pasienId);
            $this->jumlah     = '';
            $this->keterangan = '';

            session()->flash('success',
                "Top-up berhasil. Saldo baru: Rp " . number_format($this->depositInfo['saldo'], 0, ',', '.')
            );

        } catch (\Exception $e) {
            $this->addError('jumlah', $e->getMessage());
        }
    }

    public function render()
    {
        return view('livewire.kasir.deposit.topup-deposit-form');
    }
}
```

---

## 6. Modul Split Payment

### 6.1 Konsep

Satu invoice dapat dibayar dengan **beberapa metode sekaligus**. Contoh:

```
Invoice Total: Rp 500.000
  ├── BPJS cover     : Rp 250.000  (cover 50%)
  ├── Deposit Pasien : Rp 100.000  (potong saldo)
  └── Tunai          : Rp 150.000  (cash)
  ─────────────────────────────────
  Total Terbayar     : Rp 500.000  ✓ LUNAS
```

### 6.2 Aturan Split Payment

```
✓ Total semua split harus = total_tagihan
✓ Setiap metode ditambahkan satu per satu (bisa dihapus sebelum konfirmasi)
✓ Metode "deposit" hanya tersedia jika pasien punya saldo cukup
✓ Metode "bpjs" wajib isi nomor SEP
✓ Metode "asuransi" wajib isi nama asuransi & nomor polis
✓ Satu invoice boleh punya > 1 metode tunai (misal 2 kali bayar cash bertahap)
✓ Setelah konfirmasi → status billing = lunas, tidak bisa edit split
✓ Jika billing dibatalkan → refund semua deposit yang terpakai
```

### 6.3 Service — Split Payment

```php
// app/Services/Kasir/BillingService.php

namespace App\Services\Kasir;

use App\Models\{Billing, PembayaranSplit, Pasien, SesiKas};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class BillingService
{
    public function __construct(
        private DepositService    $depositService,
        private AuditKasirService $auditService,
    ) {}

    /**
     * Proses split payment — semua metode dalam 1 transaksi
     */
    public function prosesSplitPayment(
        Billing  $billing,
        array    $splitItems,   // array metode & jumlah
        int      $userId,
        SesiKas  $sesiKas
    ): Billing {
        if ($billing->status === 'lunas') {
            throw new \RuntimeException('Invoice ini sudah lunas.');
        }
        if ($billing->status === 'dibatalkan') {
            throw new \RuntimeException('Invoice ini sudah dibatalkan.');
        }

        // Validasi total split = total tagihan
        $totalSplit = collect($splitItems)->sum('jumlah');
        $sisaTagihan = $billing->total_tagihan - $billing->total_bayar;

        if (abs($totalSplit - $sisaTagihan) > 0.01) {
            throw new \InvalidArgumentException(
                "Total pembayaran (Rp " . number_format($totalSplit, 0, ',', '.') . ") " .
                "tidak sesuai sisa tagihan (Rp " . number_format($sisaTagihan, 0, ',', '.') . ")."
            );
        }

        return DB::transaction(function () use ($billing, $splitItems, $userId, $sesiKas) {
            $totalDeposit = 0;

            foreach ($splitItems as $item) {
                // Proses deposit terlebih dahulu (potong saldo)
                if ($item['metode'] === 'deposit') {
                    $pasien = Pasien::find($billing->kunjungan->pasien_id);
                    $this->depositService->pakai($pasien, $item['jumlah'], $billing->id, $userId);
                    $totalDeposit += $item['jumlah'];
                }

                PembayaranSplit::create([
                    'billing_id'     => $billing->id,
                    'sesi_kas_id'    => $sesiKas->id,
                    'user_id'        => $userId,
                    'metode'         => $item['metode'],
                    'jumlah'         => $item['jumlah'],
                    'referensi'      => $item['referensi'] ?? null,
                    'nama_asuransi'  => $item['nama_asuransi'] ?? null,
                    'nomor_polis'    => $item['nomor_polis'] ?? null,
                    'jumlah_cover'   => $item['jumlah_cover'] ?? null,
                    'jumlah_pasien'  => $item['jumlah_pasien'] ?? null,
                ]);
            }

            // Update billing
            $totalBayarBaru = $billing->total_bayar + $totalSplit;
            $billing->update([
                'total_bayar'           => $totalBayarBaru,
                'total_deposit_dipakai' => $billing->total_deposit_dipakai + $totalDeposit,
                'sisa'                  => 0,
                'status'                => 'lunas',
                'sesi_kas_id'           => $sesiKas->id,
            ]);

            $this->auditService->log('proses_split_payment', $userId, 'billing', $billing->id, [
                'nomor_invoice' => $billing->nomor_invoice,
                'total'         => $billing->total_tagihan,
                'split_count'   => count($splitItems),
                'methods'       => collect($splitItems)->pluck('metode')->unique()->values(),
            ]);

            return $billing->fresh(['pembayaranSplit']);
        });
    }

    /**
     * Batalkan billing — hanya jika kas masih buka + verifikasi password SuperAdmin
     */
    public function batalkanBilling(
        Billing $billing,
        string  $passwordSuperAdmin,
        string  $alasan,
        int     $requestUserId
    ): Billing {
        // Cek kas masih buka
        $sesiKas = SesiKas::where('status', 'buka')
            ->whereDate('tanggal', today())
            ->first();

        if (!$sesiKas) {
            throw new \RuntimeException('Kas sudah ditutup. Pembatalan tidak dapat dilakukan.');
        }

        if ($billing->status === 'dibatalkan') {
            throw new \RuntimeException('Invoice ini sudah dibatalkan.');
        }

        // Verifikasi password SuperAdmin
        $superAdmin = $this->verifySuperAdminPassword($passwordSuperAdmin);

        return DB::transaction(function () use ($billing, $alasan, $requestUserId, $superAdmin, $sesiKas) {
            // Refund deposit jika ada pemakaian deposit
            if ($billing->total_deposit_dipakai > 0) {
                $pasien = Pasien::find($billing->kunjungan->pasien_id);
                $this->depositService->refund(
                    $pasien,
                    $billing->total_deposit_dipakai,
                    $billing->id,
                    $requestUserId
                );
            }

            // Update total pembatalan di sesi kas
            $sesiKas->increment('total_pembatalan', $billing->total_bayar);

            $billing->update([
                'status'            => 'dibatalkan',
                'dibatalkan_alasan' => $alasan,
                'dibatalkan_oleh'   => $requestUserId,
                'dibatalkan_pada'   => now(),
            ]);

            $this->auditService->log('batalkan_tagihan', $requestUserId, 'billing', $billing->id, [
                'nomor_invoice'       => $billing->nomor_invoice,
                'total_tagihan'       => $billing->total_tagihan,
                'alasan'              => $alasan,
                'verifikasi_oleh'     => $superAdmin->nama,
                'sesi_kas_id'         => $sesiKas->id,
            ]);

            return $billing->fresh();
        });
    }

    /**
     * Verifikasi password SuperAdmin — kembalikan User jika valid
     */
    public function verifySuperAdminPassword(string $password): \App\Models\User
    {
        $superAdmin = \App\Models\User::role('super_admin')
            ->where('is_active', true)
            ->first();

        if (!$superAdmin || !Hash::check($password, $superAdmin->password)) {
            throw new \RuntimeException('Password SuperAdmin tidak valid.');
        }

        return $superAdmin;
    }
}
```

### 6.4 Livewire — Split Payment Form

```php
// app/Livewire/Kasir/Billing/SplitPaymentForm.php

namespace App\Livewire\Kasir\Billing;

use Livewire\Component;
use App\Models\{Billing, DepositPasien};
use App\Services\Kasir\{BillingService, SesiKasService};

class SplitPaymentForm extends Component
{
    public Billing $billing;
    public float   $sisaTagihan      = 0;
    public float   $totalSudahDiisi  = 0;
    public float   $saldoDeposit     = 0;

    // Array item split
    public array  $splitItems = [];

    // Form tambah 1 item
    public string $metode         = 'tunai';
    public string $jumlahInput    = '';
    public string $referensi      = '';
    public string $namaAsuransi   = '';
    public string $nomorPolis     = '';
    public string $jumlahCover    = '';

    // Daftar metode yang tersedia
    public array $metodeList = [
        'tunai'     => '💵 Tunai',
        'debit'     => '💳 Kartu Debit',
        'kredit'    => '💳 Kartu Kredit',
        'transfer'  => '🏦 Transfer Bank',
        'qris'      => '📱 QRIS / E-Wallet',
        'bpjs'      => '🏥 BPJS',
        'asuransi'  => '📋 Asuransi Swasta',
        'deposit'   => '🏦 Deposit Pasien',
    ];

    public function mount(Billing $billing): void
    {
        $this->billing      = $billing;
        $this->sisaTagihan  = $billing->total_tagihan - $billing->total_bayar;

        // Cek saldo deposit pasien
        $pasienId = $billing->kunjungan->pasien_id;
        $deposit  = DepositPasien::where('pasien_id', $pasienId)->first();
        $this->saldoDeposit = $deposit?->saldo ?? 0;
    }

    protected function rules(): array
    {
        $rules = [
            'metode'      => ['required', 'in:tunai,debit,kredit,transfer,qris,bpjs,asuransi,deposit'],
            'jumlahInput' => ['required', 'numeric', 'min:0.01'],
        ];

        if ($this->metode === 'bpjs') {
            $rules['referensi'] = ['required', 'string', 'max:100'];
        }
        if ($this->metode === 'asuransi') {
            $rules['namaAsuransi'] = ['required', 'string', 'max:100'];
            $rules['nomorPolis']   = ['required', 'string', 'max:50'];
            $rules['jumlahCover']  = ['required', 'numeric', 'min:0'];
        }

        return $rules;
    }

    public function tambahItem(): void
    {
        $this->validate();

        $jumlah = (float) $this->jumlahInput;

        // Cek saldo deposit jika metode deposit
        if ($this->metode === 'deposit') {
            $depositTerpakai = collect($this->splitItems)
                ->where('metode', 'deposit')
                ->sum('jumlah');

            if ($depositTerpakai + $jumlah > $this->saldoDeposit) {
                $this->addError('jumlahInput',
                    "Saldo deposit tidak cukup. Tersedia: Rp " .
                    number_format($this->saldoDeposit - $depositTerpakai, 0, ',', '.')
                );
                return;
            }
        }

        // Cek total tidak melebihi sisa tagihan
        if ($this->totalSudahDiisi + $jumlah > $this->sisaTagihan + 0.01) {
            $this->addError('jumlahInput',
                "Total melebihi sisa tagihan (Rp " .
                number_format($this->sisaTagihan, 0, ',', '.') . ")."
            );
            return;
        }

        $this->splitItems[] = [
            'metode'        => $this->metode,
            'label'         => $this->metodeList[$this->metode],
            'jumlah'        => $jumlah,
            'referensi'     => $this->referensi ?: null,
            'nama_asuransi' => $this->namaAsuransi ?: null,
            'nomor_polis'   => $this->nomorPolis ?: null,
            'jumlah_cover'  => $this->jumlahCover ? (float) $this->jumlahCover : null,
            'jumlah_pasien' => $this->metode === 'asuransi'
                ? ($jumlah - (float) $this->jumlahCover)
                : null,
        ];

        $this->totalSudahDiisi = collect($this->splitItems)->sum('jumlah');
        $this->resetItemForm();
    }

    public function hapusItem(int $index): void
    {
        array_splice($this->splitItems, $index, 1);
        $this->totalSudahDiisi = collect($this->splitItems)->sum('jumlah');
    }

    public function isiOtomatis(): void
    {
        // Isi jumlah otomatis = sisa yang belum diisi
        $sisa = $this->sisaTagihan - $this->totalSudahDiisi;
        $this->jumlahInput = (string) $sisa;
    }

    public function konfirmasi(BillingService $billingService, SesiKasService $sesiKasService): void
    {
        if (empty($this->splitItems)) {
            $this->addError('global', 'Tambahkan minimal 1 metode pembayaran.');
            return;
        }

        $totalSplit = collect($this->splitItems)->sum('jumlah');
        if (abs($totalSplit - $this->sisaTagihan) > 0.01) {
            $this->addError('global',
                "Total pembayaran belum sesuai sisa tagihan."
            );
            return;
        }

        $sesiKas = $sesiKasService->getSesiAktif(auth()->id());
        if (!$sesiKas) {
            $this->addError('global', 'Kas belum dibuka.');
            return;
        }

        try {
            $billingService->prosesSplitPayment(
                $this->billing,
                $this->splitItems,
                auth()->id(),
                $sesiKas
            );

            session()->flash('success', "Invoice {$this->billing->nomor_invoice} berhasil dilunasi.");
            $this->redirectRoute('kasir.billing.show', $this->billing);

        } catch (\Exception $e) {
            $this->addError('global', $e->getMessage());
        }
    }

    private function resetItemForm(): void
    {
        $this->metode      = 'tunai';
        $this->jumlahInput = '';
        $this->referensi   = '';
        $this->namaAsuransi= '';
        $this->nomorPolis  = '';
        $this->jumlahCover = '';
    }

    public function render()
    {
        return view('livewire.kasir.billing.split-payment-form');
    }
}
```

### 6.5 Blade View — Split Payment

```blade
{{-- resources/views/livewire/kasir/billing/split-payment-form.blade.php --}}
<div class="space-y-5">

    {{-- Error global --}}
    @if($errors->has('global'))
    <div class="alert-error animate-fade-in">
        <span>{{ $errors->first('global') }}</span>
    </div>
    @endif

    {{-- Ringkasan Invoice --}}
    <div class="card">
        <div class="card-body">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                <div>
                    <p class="text-gray-500">No. Invoice</p>
                    <p class="font-mono font-semibold">{{ $billing->nomor_invoice }}</p>
                </div>
                <div>
                    <p class="text-gray-500">Total Tagihan</p>
                    <p class="font-semibold text-gray-900">Rp {{ number_format($billing->total_tagihan, 0, ',', '.') }}</p>
                </div>
                <div>
                    <p class="text-gray-500">Sudah Dibayar</p>
                    <p class="font-semibold text-emerald-600">Rp {{ number_format($billing->total_bayar, 0, ',', '.') }}</p>
                </div>
                <div>
                    <p class="text-gray-500">Sisa Tagihan</p>
                    <p class="font-bold text-lg {{ $sisaTagihan > 0 ? 'text-red-600' : 'text-emerald-600' }}">
                        Rp {{ number_format($sisaTagihan, 0, ',', '.') }}
                    </p>
                </div>
            </div>

            {{-- Saldo Deposit --}}
            @if($saldoDeposit > 0)
            <div class="mt-3 flex items-center gap-2 text-sm bg-blue-50 px-4 py-2 rounded-lg">
                <svg class="w-4 h-4 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>
                </svg>
                <span class="text-blue-700">
                    Saldo Deposit Pasien tersedia:
                    <strong>Rp {{ number_format($saldoDeposit, 0, ',', '.') }}</strong>
                </span>
            </div>
            @endif
        </div>
    </div>

    {{-- Tambah Metode Pembayaran --}}
    <div class="card">
        <div class="card-header">
            <h3 class="text-sm font-semibold text-gray-700">Tambah Metode Pembayaran</h3>
            <button type="button" wire:click="isiOtomatis" class="btn-secondary btn-sm">
                Isi Otomatis
            </button>
        </div>
        <div class="card-body space-y-4">

            {{-- Pilih Metode --}}
            <div class="form-group">
                <label class="form-label">Metode <span class="text-red-500">*</span></label>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-2">
                    @foreach($metodeList as $val => $label)
                    @php
                        $disabled = $val === 'deposit' && $saldoDeposit <= 0;
                        $active   = $metode === $val;
                    @endphp
                    <button type="button"
                        wire:click="{{ !$disabled ? '$set(\'metode\', \'' . $val . '\')' : '' }}"
                        @disabled($disabled)
                        class="py-2 px-3 rounded-lg border text-xs font-medium transition-all text-center
                               {{ $active ? 'bg-primary-50 border-primary-500 text-primary-700 ring-1 ring-primary-500' : 'border-gray-200 hover:bg-gray-50' }}
                               {{ $disabled ? 'opacity-40 cursor-not-allowed' : 'cursor-pointer' }}">
                        {{ $label }}
                        @if($val === 'deposit')
                            <div class="text-xs text-gray-400 mt-0.5">
                                Saldo: {{ number_format($saldoDeposit, 0, ',', '.') }}
                            </div>
                        @endif
                    </button>
                    @endforeach
                </div>
            </div>

            {{-- Input Jumlah --}}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="form-group">
                    <label class="form-label">Jumlah (Rp) <span class="text-red-500">*</span></label>
                    <input type="number" wire:model="jumlahInput"
                        class="form-input @error('jumlahInput') border-red-400 @enderror"
                        placeholder="0" min="0" step="0.01" />
                    @error('jumlahInput') <p class="form-error">{{ $message }}</p> @enderror
                </div>

                {{-- BPJS: Nomor SEP --}}
                @if($metode === 'bpjs')
                <div class="form-group">
                    <label class="form-label">Nomor SEP <span class="text-red-500">*</span></label>
                    <input type="text" wire:model="referensi"
                        class="form-input @error('referensi') border-red-400 @enderror"
                        placeholder="Nomor SEP BPJS" />
                    @error('referensi') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                @endif

                {{-- Transfer/Debit/Kredit: Referensi --}}
                @if(in_array($metode, ['transfer','debit','kredit','qris']))
                <div class="form-group">
                    <label class="form-label">No. Referensi / Otorisasi</label>
                    <input type="text" wire:model="referensi" class="form-input"
                        placeholder="Opsional" />
                </div>
                @endif
            </div>

            {{-- Asuransi: Cover Detail --}}
            @if($metode === 'asuransi')
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="form-group">
                    <label class="form-label">Nama Asuransi <span class="text-red-500">*</span></label>
                    <input type="text" wire:model="namaAsuransi"
                        class="form-input @error('namaAsuransi') border-red-400 @enderror"
                        placeholder="Prudential, AXA, dll" />
                    @error('namaAsuransi') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                <div class="form-group">
                    <label class="form-label">Nomor Polis <span class="text-red-500">*</span></label>
                    <input type="text" wire:model="nomorPolis"
                        class="form-input @error('nomorPolis') border-red-400 @enderror" />
                    @error('nomorPolis') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                <div class="form-group">
                    <label class="form-label">Cover Asuransi (Rp) <span class="text-red-500">*</span></label>
                    <input type="number" wire:model="jumlahCover"
                        class="form-input @error('jumlahCover') border-red-400 @enderror"
                        placeholder="0" />
                    @error('jumlahCover') <p class="form-error">{{ $message }}</p> @enderror
                </div>
            </div>
            @endif

            <div>
                <button type="button" wire:click="tambahItem" class="btn-primary">
                    + Tambahkan
                </button>
            </div>
        </div>
    </div>

    {{-- Daftar Item Split --}}
    @if(!empty($splitItems))
    <div class="card">
        <div class="card-header">
            <h3 class="text-sm font-semibold text-gray-700">Rincian Pembayaran</h3>
        </div>
        <div class="card-body p-0">
            <table class="table">
                <thead>
                    <tr>
                        <th>Metode</th>
                        <th>Detail</th>
                        <th class="text-right">Jumlah (Rp)</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($splitItems as $i => $item)
                    <tr>
                        <td>
                            <span class="badge badge-primary">{{ $item['label'] }}</span>
                        </td>
                        <td class="text-sm text-gray-500">
                            @if($item['referensi']) <span>{{ $item['referensi'] }}</span> @endif
                            @if($item['nama_asuransi'])
                                {{ $item['nama_asuransi'] }} — Polis: {{ $item['nomor_polis'] }}
                                <br><span class="text-xs">Cover: Rp {{ number_format($item['jumlah_cover'], 0, ',', '.') }}</span>
                            @endif
                        </td>
                        <td class="text-right font-semibold text-gray-900">
                            {{ number_format($item['jumlah'], 0, ',', '.') }}
                        </td>
                        <td>
                            <button type="button" wire:click="hapusItem({{ $i }})"
                                class="text-red-400 hover:text-red-600">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </button>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="bg-gray-50 font-semibold">
                        <td colspan="2" class="px-4 py-3 text-right text-gray-700">Total Terbayar</td>
                        <td class="px-4 py-3 text-right text-lg {{ abs($totalSudahDiisi - $sisaTagihan) < 0.01 ? 'text-emerald-600' : 'text-red-600' }}">
                            Rp {{ number_format($totalSudahDiisi, 0, ',', '.') }}
                        </td>
                        <td></td>
                    </tr>
                    @if(abs($totalSudahDiisi - $sisaTagihan) >= 0.01)
                    <tr class="bg-red-50">
                        <td colspan="2" class="px-4 py-2 text-right text-sm text-red-600">Kurang</td>
                        <td class="px-4 py-2 text-right text-sm text-red-600 font-medium">
                            Rp {{ number_format($sisaTagihan - $totalSudahDiisi, 0, ',', '.') }}
                        </td>
                        <td></td>
                    </tr>
                    @endif
                </tfoot>
            </table>
        </div>
    </div>
    @endif

    {{-- Tombol Konfirmasi --}}
    <div class="flex justify-end gap-3">
        <a href="{{ route('kasir.billing.show', $billing) }}" class="btn-secondary">Batal</a>
        <button type="button" wire:click="konfirmasi" wire:loading.attr="disabled"
            class="btn-primary"
            @disabled(abs($totalSudahDiisi - $sisaTagihan) >= 0.01 || empty($splitItems))>
            <span wire:loading.remove>✓ Konfirmasi Pembayaran</span>
            <span wire:loading class="flex items-center gap-2">
                <div class="spinner w-4 h-4"></div> Memproses...
            </span>
        </button>
    </div>
</div>
```

---

## 7. Modul Cetak Invoice (Original & Copy)

### 7.1 Logika Original vs Copy

```
Cetak ke-1 → jenis = "ORIGINAL", sudah_cetak = true, jumlah_cetak = 1
Cetak ke-2+ → jenis = "COPY", jumlah_cetak += 1
```

Setiap aksi cetak dicatat di tabel `cetak_invoice_log`. DomPDF menambahkan watermark teks di header invoice.

### 7.2 Service — Cetak Invoice

```php
// app/Services/Kasir/CetakInvoiceService.php

namespace App\Services\Kasir;

use App\Models\{Billing, CetakInvoiceLog};
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;

class CetakInvoiceService
{
    public function cetak(Billing $billing, int $userId): \Illuminate\Http\Response
    {
        return DB::transaction(function () use ($billing, $userId) {
            // Hitung nomor cetak ini
            $nomorCetak = CetakInvoiceLog::where('billing_id', $billing->id)->count() + 1;
            $jenis      = $nomorCetak === 1 ? 'original' : 'copy';

            // Log cetak
            CetakInvoiceLog::create([
                'billing_id'  => $billing->id,
                'user_id'     => $userId,
                'nomor_cetak' => $nomorCetak,
                'jenis'       => $jenis,
                'ip_address'  => request()->ip(),
            ]);

            // Update flag di billing
            $billing->update([
                'sudah_cetak'   => true,
                'jumlah_cetak'  => $nomorCetak,
            ]);

            // Audit log
            AuditKasirService::log('cetak_invoice', $userId, 'billing', $billing->id, [
                'nomor_invoice' => $billing->nomor_invoice,
                'nomor_cetak'   => $nomorCetak,
                'jenis'         => $jenis,
            ]);

            // Generate PDF
            $pdf = Pdf::loadView('kasir.invoice.template', [
                'billing'     => $billing->load([
                    'kunjungan.pasien',
                    'kunjungan.dokter.user',
                    'kunjungan.poli',
                    'pembayaranSplit',
                ]),
                'jenis'       => strtoupper($jenis),   // 'ORIGINAL' atau 'COPY'
                'nomorCetak'  => $nomorCetak,
                'dicetak_oleh'=> auth()->user()->nama,
                'dicetak_pada'=> now()->format('d/m/Y H:i'),
            ])->setPaper('a5', 'portrait');

            $filename = "Invoice-{$billing->nomor_invoice}-{$jenis}.pdf";

            return $pdf->download($filename);
        });
    }
}
```

### 7.3 Template Invoice PDF

```blade
{{-- resources/views/kasir/invoice/template.blade.php --}}
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 11px; color: #111; }

        .header-bar {
            background: #1d4ed8;
            color: white;
            padding: 12px 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .klinik-nama { font-size: 14px; font-weight: bold; }
        .klinik-sub  { font-size: 10px; opacity: 0.85; }

        /* ── WATERMARK ORIGINAL / COPY ── */
        .stamp {
            position: absolute;
            top: 55px;
            right: 16px;
            font-size: 28px;
            font-weight: 900;
            letter-spacing: 3px;
            opacity: 0.15;
            transform: rotate(-30deg);
            pointer-events: none;
        }
        .stamp.original { color: #15803d; border: 4px solid #15803d; padding: 4px 8px; }
        .stamp.copy     { color: #b91c1c; border: 4px solid #b91c1c; padding: 4px 8px; }

        /* ── Header label invoice ── */
        .label-jenis {
            display: inline-block;
            padding: 2px 10px;
            border-radius: 4px;
            font-size: 10px;
            font-weight: bold;
            letter-spacing: 1px;
        }
        .label-original { background: #dcfce7; color: #15803d; border: 1px solid #86efac; }
        .label-copy     { background: #fee2e2; color: #b91c1c; border: 1px solid #fca5a5; }

        .section { padding: 10px 16px; }
        .divider  { border-top: 1px dashed #d1d5db; margin: 6px 0; }
        .row      { display: flex; justify-content: space-between; margin-bottom: 3px; }
        .label    { color: #6b7280; }
        .value    { font-weight: 600; }
        table     { width: 100%; border-collapse: collapse; }
        th, td    { padding: 5px 8px; text-align: left; }
        th        { background: #f3f4f6; font-weight: 600; font-size: 10px; color: #374151; }
        .total-row{ background: #eff6ff; font-weight: bold; }
        .footer   { padding: 8px 16px; font-size: 9px; color: #9ca3af; text-align: center; }
    </style>
</head>
<body>

{{-- Watermark --}}
<div class="stamp {{ strtolower($jenis) }}">{{ $jenis }}</div>

{{-- Header --}}
<div class="header-bar">
    <div>
        <div class="klinik-nama">{{ config('emr.klinik_nama', 'Klinik Sehat') }}</div>
        <div class="klinik-sub">{{ config('emr.klinik_alamat') }}</div>
    </div>
    <div style="text-align: right;">
        <div style="font-size: 16px; font-weight: bold;">INVOICE</div>
        <span class="label-jenis label-{{ strtolower($jenis) }}">{{ $jenis }}</span>
    </div>
</div>

{{-- Info Invoice --}}
<div class="section">
    <div class="row">
        <span class="label">No. Invoice</span>
        <span class="value" style="font-family: monospace;">{{ $billing->nomor_invoice }}</span>
    </div>
    <div class="row">
        <span class="label">Tanggal</span>
        <span class="value">{{ $billing->created_at->format('d/m/Y H:i') }}</span>
    </div>
    @if($nomorCetak > 1)
    <div class="row" style="color: #b91c1c;">
        <span>Cetakan ke-{{ $nomorCetak }} (COPY)</span>
        <span>{{ $dicetak_pada }}</span>
    </div>
    @endif
</div>

<div class="divider"></div>

{{-- Info Pasien --}}
<div class="section">
    <div class="row">
        <span class="label">Pasien</span>
        <span class="value">{{ $billing->kunjungan->pasien->nama }}</span>
    </div>
    <div class="row">
        <span class="label">No. RM</span>
        <span style="font-family: monospace;">{{ $billing->kunjungan->pasien->nomor_rm }}</span>
    </div>
    <div class="row">
        <span class="label">Poli / Dokter</span>
        <span>{{ $billing->kunjungan->poli?->nama }} — dr. {{ $billing->kunjungan->dokter?->user->nama }}</span>
    </div>
</div>

<div class="divider"></div>

{{-- Item Tagihan --}}
<div class="section" style="padding-bottom: 4px;">
    <table>
        <thead>
            <tr>
                <th>Item</th>
                <th style="text-align: right;">Qty</th>
                <th style="text-align: right;">Harga</th>
                <th style="text-align: right;">Total</th>
            </tr>
        </thead>
        <tbody>
            {{-- Item dari tindakan --}}
            @foreach($billing->kunjungan->tindakan ?? [] as $t)
            <tr>
                <td>{{ $t->masterTindakan->nama }}</td>
                <td style="text-align: right;">{{ $t->jumlah }}</td>
                <td style="text-align: right;">{{ number_format($t->masterTindakan->tarif, 0, ',', '.') }}</td>
                <td style="text-align: right;">{{ number_format($t->jumlah * $t->masterTindakan->tarif, 0, ',', '.') }}</td>
            </tr>
            @endforeach
            {{-- Item dari resep --}}
            @foreach($billing->kunjungan->resep->items ?? [] as $ri)
            <tr>
                <td>{{ $ri->obat->nama }} ({{ $ri->aturan_pakai }})</td>
                <td style="text-align: right;">{{ $ri->jumlah }}</td>
                <td style="text-align: right;">{{ number_format($ri->obat->harga_jual, 0, ',', '.') }}</td>
                <td style="text-align: right;">{{ number_format($ri->jumlah * $ri->obat->harga_jual, 0, ',', '.') }}</td>
            </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr class="total-row">
                <td colspan="3" style="text-align: right; padding: 8px;">Total Tagihan</td>
                <td style="text-align: right; padding: 8px;">
                    Rp {{ number_format($billing->total_tagihan, 0, ',', '.') }}
                </td>
            </tr>
        </tfoot>
    </table>
</div>

<div class="divider"></div>

{{-- Rincian Pembayaran (Split) --}}
<div class="section">
    <div style="font-weight: 600; margin-bottom: 6px; font-size: 11px;">Rincian Pembayaran</div>
    @foreach($billing->pembayaranSplit as $split)
    <div class="row">
        <span class="label" style="text-transform: capitalize;">
            {{ str_replace('_',' ', $split->metode) }}
            @if($split->nama_asuransi) ({{ $split->nama_asuransi }}) @endif
        </span>
        <span class="value">Rp {{ number_format($split->jumlah, 0, ',', '.') }}</span>
    </div>
    @endforeach
    <div class="divider"></div>
    <div class="row" style="font-weight: bold; font-size: 13px;">
        <span>TOTAL DIBAYAR</span>
        <span style="color: #15803d;">Rp {{ number_format($billing->total_bayar, 0, ',', '.') }}</span>
    </div>
</div>

{{-- Footer --}}
<div class="footer">
    <div>Dicetak oleh: {{ $dicetak_oleh }} — {{ $dicetak_pada }}</div>
    <div>Dokumen ini {{ $jenis === 'ORIGINAL' ? 'adalah dokumen asli' : 'adalah salinan — bukan dokumen asli' }}</div>
</div>

</body>
</html>
```

---

## 8. Modul Pembatalan Tagihan

### 8.1 Aturan Bisnis

```
KONDISI yang harus terpenuhi untuk membatalkan tagihan:
  ✓ Status billing: belum_bayar ATAU lunas (bukan dibatalkan)
  ✓ Sesi kas HARI INI masih berstatus "buka"
  ✓ Password SuperAdmin berhasil diverifikasi

EFEK pembatalan:
  ├── billing.status → "dibatalkan"
  ├── billing.dibatalkan_oleh  → user_id yang meminta
  ├── billing.dibatalkan_pada  → timestamp
  ├── Jika ada deposit terpakai → otomatis di-refund ke saldo pasien
  ├── sesi_kas.total_pembatalan += billing.total_bayar
  └── audit_kasir dicatat lengkap
```

### 8.2 Livewire — Modal Konfirmasi Batal

```php
// app/Livewire/Kasir/Billing/BatalkanBillingModal.php

namespace App\Livewire\Kasir\Billing;

use Livewire\Component;
use App\Models\Billing;
use App\Services\Kasir\BillingService;

class BatalkanBillingModal extends Component
{
    public bool   $show       = false;
    public ?int   $billingId  = null;
    public string $alasan     = '';
    public string $password   = '';
    public bool   $processing = false;
    public string $errorMsg   = '';

    protected $listeners = ['openBatalkanModal' => 'open'];

    public function open(int $billingId): void
    {
        $this->billingId = $billingId;
        $this->alasan    = '';
        $this->password  = '';
        $this->errorMsg  = '';
        $this->show      = true;
    }

    public function batalkan(BillingService $service): void
    {
        $this->validate([
            'alasan'   => ['required', 'string', 'min:10', 'max:500'],
            'password' => ['required', 'string'],
        ]);

        $this->processing = true;
        $this->errorMsg   = '';

        try {
            $billing = Billing::findOrFail($this->billingId);

            $service->batalkanBilling(
                billing:            $billing,
                passwordSuperAdmin: $this->password,
                alasan:             $this->alasan,
                requestUserId:      auth()->id(),
            );

            $this->show = false;
            $this->dispatch('billingDibatalkan');
            session()->flash('success', "Invoice {$billing->nomor_invoice} berhasil dibatalkan.");
            $this->redirectRoute('kasir.billing.index');

        } catch (\Exception $e) {
            $this->errorMsg   = $e->getMessage();
            $this->processing = false;
        }
    }

    public function render()
    {
        return view('livewire.kasir.billing.batalkan-billing-modal');
    }
}
```

```blade
{{-- resources/views/livewire/kasir/billing/batalkan-billing-modal.blade.php --}}
@if($show)
<div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 animate-fade-in"
     x-data @click.self="$wire.set('show', false)">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-md mx-4">

        <div class="modal-header bg-red-50 rounded-t-xl">
            <div class="flex items-center gap-2">
                <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
                <h3 class="modal-title text-red-700">Batalkan Tagihan</h3>
            </div>
            <button wire:click="$set('show', false)" class="text-gray-400 hover:text-gray-600">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        <div class="modal-body">

            {{-- Error --}}
            @if($errorMsg)
            <div class="alert-error animate-fade-in">
                <svg class="w-5 h-5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                </svg>
                <span>{{ $errorMsg }}</span>
            </div>
            @endif

            <div class="bg-amber-50 border border-amber-200 rounded-lg p-3 text-sm text-amber-800">
                <strong>Perhatian:</strong> Tindakan ini tidak dapat diurungkan.
                Saldo deposit yang terpakai akan dikembalikan secara otomatis.
            </div>

            {{-- Alasan --}}
            <div class="form-group">
                <label class="form-label">
                    Alasan Pembatalan <span class="text-red-500">*</span>
                </label>
                <textarea wire:model="alasan" rows="3"
                    class="form-textarea @error('alasan') border-red-400 @enderror"
                    placeholder="Jelaskan alasan pembatalan (minimal 10 karakter)"></textarea>
                @error('alasan') <p class="form-error">{{ $message }}</p> @enderror
            </div>

            {{-- Password SuperAdmin --}}
            <div class="form-group">
                <label class="form-label">
                    Password SuperAdmin <span class="text-red-500">*</span>
                </label>
                <div class="relative" x-data="{ show: false }">
                    <input :type="show ? 'text' : 'password'"
                        wire:model="password"
                        class="form-input pr-10 @error('password') border-red-400 @enderror"
                        placeholder="Masukkan password SuperAdmin" />
                    <button type="button" @click="show = !show"
                        class="absolute inset-y-0 right-3 flex items-center text-gray-400 hover:text-gray-600">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path x-show="!show" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M15 12a3 3 0 11-6 0 3 3 0 016 0z M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                            <path x-show="show" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>
                        </svg>
                    </button>
                </div>
                @error('password') <p class="form-error">{{ $message }}</p> @enderror
                <p class="form-hint">Diperlukan verifikasi password SuperAdmin untuk tindakan ini.</p>
            </div>
        </div>

        <div class="modal-footer">
            <button type="button" wire:click="$set('show', false)" class="btn-secondary">
                Batal
            </button>
            <button type="button" wire:click="batalkan" wire:loading.attr="disabled"
                class="btn-danger">
                <span wire:loading.remove wire:target="batalkan">
                    <svg class="w-4 h-4 mr-1 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                    </svg>
                    Ya, Batalkan
                </span>
                <span wire:loading wire:target="batalkan" class="flex items-center gap-2">
                    <div class="spinner w-4 h-4"></div> Memproses...
                </span>
            </button>
        </div>
    </div>
</div>
@endif
```

---

## 9. Modul Buka/Tutup Kas

### 9.1 Alur Sesi Kas

```
Buka Kas (kasir input saldo awal)
    │
    ▼
Transaksi Berjalan (pembayaran, deposit, dll)
    │
    ▼
Tutup Kas (kalkulasi otomatis: total per metode)
    │
    ├──► Kas Sudah Tutup ──────────────────────────────────
    │         │                                            │
    │         ├── Laporan kas bisa dicetak                │
    │         └── Tidak bisa batal tagihan baru           │
    │                                                     │
    │    Buka Kas Kembali (password SuperAdmin)           │
    │         │                                           │
    │         └── status = "buka" kembali                 │
    │             kasir bisa proses transaksi lagi        │
    └─────────────────────────────────────────────────────
```

### 9.2 Service — Sesi Kas

```php
// app/Services/Kasir/SesiKasService.php

namespace App\Services\Kasir;

use App\Models\{SesiKas, Billing, PembayaranSplit};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class SesiKasService
{
    /**
     * Buka sesi kas baru
     */
    public function bukaKas(int $userId, float $saldoAwal, ?string $catatan = null): SesiKas
    {
        // Cek apakah kasir sudah punya sesi aktif hari ini
        $existing = SesiKas::where('user_id', $userId)
            ->where('status', 'buka')
            ->whereDate('tanggal', today())
            ->first();

        if ($existing) {
            throw new \RuntimeException('Anda sudah memiliki sesi kas yang aktif hari ini.');
        }

        $sesi = SesiKas::create([
            'user_id'      => $userId,
            'tanggal'      => today(),
            'dibuka_pada'  => now(),
            'saldo_awal'   => $saldoAwal,
            'status'       => 'buka',
            'catatan'      => $catatan,
        ]);

        AuditKasirService::log('buka_kas', $userId, 'sesi_kas', $sesi->id, [
            'saldo_awal' => $saldoAwal,
        ]);

        return $sesi;
    }

    /**
     * Tutup sesi kas — kalkulasi otomatis rekap per metode
     */
    public function tutupKas(SesiKas $sesi, int $userId, ?string $catatan = null): SesiKas
    {
        if ($sesi->status === 'tutup') {
            throw new \RuntimeException('Sesi kas ini sudah ditutup.');
        }

        return DB::transaction(function () use ($sesi, $userId, $catatan) {
            // Rekap pembayaran dari sesi ini
            $rekap = PembayaranSplit::where('sesi_kas_id', $sesi->id)
                ->join('billing', 'pembayaran_split.billing_id', '=', 'billing.id')
                ->where('billing.status', 'lunas')
                ->selectRaw("
                    SUM(CASE WHEN metode = 'tunai'    THEN jumlah ELSE 0 END) as total_cash,
                    SUM(CASE WHEN metode IN ('debit','kredit','transfer','qris') THEN jumlah ELSE 0 END) as total_non_cash,
                    SUM(CASE WHEN metode = 'deposit'  THEN jumlah ELSE 0 END) as total_deposit,
                    SUM(CASE WHEN metode = 'bpjs'     THEN jumlah ELSE 0 END) as total_bpjs,
                    SUM(CASE WHEN metode = 'asuransi' THEN jumlah ELSE 0 END) as total_asuransi,
                    SUM(jumlah) as saldo_akhir
                ")
                ->first();

            $sesi->update([
                'status'          => 'tutup',
                'ditutup_pada'    => now(),
                'ditutup_oleh'    => $userId,
                'saldo_akhir'     => $rekap->saldo_akhir ?? 0,
                'total_cash'      => $rekap->total_cash ?? 0,
                'total_non_cash'  => $rekap->total_non_cash ?? 0,
                'total_deposit'   => $rekap->total_deposit ?? 0,
                'total_bpjs'      => $rekap->total_bpjs ?? 0,
                'total_asuransi'  => $rekap->total_asuransi ?? 0,
                'catatan'         => $catatan,
            ]);

            AuditKasirService::log('tutup_kas', $userId, 'sesi_kas', $sesi->id, [
                'saldo_akhir'    => $sesi->saldo_akhir,
                'total_cash'     => $sesi->total_cash,
                'total_non_cash' => $sesi->total_non_cash,
            ]);

            return $sesi->fresh();
        });
    }

    /**
     * Buka kembali kas yang sudah ditutup — wajib password SuperAdmin
     */
    public function bukaKasKembali(
        SesiKas $sesi,
        string  $passwordSuperAdmin,
        string  $alasan,
        int     $requestUserId
    ): SesiKas {
        if ($sesi->status !== 'tutup') {
            throw new \RuntimeException('Kas ini belum ditutup.');
        }

        // Verifikasi password SuperAdmin
        $superAdmin = $this->verifySuperAdminPassword($passwordSuperAdmin);

        $sesi->update([
            'status'                 => 'buka',
            'dibuka_kembali_oleh'    => $superAdmin->id,
            'dibuka_kembali_pada'    => now(),
            'alasan_dibuka_kembali'  => $alasan,
        ]);

        AuditKasirService::log('buka_kas_kembali', $requestUserId, 'sesi_kas', $sesi->id, [
            'alasan'              => $alasan,
            'superadmin_nama'     => $superAdmin->nama,
            'ditutup_pada'        => $sesi->ditutup_pada,
            'total_sebelum_buka'  => $sesi->saldo_akhir,
        ]);

        return $sesi->fresh();
    }

    public function getSesiAktif(int $userId): ?SesiKas
    {
        return SesiKas::where('user_id', $userId)
            ->where('status', 'buka')
            ->whereDate('tanggal', today())
            ->first();
    }

    private function verifySuperAdminPassword(string $password): \App\Models\User
    {
        $superAdmin = \App\Models\User::role('super_admin')
            ->where('is_active', true)
            ->first();

        if (!$superAdmin || !Hash::check($password, $superAdmin->password)) {
            throw new \RuntimeException('Password SuperAdmin tidak valid.');
        }

        return $superAdmin;
    }
}
```

### 9.3 Livewire — Panel Sesi Kas

```php
// app/Livewire/Kasir/SesiKas/SesiKasPanel.php

namespace App\Livewire\Kasir\SesiKas;

use Livewire\Component;
use App\Models\SesiKas;
use App\Services\Kasir\SesiKasService;

class SesiKasPanel extends Component
{
    public ?SesiKas $sesiAktif   = null;
    public bool     $showBuka    = false;
    public bool     $showTutup   = false;
    public bool     $showBukaKembali = false;

    // Form buka kas
    public string $saldoAwal  = '';
    public string $catatan    = '';

    // Form tutup kas
    public string $catatanTutup = '';

    // Form buka kas kembali (password superadmin)
    public string $passwordBukaKembali = '';
    public string $alasanBukaKembali   = '';
    public ?int   $sesiIdBukaKembali   = null;

    // Form batalkan tagihan (hanya trigger event ke modal)
    public string $passwordBatalkan = '';
    public string $errorMsg         = '';

    public function mount(): void
    {
        $this->sesiAktif = app(SesiKasService::class)->getSesiAktif(auth()->id());
    }

    // ── Buka Kas ──────────────────────────────────────
    public function bukaKas(SesiKasService $service): void
    {
        $this->validate([
            'saldoAwal' => ['required', 'numeric', 'min:0'],
        ]);

        try {
            $this->sesiAktif = $service->bukaKas(
                auth()->id(),
                (float) $this->saldoAwal,
                $this->catatan ?: null
            );
            $this->showBuka = false;
            $this->reset(['saldoAwal', 'catatan']);
            session()->flash('success', 'Kas berhasil dibuka.');
        } catch (\Exception $e) {
            $this->addError('saldoAwal', $e->getMessage());
        }
    }

    // ── Tutup Kas ─────────────────────────────────────
    public function tutupKas(SesiKasService $service): void
    {
        if (!$this->sesiAktif) return;

        try {
            $service->tutupKas(
                $this->sesiAktif,
                auth()->id(),
                $this->catatanTutup ?: null
            );
            $this->sesiAktif  = null;
            $this->showTutup  = false;
            session()->flash('success', 'Kas berhasil ditutup. Laporan kas tersimpan.');
        } catch (\Exception $e) {
            $this->addError('catatanTutup', $e->getMessage());
        }
    }

    // ── Buka Kas Kembali ──────────────────────────────
    public function bukaKasKembali(SesiKasService $service): void
    {
        $this->validate([
            'passwordBukaKembali' => ['required', 'string'],
            'alasanBukaKembali'   => ['required', 'string', 'min:10'],
            'sesiIdBukaKembali'   => ['required', 'exists:sesi_kas,id'],
        ]);

        try {
            $sesi = SesiKas::findOrFail($this->sesiIdBukaKembali);
            $service->bukaKasKembali(
                $sesi,
                $this->passwordBukaKembali,
                $this->alasanBukaKembali,
                auth()->id()
            );
            $this->sesiAktif           = $sesi->fresh();
            $this->showBukaKembali     = false;
            $this->errorMsg            = '';
            $this->passwordBukaKembali = '';
            $this->alasanBukaKembali   = '';
            session()->flash('success', 'Kas berhasil dibuka kembali.');
        } catch (\Exception $e) {
            $this->errorMsg = $e->getMessage();
        }
    }

    public function render()
    {
        // Ambil sesi tutup hari ini untuk tombol "Buka Kembali"
        $sesiTutupHariIni = SesiKas::where('status', 'tutup')
            ->whereDate('tanggal', today())
            ->with('user')
            ->latest()
            ->get();

        return view('livewire.kasir.sesi-kas.sesi-kas-panel', [
            'sesiTutupHariIni' => $sesiTutupHariIni,
        ]);
    }
}
```

### 9.4 Blade View — Panel Sesi Kas

```blade
{{-- resources/views/livewire/kasir/sesi-kas/sesi-kas-panel.blade.php --}}
<div>

    {{-- Status Kas --}}
    @if($sesiAktif)
    {{-- KAS BUKA --}}
    <div class="card border-l-4 border-emerald-500">
        <div class="card-body flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-3 h-3 rounded-full bg-emerald-500 animate-pulse"></div>
                <div>
                    <p class="font-semibold text-gray-900">Kas Sedang Buka</p>
                    <p class="text-xs text-gray-500">
                        Dibuka: {{ $sesiAktif->dibuka_pada->format('H:i') }} ·
                        Saldo awal: Rp {{ number_format($sesiAktif->saldo_awal, 0, ',', '.') }}
                    </p>
                </div>
            </div>
            <button type="button" wire:click="$set('showTutup', true)"
                class="btn-warning btn-sm">
                🔒 Tutup Kas
            </button>
        </div>
    </div>

    {{-- Modal Tutup Kas --}}
    @if($showTutup)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50"
         x-data @click.self="$wire.set('showTutup', false)">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-sm mx-4">
            <div class="modal-header">
                <h3 class="modal-title">Tutup Kas</h3>
                <button wire:click="$set('showTutup', false)" class="text-gray-400 hover:text-gray-600">✕</button>
            </div>
            <div class="modal-body">
                <div class="bg-amber-50 border border-amber-200 rounded-lg p-3 text-sm text-amber-800 mb-4">
                    Setelah kas ditutup, pembatalan tagihan tidak dapat dilakukan.
                </div>
                <div class="form-group">
                    <label class="form-label">Catatan (opsional)</label>
                    <textarea wire:model="catatanTutup" rows="2" class="form-textarea"
                        placeholder="Catatan penutupan kas"></textarea>
                    @error('catatanTutup') <p class="form-error">{{ $message }}</p> @enderror
                </div>
            </div>
            <div class="modal-footer">
                <button wire:click="$set('showTutup', false)" class="btn-secondary">Batal</button>
                <button wire:click="tutupKas" wire:loading.attr="disabled" class="btn-warning">
                    <span wire:loading.remove wire:target="tutupKas">🔒 Tutup Sekarang</span>
                    <span wire:loading wire:target="tutupKas" class="flex items-center gap-2">
                        <div class="spinner w-4 h-4"></div> Menutup...
                    </span>
                </button>
            </div>
        </div>
    </div>
    @endif

    @else
    {{-- KAS TUTUP / BELUM BUKA --}}
    <div class="card border-l-4 border-gray-300">
        <div class="card-body flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-3 h-3 rounded-full bg-gray-400"></div>
                <div>
                    <p class="font-semibold text-gray-700">Kas Belum Dibuka</p>
                    <p class="text-xs text-gray-400">{{ now()->format('d/m/Y') }}</p>
                </div>
            </div>
            <button type="button" wire:click="$set('showBuka', true)" class="btn-primary btn-sm">
                🔓 Buka Kas
            </button>
        </div>
    </div>

    {{-- Modal Buka Kas --}}
    @if($showBuka)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50"
         x-data @click.self="$wire.set('showBuka', false)">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-sm mx-4">
            <div class="modal-header">
                <h3 class="modal-title">Buka Kas — {{ now()->format('d/m/Y') }}</h3>
                <button wire:click="$set('showBuka', false)" class="text-gray-400 hover:text-gray-600">✕</button>
            </div>
            <div class="modal-body space-y-4">
                <div class="form-group">
                    <label class="form-label">Saldo Awal Kas (Rp) <span class="text-red-500">*</span></label>
                    <input type="number" wire:model="saldoAwal"
                        class="form-input @error('saldoAwal') border-red-400 @enderror"
                        placeholder="0" min="0" />
                    @error('saldoAwal') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                <div class="form-group">
                    <label class="form-label">Catatan</label>
                    <input type="text" wire:model="catatan" class="form-input" placeholder="Opsional" />
                </div>
            </div>
            <div class="modal-footer">
                <button wire:click="$set('showBuka', false)" class="btn-secondary">Batal</button>
                <button wire:click="bukaKas" wire:loading.attr="disabled" class="btn-primary">
                    <span wire:loading.remove>🔓 Buka Kas</span>
                    <span wire:loading class="flex items-center gap-2">
                        <div class="spinner w-4 h-4"></div>
                    </span>
                </button>
            </div>
        </div>
    </div>
    @endif

    {{-- Buka Kas Kembali (sesi hari ini yang sudah tutup) --}}
    @if($sesiTutupHariIni->count() > 0)
    @can('buka_kas_kembali')
    <div class="mt-3">
        <button type="button" wire:click="$set('showBukaKembali', true)"
            class="text-sm text-primary-600 hover:underline flex items-center gap-1">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
            </svg>
            Buka Kembali Kas (SuperAdmin)
        </button>
    </div>

    {{-- Modal Buka Kembali --}}
    @if($showBukaKembali)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50"
         x-data @click.self="$wire.set('showBukaKembali', false)">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-md mx-4">
            <div class="modal-header bg-red-50 rounded-t-xl">
                <h3 class="modal-title text-red-700">🔑 Buka Kembali Kas</h3>
                <button wire:click="$set('showBukaKembali', false)" class="text-gray-400">✕</button>
            </div>
            <div class="modal-body space-y-4">

                @if($errorMsg)
                <div class="alert-error">{{ $errorMsg }}</div>
                @endif

                <p class="text-sm text-gray-600">
                    Pilih sesi kas yang ingin dibuka kembali, masukkan alasan dan password SuperAdmin.
                </p>

                {{-- Pilih sesi --}}
                <div class="form-group">
                    <label class="form-label">Sesi Kas <span class="text-red-500">*</span></label>
                    <select wire:model="sesiIdBukaKembali" class="form-select">
                        <option value="">— Pilih Sesi —</option>
                        @foreach($sesiTutupHariIni as $s)
                        <option value="{{ $s->id }}">
                            {{ $s->user->nama }} — Tutup: {{ $s->ditutup_pada?->format('H:i') }}
                            (Saldo: Rp {{ number_format($s->saldo_akhir ?? 0, 0, ',', '.') }})
                        </option>
                        @endforeach
                    </select>
                    @error('sesiIdBukaKembali') <p class="form-error">{{ $message }}</p> @enderror
                </div>

                {{-- Alasan --}}
                <div class="form-group">
                    <label class="form-label">Alasan <span class="text-red-500">*</span></label>
                    <textarea wire:model="alasanBukaKembali" rows="2" class="form-textarea"
                        placeholder="Minimal 10 karakter"></textarea>
                    @error('alasanBukaKembali') <p class="form-error">{{ $message }}</p> @enderror
                </div>

                {{-- Password SuperAdmin --}}
                <div class="form-group" x-data="{ show: false }">
                    <label class="form-label">Password SuperAdmin <span class="text-red-500">*</span></label>
                    <div class="relative">
                        <input :type="show ? 'text' : 'password'"
                            wire:model="passwordBukaKembali"
                            class="form-input pr-10 @error('passwordBukaKembali') border-red-400 @enderror"
                            placeholder="Password SuperAdmin" />
                        <button type="button" @click="show = !show"
                            class="absolute inset-y-0 right-3 flex items-center text-gray-400">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M15 12a3 3 0 11-6 0 3 3 0 016 0z M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                            </svg>
                        </button>
                    </div>
                    @error('passwordBukaKembali') <p class="form-error">{{ $message }}</p> @enderror
                </div>
            </div>
            <div class="modal-footer">
                <button wire:click="$set('showBukaKembali', false)" class="btn-secondary">Batal</button>
                <button wire:click="bukaKasKembali" wire:loading.attr="disabled" class="btn-danger">
                    <span wire:loading.remove>🔑 Buka Kas Kembali</span>
                    <span wire:loading class="flex items-center gap-2">
                        <div class="spinner w-4 h-4"></div>
                    </span>
                </button>
            </div>
        </div>
    </div>
    @endif
    @endcan
    @endif

    @endif
</div>
```

---

## 10. Model Eloquent

```php
// app/Models/SesiKas.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

class SesiKas extends Model
{
    protected $table    = 'sesi_kas';
    protected $fillable = [
        'user_id','tanggal','dibuka_pada','saldo_awal',
        'ditutup_pada','saldo_akhir',
        'total_cash','total_non_cash','total_deposit','total_bpjs','total_asuransi','total_pembatalan',
        'status','ditutup_oleh',
        'dibuka_kembali_oleh','dibuka_kembali_pada','alasan_dibuka_kembali',
        'catatan',
    ];
    protected $casts = [
        'tanggal'             => 'date',
        'dibuka_pada'         => 'datetime',
        'ditutup_pada'        => 'datetime',
        'dibuka_kembali_pada' => 'datetime',
    ];

    public function user():             BelongsTo { return $this->belongsTo(User::class); }
    public function ditutupOleh():      BelongsTo { return $this->belongsTo(User::class, 'ditutup_oleh'); }
    public function dibukaKembaliOleh():BelongsTo { return $this->belongsTo(User::class, 'dibuka_kembali_oleh'); }
    public function billings():         HasMany   { return $this->hasMany(Billing::class); }

    public function scopeBuka($q)  { return $q->where('status', 'buka'); }
    public function scopeTutup($q) { return $q->where('status', 'tutup'); }
}
```

```php
// app/Models/DepositPasien.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

class DepositPasien extends Model
{
    protected $table    = 'deposit_pasien';
    protected $fillable = ['pasien_id','saldo','total_topup','total_terpakai'];
    protected $casts    = ['saldo' => 'decimal:2'];

    public function pasien():    BelongsTo { return $this->belongsTo(Pasien::class); }
    public function transaksi(): HasMany   { return $this->hasMany(TransaksiDeposit::class, 'pasien_id', 'pasien_id'); }
}
```

```php
// app/Models/PembayaranSplit.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PembayaranSplit extends Model
{
    protected $table    = 'pembayaran_split';
    protected $fillable = [
        'billing_id','sesi_kas_id','user_id','metode','jumlah',
        'referensi','nama_asuransi','nomor_polis','jumlah_cover','jumlah_pasien','tanggal_bayar',
    ];
    protected $casts = ['tanggal_bayar' => 'datetime'];

    public function billing(): BelongsTo { return $this->belongsTo(Billing::class); }
    public function user():    BelongsTo { return $this->belongsTo(User::class); }
    public function sesiKas(): BelongsTo { return $this->belongsTo(SesiKas::class); }

    public function getMetodeLabelAttribute(): string
    {
        return match($this->metode) {
            'tunai'    => '💵 Tunai',
            'debit'    => '💳 Kartu Debit',
            'kredit'   => '💳 Kartu Kredit',
            'transfer' => '🏦 Transfer Bank',
            'qris'     => '📱 QRIS',
            'bpjs'     => '🏥 BPJS',
            'asuransi' => '📋 Asuransi',
            'deposit'  => '🏦 Deposit',
            default    => ucfirst($this->metode),
        };
    }
}
```

---

## 11. Repository Layer

```php
// app/Repositories/Kasir/BillingRepository.php

namespace App\Repositories\Kasir;

use App\Models\Billing;
use Illuminate\Pagination\LengthAwarePaginator;

class BillingRepository
{
    public function findAll(array $params = []): LengthAwarePaginator
    {
        return Billing::with(['kunjungan.pasien', 'sesiKas', 'pembayaranSplit'])
            ->when($params['search'] ?? null, fn($q, $s) => $q
                ->where('nomor_invoice', 'like', "%$s%")
                ->orWhereHas('kunjungan.pasien', fn($q2) => $q2
                    ->where('nama', 'like', "%$s%")
                    ->orWhere('nomor_rm', 'like', "%$s%")
                )
            )
            ->when($params['status'] ?? null, fn($q, $v) => $q->where('status', $v))
            ->when($params['tanggal'] ?? null, fn($q, $v) => $q->whereDate('created_at', $v))
            ->when($params['sesi_kas_id'] ?? null, fn($q, $v) => $q->where('sesi_kas_id', $v))
            ->latest()
            ->paginate($params['per_page'] ?? 15);
    }

    public function findByCetakStatus(int $billingId): ?Billing
    {
        return Billing::with('cetakLogs')->find($billingId);
    }
}
```

---

## 12. Service Layer

```php
// app/Services/Kasir/AuditKasirService.php

namespace App\Services\Kasir;

use App\Models\AuditKasir;

class AuditKasirService
{
    public static function log(
        string  $aksi,
        int     $userId,
        ?string $referensiTipe = null,
        ?int    $referensiId   = null,
        array   $detail        = [],
        ?int    $superAdminId  = null
    ): void {
        AuditKasir::create([
            'user_id'        => $userId,
            'superadmin_id'  => $superAdminId,
            'aksi'           => $aksi,
            'referensi_tipe' => $referensiTipe,
            'referensi_id'   => $referensiId,
            'detail'         => $detail,
            'ip_address'     => request()->ip(),
        ]);
    }
}
```

---

## 13. Form Request Validation

```php
// app/Http/Requests/Kasir/TopupDepositRequest.php

namespace App\Http\Requests\Kasir;

use Illuminate\Foundation\Http\FormRequest;

class TopupDepositRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()->can('deposit.create'); }

    public function rules(): array
    {
        return [
            'pasien_id'  => ['required', 'exists:pasien,id'],
            'jumlah'     => ['required', 'numeric', 'min:1000', 'max:100000000'],
            'keterangan' => ['nullable', 'string', 'max:200'],
        ];
    }
}
```

```php
// app/Http/Requests/Kasir/SplitPaymentRequest.php

namespace App\Http\Requests\Kasir;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SplitPaymentRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()->can('billing.edit'); }

    public function rules(): array
    {
        return [
            'items'                  => ['required', 'array', 'min:1'],
            'items.*.metode'         => ['required', Rule::in([
                'tunai','debit','kredit','transfer','qris','bpjs','asuransi','deposit'])],
            'items.*.jumlah'         => ['required', 'numeric', 'min:0.01'],
            'items.*.referensi'      => ['nullable', 'string', 'max:100'],
            'items.*.nama_asuransi'  => ['required_if:items.*.metode,asuransi', 'nullable', 'string'],
            'items.*.nomor_polis'    => ['required_if:items.*.metode,asuransi', 'nullable', 'string'],
            'items.*.jumlah_cover'   => ['required_if:items.*.metode,asuransi', 'nullable', 'numeric'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            foreach ($this->items ?? [] as $i => $item) {
                if (($item['metode'] ?? '') === 'bpjs' && empty($item['referensi'])) {
                    $v->errors()->add("items.{$i}.referensi", 'Nomor SEP BPJS wajib diisi.');
                }
            }
        });
    }
}
```

```php
// app/Http/Requests/Kasir/BatalkanBillingRequest.php

namespace App\Http\Requests\Kasir;

use Illuminate\Foundation\Http\FormRequest;

class BatalkanBillingRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Hanya super_admin & admin yang bisa request pembatalan
        return $this->user()->hasAnyRole(['super_admin', 'admin']);
    }

    public function rules(): array
    {
        return [
            'alasan'             => ['required', 'string', 'min:10', 'max:500'],
            'password_superadmin'=> ['required', 'string'],
        ];
    }
}
```

---

## 14. Livewire Components — Daftar Lengkap

```
app/Livewire/Kasir/
├── SesiKas/
│   └── SesiKasPanel.php            # Buka/Tutup/Buka Kembali Kas
│
├── Deposit/
│   ├── TopupDepositForm.php        # Search pasien + input top-up
│   └── RiwayatDepositTable.php     # Riwayat transaksi deposit per pasien
│
├── Billing/
│   ├── BillingTable.php            # Daftar invoice + filter status + sesi
│   ├── BillingDetail.php           # Detail invoice + tombol cetak + batal
│   ├── SplitPaymentForm.php        # Form split payment multi-metode
│   └── BatalkanBillingModal.php    # Modal batal + input alasan + password
│
└── Laporan/
    └── RekapSesiKas.php            # Rekap per sesi: total per metode
```

---

## 15. Route & Controller

```php
// routes/web.php — tambahkan group kasir

Route::middleware(['auth'])->prefix('kasir')->name('kasir.')->group(function () {

    // Sesi Kas
    Route::middleware('permission:kas.manage')->group(function () {
        Route::get('/sesi-kas',           [SesiKasController::class, 'index'])->name('sesi-kas.index');
        Route::post('/sesi-kas/buka',     [SesiKasController::class, 'buka'])->name('sesi-kas.buka');
        Route::patch('/sesi-kas/{sesi}/tutup',        [SesiKasController::class, 'tutup'])->name('sesi-kas.tutup');
        Route::patch('/sesi-kas/{sesi}/buka-kembali', [SesiKasController::class, 'bukaKembali'])
             ->name('sesi-kas.buka-kembali')
             ->middleware('role:super_admin');
    });

    // Deposit
    Route::middleware('permission:deposit.view')->group(function () {
        Route::get('/deposit',            [DepositController::class, 'index'])->name('deposit.index');
        Route::get('/deposit/topup',      [DepositController::class, 'topup'])->name('deposit.topup')
             ->middleware('permission:deposit.create');
        Route::get('/deposit/pasien/{pasien}', [DepositController::class, 'riwayat'])->name('deposit.riwayat');
    });

    // Billing
    Route::middleware('permission:billing.view')->group(function () {
        Route::get('/billing',            [BillingController::class, 'index'])->name('billing.index');
        Route::get('/billing/{billing}',  [BillingController::class, 'show'])->name('billing.show');
        Route::post('/billing/{billing}/split-payment', [BillingController::class, 'splitPayment'])
             ->name('billing.split-payment')
             ->middleware('permission:billing.edit');
        Route::get('/billing/{billing}/cetak', [BillingController::class, 'cetak'])->name('billing.cetak')
             ->middleware('permission:billing.cetak');
        Route::patch('/billing/{billing}/batalkan', [BillingController::class, 'batalkan'])
             ->name('billing.batalkan')
             ->middleware('role:super_admin|admin');
    });

    // Laporan Kas
    Route::get('/laporan-sesi/{sesi}', [SesiKasController::class, 'laporan'])
         ->name('laporan-sesi')
         ->middleware('permission:kas.manage');
});
```

---

## 16. Struktur Folder

```
app/
├── Http/
│   ├── Controllers/
│   │   └── Kasir/
│   │       ├── SesiKasController.php
│   │       ├── DepositController.php
│   │       └── BillingController.php
│   └── Requests/
│       └── Kasir/
│           ├── BukaKasRequest.php
│           ├── TutupKasRequest.php
│           ├── BukaKasKembaliRequest.php
│           ├── TopupDepositRequest.php
│           ├── SplitPaymentRequest.php
│           └── BatalkanBillingRequest.php
│
├── Livewire/Kasir/          # (lihat Section 14)
│
├── Models/
│   ├── SesiKas.php
│   ├── DepositPasien.php
│   ├── TransaksiDeposit.php
│   ├── PembayaranSplit.php
│   ├── CetakInvoiceLog.php
│   └── AuditKasir.php
│
├── Repositories/
│   └── Kasir/
│       ├── SesiKasRepository.php
│       ├── DepositRepository.php
│       └── BillingRepository.php
│
└── Services/
    └── Kasir/
        ├── SesiKasService.php          # buka/tutup/buka-kembali kas
        ├── DepositService.php          # topup/pakai/refund deposit
        ├── BillingService.php          # split payment + batalkan
        ├── CetakInvoiceService.php     # cetak original/copy + log
        └── AuditKasirService.php       # static log helper

database/
├── migrations/
│   ├── 2026_01_01_000200_create_sesi_kas_table.php
│   ├── 2026_01_01_000201_create_deposit_pasien_table.php
│   ├── 2026_01_01_000202_create_transaksi_deposit_table.php
│   ├── 2026_01_01_000203_update_billing_table_add_columns.php
│   ├── 2026_01_01_000204_update_pembayaran_table_add_columns.php
│   ├── 2026_01_01_000205_create_pembayaran_split_table.php
│   ├── 2026_01_01_000206_create_cetak_invoice_log_table.php
│   └── 2026_01_01_000207_create_audit_kasir_table.php

resources/views/
├── kasir/
│   ├── invoice/
│   │   └── template.blade.php      # Template PDF DomPDF
│   └── laporan/
│       └── rekap-sesi.blade.php
└── livewire/kasir/                 # Blade views Livewire
```

---

## 17. User Stories & Business Rules

| ID | Persona | Skenario | Expected Behavior |
|----|---------|----------|-------------------|
| US01 | Kasir | Buka kas dengan saldo awal Rp 500.000 | Sesi kas berstatus buka, kasir bisa mulai transaksi |
| US02 | Kasir | Top-up deposit pasien Rp 200.000 | Saldo deposit naik, `transaksi_deposit` tercatat tipe `topup` |
| US03 | Kasir | Bayar invoice Rp 500.000 split: BPJS Rp 300.000 + Tunai Rp 200.000 | 2 record di `pembayaran_split`, status billing = lunas |
| US04 | Kasir | Bayar invoice menggunakan deposit + tunai | Saldo deposit terpotong otomatis, refund jika dibatalkan |
| US05 | Kasir | Total split < total tagihan saat konfirmasi | Error: "Total belum sesuai sisa tagihan" |
| US06 | Kasir | Cetak invoice pertama kali | PDF watermark "ORIGINAL", `jumlah_cetak = 1` |
| US07 | Kasir | Cetak ulang invoice yang sama | PDF watermark "COPY", `jumlah_cetak = 2` |
| US08 | Admin | Batalkan tagihan saat kas masih buka | Modal muncul → input alasan + password superadmin → billing dibatalkan + deposit di-refund |
| US09 | Kasir | Batalkan tagihan setelah kas tutup | Error: "Kas sudah ditutup. Pembatalan tidak dapat dilakukan." |
| US10 | Admin | Input password superadmin salah saat batalkan | Error: "Password SuperAdmin tidak valid." |
| US11 | SuperAdmin | Tutup kas lalu buka kembali | Modal buka kembali → input alasan + password → sesi status = buka, audit log tercatat |
| US12 | Kasir | Tutup kas | Rekap otomatis per metode tersimpan, laporan kas bisa dicetak |
| US13 | SuperAdmin | Lihat audit log kasir | Semua aksi sensitif tercatat: buka/tutup kas, batal tagihan, buka kembali |
| US14 | Kasir | Coba input deposit pasien tanpa buka kas | Error: "Kas belum dibuka" |
| US15 | Kasir | Input metode BPJS tanpa nomor SEP | Validasi error: "Nomor SEP wajib diisi" |

---

## 18. Seeder Data Awal

```php
// database/seeders/KasirSeeder.php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\{Pasien, DepositPasien};

class KasirSeeder extends Seeder
{
    public function run(): void
    {
        // Buat rekening deposit untuk pasien seed
        $pasienList = Pasien::whereIn('nomor_rm', ['RM-000001','RM-000002','RM-000003'])->get();

        $deposits = [
            'RM-000001' => 500000,   // Budi Santoso — saldo Rp 500.000
            'RM-000002' => 1000000,  // John Smith — saldo Rp 1.000.000
            'RM-000003' => 0,        // Ni Luh Ayu — belum ada deposit
        ];

        foreach ($pasienList as $pasien) {
            $saldo = $deposits[$pasien->nomor_rm] ?? 0;

            DepositPasien::updateOrCreate(
                ['pasien_id' => $pasien->id],
                [
                    'saldo'          => $saldo,
                    'total_topup'    => $saldo,
                    'total_terpakai' => 0,
                ]
            );

            $this->command->info("✓ Deposit {$pasien->nama}: Rp " . number_format($saldo, 0, ',', '.'));
        }
    }
}
```

```bash
# Perintah migrasi & seed
php artisan migrate
php artisan db:seed --class=KasirSeeder
```

---

*PRD_Modul_Kasir_Update.md v2.0.0*  
*Konsisten dengan PRD_EMR_Laravel.md · setup_pasien.md · PRD_Manajemen_Inventory.md*  
*(Laravel 12 · Livewire 3 · MySQL · Tailwind CSS · DomPDF)*