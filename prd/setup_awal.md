# Product Requirements Document (PRD)
# Sistem Rekam Medis Elektronik (RME / EMR)

**Versi:** 2.0.0  
**Tanggal:** Mei 2026  
**Status:** Draft  
**Author:** Tim Pengembang  
**Tech Stack:** Laravel 12, Livewire 3, MySQL

---

## Daftar Isi

1. [Ringkasan Eksekutif](#1-ringkasan-eksekutif)
2. [Tujuan & Sasaran Produk](#2-tujuan--sasaran-produk)
3. [Target Pengguna & Role](#3-target-pengguna--role)
4. [Tech Stack](#4-tech-stack)
5. [Arsitektur Sistem](#5-arsitektur-sistem)
6. [Struktur Folder Proyek](#6-struktur-folder-proyek)
7. [Skema Database (Migration Laravel)](#7-skema-database-migration-laravel)
8. [Fitur & Modul Utama](#8-fitur--modul-utama)
9. [Hak Akses per Role (RBAC)](#9-hak-akses-per-role-rbac)
10. [Route & Controller](#10-route--controller)
11. [Autentikasi & Keamanan](#11-autentikasi--keamanan)
12. [UI/UX Guidelines](#12-uiux-guidelines)
13. [Setup & Konfigurasi Awal](#13-setup--konfigurasi-awal)
14. [Environment Variables](#14-environment-variables)
15. [Roadmap Pengembangan](#15-roadmap-pengembangan)

---

## 1. Ringkasan Eksekutif

Sistem Rekam Medis Elektronik (RME) ini adalah aplikasi fullstack berbasis web yang dirancang untuk mendigitalisasi proses pencatatan medis di fasilitas kesehatan. Aplikasi dibangun dengan pendekatan modular dan scalable menggunakan **Laravel 12** sebagai backend framework, **Livewire 3** untuk interaktivitas UI tanpa JavaScript terpisah, dan **MySQL** sebagai database relasional. Sistem mendukung multi-role user dengan kontrol akses granular (RBAC) menggunakan Laravel Gates & Policies, sehingga setiap peran hanya dapat mengakses data dan fungsi yang relevan.

---

## 2. Tujuan & Sasaran Produk

### Tujuan Utama
- Menggantikan pencatatan rekam medis manual dengan sistem digital yang efisien
- Meningkatkan akurasi dan keamanan data pasien
- Mempercepat alur kerja klinis dari pendaftaran hingga pembuatan laporan

### Sasaran Teknis
- Aplikasi fullstack dengan Laravel 12 (MVC Pattern)
- Database relasional MySQL dengan Laravel Eloquent ORM & Migrations
- Autentikasi aman dengan Laravel Breeze / Fortify + Spatie Permission (RBAC)
- UI interaktif real-time dengan Livewire 3 tanpa SPA overhead
- Struktur kode modular berbasis Service & Repository Pattern
- Siap dikembangkan menjadi sistem enterprise multi-klinik

### KPI Keberhasilan
- Waktu pendaftaran pasien < 2 menit
- Response time halaman < 500ms (p95)
- Uptime sistem ≥ 99.5%
- Zero data breach pada data pasien

---

## 3. Target Pengguna & Role

| Role | Deskripsi | Akses Utama |
|------|-----------|-------------|
| **Super Admin** | Administrator sistem tertinggi | Full access semua modul + manajemen user & sistem |
| **Admin** | Pengelola operasional harian | Manajemen pasien, jadwal, laporan |
| **Dokter** | Tenaga medis pemeriksa | SOAP note, diagnosis, resep, riwayat pasien |
| **Perawat** | Tenaga keperawatan | Asesmen awal, tanda vital, tindakan keperawatan |
| **Apoteker** | Pengelola farmasi | Validasi & dispensing resep |
| **Kasir** | Pengelola keuangan | Billing, pembayaran, invoice |
| **Rekam Medis** | Staff rekam medis | Kelola & arsip rekam medis, laporan |
| **Pasien** | Pasien terdaftar | Riwayat kunjungan pribadi (portal pasien) |

---

## 4. Tech Stack

### Backend
```
Laravel 12
PHP 8.3+
Eloquent ORM
Laravel Sanctum (API auth, jika diperlukan)
Laravel Queues (notifikasi async)
Laravel Events & Listeners
Spatie Laravel Permission (RBAC)
Spatie Laravel Activity Log (Audit Trail)
```

### Frontend / UI
```
Livewire 3 (full-stack reactive components)
Alpine.js (interaktivitas JS ringan, sudah bundled dengan Livewire)
Tailwind CSS 3.4+
  - @tailwindcss/forms       (reset & styling form elements)
  - @tailwindcss/typography  (styling konten teks/artikel)
  - tailwind-scrollbar       (custom scrollbar)
Flowbite 2.x (komponen UI berbasis Tailwind: modal, dropdown, tooltip, dll)
Blade Templates (templating engine Laravel)
Vite 5 (asset bundler)
```

### Database
```
MySQL 8.0+
Laravel Migrations (versi skema)
Laravel Seeders & Factories (data dummy)
```

### Autentikasi
```
Laravel Breeze (scaffolding auth)
Spatie Laravel Permission (role & permission management)
bcrypt (password hashing, default Laravel)
```

### Tooling & Utilities
```
Laravel Telescope (debugging & monitoring, dev only)
Laravel Pint (code style)
Pest / PHPUnit (testing)
Maatwebsite/Laravel-Excel (export Excel)
Barryvdh/Laravel-DomPDF (export PDF)
```

---

## 5. Arsitektur Sistem

```
┌─────────────────────────────────────────────┐
│              CLIENT BROWSER                 │
│  Blade Templates + Livewire Components      │
│  + Alpine.js + Tailwind CSS                 │
└────────────────────┬────────────────────────┘
                     │ HTTPS (WebSocket via Livewire)
┌────────────────────▼────────────────────────┐
│           LARAVEL 12 SERVER                 │
│  ┌─────────────┐  ┌──────────────────────┐ │
│  │ Middleware   │  │  Routes (web.php)    │ │
│  │ (Auth+RBAC) │  │  Controllers         │ │
│  └─────────────┘  └──────────────────────┘ │
│  ┌──────────────────────────────────────┐  │
│  │      Livewire Components             │  │
│  │      (Reactive UI + Business Logic)  │  │
│  └──────────────────────────────────────┘  │
│  ┌──────────────────────────────────────┐  │
│  │      Service Layer                   │  │
│  │      (Business Logic)                │  │
│  └──────────────────────────────────────┘  │
│  ┌──────────────────────────────────────┐  │
│  │      Repository Layer                │  │
│  │      (Data Access / Eloquent)        │  │
│  └──────────────────────────────────────┘  │
└────────────────────┬────────────────────────┘
                     │ PDO / MySQLi
┌────────────────────▼────────────────────────┐
│              MySQL 8.0+                     │
│         Relational Database                 │
└─────────────────────────────────────────────┘
```

### Pola Arsitektur
- **MVC Pattern** — Model, View (Blade), Controller
- **Repository Pattern** — abstraksi query Eloquent dari business logic
- **Service Layer** — business logic terpusat, terpisah dari Controller
- **Livewire Components** — UI reaktif tanpa API terpisah
- **Gates & Policies** — validasi akses berbasis role via Spatie Permission

---

## 6. Struktur Folder Proyek

```
emr-laravel/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Auth/                    # Auth controllers (Breeze)
│   │   │   ├── DashboardController.php
│   │   │   ├── PasienController.php
│   │   │   ├── KunjunganController.php
│   │   │   ├── PemeriksaanController.php
│   │   │   ├── RawatInapController.php
│   │   │   ├── FarmasiController.php
│   │   │   ├── BillingController.php
│   │   │   ├── LaporanController.php
│   │   │   └── PengaturanController.php
│   │   │
│   │   ├── Middleware/
│   │   │   ├── CheckRole.php            # Middleware cek role
│   │   │   └── CheckPermission.php      # Middleware cek permission
│   │   │
│   │   └── Requests/                   # Form Request Validation
│   │       ├── StorePasienRequest.php
│   │       ├── StoreKunjunganRequest.php
│   │       ├── StoreSOAPRequest.php
│   │       ├── StoreResepRequest.php
│   │       └── StoreBillingRequest.php
│   │
│   ├── Livewire/                        # Livewire Components
│   │   ├── Dashboard/
│   │   │   ├── StatistikHarian.php
│   │   │   └── AntreanRealtime.php
│   │   ├── Pasien/
│   │   │   ├── PasienTable.php          # Tabel pasien dengan search & filter
│   │   │   ├── PasienForm.php           # Form create/edit pasien
│   │   │   └── RiwayatKunjungan.php
│   │   ├── Kunjungan/
│   │   │   ├── PendaftaranForm.php      # Form pendaftaran kunjungan
│   │   │   ├── AntreanDisplay.php       # Live antrian
│   │   │   └── StatusKunjungan.php
│   │   ├── Pemeriksaan/
│   │   │   ├── AsesmenPerawatForm.php
│   │   │   └── SoapNoteForm.php
│   │   ├── Farmasi/
│   │   │   ├── ResepTable.php
│   │   │   ├── ResepForm.php
│   │   │   └── StokObatTable.php
│   │   ├── Billing/
│   │   │   ├── BillingForm.php
│   │   │   └── PembayaranForm.php
│   │   ├── Laporan/
│   │   │   └── LaporanFilter.php
│   │   └── Pengaturan/
│   │       ├── UserManagement.php
│   │       └── MasterData.php
│   │
│   ├── Models/
│   │   ├── User.php
│   │   ├── Klinik.php
│   │   ├── Poli.php
│   │   ├── Dokter.php
│   │   ├── Perawat.php
│   │   ├── Pasien.php
│   │   ├── Kunjungan.php
│   │   ├── AsesmenPerawat.php
│   │   ├── SOAPNote.php
│   │   ├── RawatInap.php
│   │   ├── Kamar.php
│   │   ├── Obat.php
│   │   ├── Resep.php
│   │   ├── ItemResep.php
│   │   ├── MasterTindakan.php
│   │   ├── Tindakan.php
│   │   ├── Billing.php
│   │   └── Pembayaran.php
│   │
│   ├── Repositories/                    # Repository Pattern
│   │   ├── Contracts/
│   │   │   ├── PasienRepositoryInterface.php
│   │   │   └── KunjunganRepositoryInterface.php
│   │   ├── PasienRepository.php
│   │   ├── KunjunganRepository.php
│   │   ├── DiagnosaRepository.php
│   │   ├── ResepRepository.php
│   │   ├── BillingRepository.php
│   │   └── UserRepository.php
│   │
│   ├── Services/                        # Business Logic Layer
│   │   ├── PasienService.php
│   │   ├── KunjunganService.php
│   │   ├── PemeriksaanService.php
│   │   ├── FarmasiService.php
│   │   ├── BillingService.php
│   │   └── LaporanService.php
│   │
│   ├── Policies/                        # Laravel Policies (RBAC)
│   │   ├── PasienPolicy.php
│   │   ├── KunjunganPolicy.php
│   │   ├── SOAPNotePolicy.php
│   │   ├── ResepPolicy.php
│   │   └── BillingPolicy.php
│   │
│   └── Providers/
│       ├── AppServiceProvider.php
│       └── AuthServiceProvider.php      # Register Policies & Gates
│
├── database/
│   ├── migrations/                      # Semua file migrasi
│   ├── seeders/
│   │   ├── DatabaseSeeder.php
│   │   ├── RolePermissionSeeder.php     # Seed roles & permissions Spatie
│   │   ├── UserSeeder.php
│   │   ├── KlinikSeeder.php
│   │   ├── PoliSeeder.php
│   │   └── ObatSeeder.php
│   └── factories/
│       ├── UserFactory.php
│       └── PasienFactory.php
│
├── resources/
│   ├── views/
│   │   ├── layouts/
│   │   │   ├── app.blade.php            # Layout utama (sidebar + navbar)
│   │   │   ├── guest.blade.php          # Layout halaman auth
│   │   │   └── print.blade.php          # Layout untuk cetak
│   │   ├── auth/
│   │   │   ├── login.blade.php
│   │   │   └── forgot-password.blade.php
│   │   ├── dashboard/
│   │   │   └── index.blade.php
│   │   ├── pasien/
│   │   │   ├── index.blade.php          # Daftar pasien
│   │   │   ├── show.blade.php           # Detail pasien
│   │   │   └── create.blade.php
│   │   ├── kunjungan/
│   │   │   ├── index.blade.php
│   │   │   └── pendaftaran.blade.php
│   │   ├── pemeriksaan/
│   │   │   ├── index.blade.php
│   │   │   └── show.blade.php
│   │   ├── rawat-inap/
│   │   │   └── index.blade.php
│   │   ├── farmasi/
│   │   │   ├── resep/
│   │   │   │   └── index.blade.php
│   │   │   └── stok-obat/
│   │   │       └── index.blade.php
│   │   ├── billing/
│   │   │   └── index.blade.php
│   │   ├── laporan/
│   │   │   └── index.blade.php
│   │   ├── pengaturan/
│   │   │   ├── pengguna/
│   │   │   │   └── index.blade.php
│   │   │   └── klinik/
│   │   │       └── index.blade.php
│   │   └── components/                  # Blade Components
│   │       ├── sidebar.blade.php
│   │       ├── navbar.blade.php
│   │       ├── breadcrumb.blade.php
│   │       ├── alert.blade.php
│   │       └── modal.blade.php
│   ├── css/
│   │   └── app.css
│   └── js/
│       └── app.js
│
├── routes/
│   ├── web.php                          # Routes utama aplikasi
│   └── auth.php                         # Routes autentikasi
│
├── config/
│   ├── permission.php                   # Konfigurasi Spatie Permission
│   └── emr.php                          # Konfigurasi custom aplikasi
│
├── .env
├── .env.example
├── vite.config.js
├── tailwind.config.js
└── composer.json
```

---

## 7. Skema Database (Migration Laravel)

### Enum Values (menggunakan tipe string + constraint di MySQL)

```
Role        : super_admin, admin, dokter, perawat, apoteker, kasir, rekam_medis, pasien
JenisKelamin: L (Laki-laki), P (Perempuan)
StatusKunjungan : menunggu, dalam_pemeriksaan, selesai, dibatalkan
StatusRawatInap : aktif, keluar, pindah_ruang
StatusResep     : menunggu, diproses, siap, diambil
StatusBilling   : belum_bayar, sebagian, lunas, dibatalkan
MetodePembayaran: tunai, transfer, bpjs, asuransi, kartu_debit, kartu_kredit
```

### Migration Files

#### users
```php
// database/migrations/2026_01_01_000001_create_users_table.php
Schema::create('users', function (Blueprint $table) {
    $table->id();
    $table->string('nama');
    $table->string('email')->unique();
    $table->string('password');
    $table->boolean('is_active')->default(true);
    $table->string('foto')->nullable();
    $table->rememberToken();
    $table->timestamps();
});
// Roles & permissions dikelola oleh Spatie Permission (tabel terpisah otomatis)
```

#### klinik
```php
// database/migrations/2026_01_01_000002_create_klinik_table.php
Schema::create('klinik', function (Blueprint $table) {
    $table->id();
    $table->string('nama');
    $table->text('alamat');
    $table->string('telepon')->nullable();
    $table->string('email')->nullable();
    $table->string('logo')->nullable();
    $table->timestamps();
});
```

#### poli
```php
// database/migrations/2026_01_01_000003_create_poli_table.php
Schema::create('poli', function (Blueprint $table) {
    $table->id();
    $table->string('nama');
    $table->string('kode')->unique();
    $table->boolean('is_active')->default(true);
    $table->timestamps();
});
```

#### dokter
```php
// database/migrations/2026_01_01_000004_create_dokter_table.php
Schema::create('dokter', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
    $table->foreignId('poli_id')->nullable()->constrained('poli')->nullOnDelete();
    $table->string('nip')->nullable()->unique();
    $table->string('sip')->nullable();
    $table->string('spesialisasi')->nullable();
    $table->json('jadwal_praktek')->nullable();
    $table->timestamps();
});
```

#### perawat
```php
// database/migrations/2026_01_01_000005_create_perawat_table.php
Schema::create('perawat', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
    $table->string('nip')->nullable()->unique();
    $table->timestamps();
});
```

#### pasien
```php
// database/migrations/2026_01_01_000006_create_pasien_table.php
Schema::create('pasien', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
    $table->string('nomor_rm')->unique();  // Nomor Rekam Medis
    $table->string('nik')->nullable()->unique();
    $table->string('nama');
    $table->date('tanggal_lahir');
    $table->enum('jenis_kelamin', ['L', 'P']);
    $table->text('alamat')->nullable();
    $table->string('telepon')->nullable();
    $table->string('email')->nullable();
    $table->string('golongan_darah')->nullable();
    $table->text('alergi')->nullable();
    $table->string('no_bpjs')->nullable();
    $table->string('no_asuransi')->nullable();
    $table->timestamps();
});
```

#### kunjungan
```php
// database/migrations/2026_01_01_000007_create_kunjungan_table.php
Schema::create('kunjungan', function (Blueprint $table) {
    $table->id();
    $table->string('nomor_antrean');
    $table->foreignId('pasien_id')->constrained('pasien')->onDelete('restrict');
    $table->foreignId('dokter_id')->nullable()->constrained('dokter')->nullOnDelete();
    $table->foreignId('poli_id')->nullable()->constrained('poli')->nullOnDelete();
    $table->dateTime('tanggal')->useCurrent();
    $table->text('keluhan')->nullable();
    $table->enum('status', ['menunggu', 'dalam_pemeriksaan', 'selesai', 'dibatalkan'])
          ->default('menunggu');
    $table->string('tipe_pembayaran')->nullable(); // umum, bpjs, asuransi
    $table->timestamps();
});
```

#### asesmen_perawat
```php
// database/migrations/2026_01_01_000008_create_asesmen_perawat_table.php
Schema::create('asesmen_perawat', function (Blueprint $table) {
    $table->id();
    $table->foreignId('kunjungan_id')->unique()->constrained('kunjungan')->onDelete('cascade');
    $table->foreignId('perawat_id')->nullable()->constrained('perawat')->nullOnDelete();
    $table->decimal('berat_badan', 5, 2)->nullable();
    $table->decimal('tinggi_badan', 5, 2)->nullable();
    $table->string('tekanan_darah')->nullable();    // e.g. "120/80"
    $table->unsignedSmallInteger('nadi')->nullable();
    $table->decimal('suhu', 4, 1)->nullable();
    $table->decimal('saturasi', 4, 1)->nullable();  // SpO2
    $table->decimal('gds', 6, 2)->nullable();       // Gula Darah Sewaktu
    $table->text('anamnesis_awal')->nullable();
    $table->timestamps();
});
```

#### soap_note
```php
// database/migrations/2026_01_01_000009_create_soap_note_table.php
Schema::create('soap_note', function (Blueprint $table) {
    $table->id();
    $table->foreignId('kunjungan_id')->unique()->constrained('kunjungan')->onDelete('cascade');
    $table->text('subjektif')->nullable();
    $table->text('objektif')->nullable();
    $table->text('asesmen')->nullable();
    $table->text('plan')->nullable();
    $table->json('icd_codes')->nullable();  // array kode ICD-10
    $table->timestamps();
});
```

#### kamar
```php
// database/migrations/2026_01_01_000010_create_kamar_table.php
Schema::create('kamar', function (Blueprint $table) {
    $table->id();
    $table->string('nomor_kamar')->unique();
    $table->string('kelas');              // VVIP, VIP, Kelas 1, 2, 3
    $table->unsignedTinyInteger('kapasitas')->default(1);
    $table->decimal('tarif', 12, 2);
    $table->boolean('is_active')->default(true);
    $table->timestamps();
});
```

#### rawat_inap
```php
// database/migrations/2026_01_01_000011_create_rawat_inap_table.php
Schema::create('rawat_inap', function (Blueprint $table) {
    $table->id();
    $table->foreignId('pasien_id')->constrained('pasien')->onDelete('restrict');
    $table->foreignId('kamar_id')->constrained('kamar')->onDelete('restrict');
    $table->dateTime('tanggal_masuk');
    $table->dateTime('tanggal_keluar')->nullable();
    $table->text('diagnosa')->nullable();
    $table->enum('status', ['aktif', 'keluar', 'pindah_ruang'])->default('aktif');
    $table->text('catatan')->nullable();
    $table->timestamps();
});
```

#### obat
```php
// database/migrations/2026_01_01_000012_create_obat_table.php
Schema::create('obat', function (Blueprint $table) {
    $table->id();
    $table->string('kode')->unique();
    $table->string('nama');
    $table->string('generik')->nullable();
    $table->string('satuan');             // tablet, kapsul, ml, dll
    $table->unsignedInteger('stok')->default(0);
    $table->decimal('harga', 12, 2);
    $table->decimal('harga_beli', 12, 2)->nullable();
    $table->string('kategori')->nullable();
    $table->boolean('is_active')->default(true);
    $table->date('expired_date')->nullable();
    $table->timestamps();
});
```

#### resep
```php
// database/migrations/2026_01_01_000013_create_resep_table.php
Schema::create('resep', function (Blueprint $table) {
    $table->id();
    $table->foreignId('kunjungan_id')->constrained('kunjungan')->onDelete('restrict');
    $table->foreignId('dokter_id')->nullable()->constrained('dokter')->nullOnDelete();
    $table->enum('status', ['menunggu', 'diproses', 'siap', 'diambil'])->default('menunggu');
    $table->text('catatan')->nullable();
    $table->timestamps();
});
```

#### item_resep
```php
// database/migrations/2026_01_01_000014_create_item_resep_table.php
Schema::create('item_resep', function (Blueprint $table) {
    $table->id();
    $table->foreignId('resep_id')->constrained('resep')->onDelete('cascade');
    $table->foreignId('obat_id')->constrained('obat')->onDelete('restrict');
    $table->unsignedSmallInteger('jumlah');
    $table->string('aturan_pakai')->nullable(); // e.g. "3x1 setelah makan"
    $table->string('catatan')->nullable();
});
```

#### master_tindakan
```php
// database/migrations/2026_01_01_000015_create_master_tindakan_table.php
Schema::create('master_tindakan', function (Blueprint $table) {
    $table->id();
    $table->string('kode')->unique();
    $table->string('nama');
    $table->decimal('tarif', 12, 2);
    $table->string('kategori')->nullable();
    $table->timestamps();
});
```

#### tindakan
```php
// database/migrations/2026_01_01_000016_create_tindakan_table.php
Schema::create('tindakan', function (Blueprint $table) {
    $table->id();
    $table->foreignId('kunjungan_id')->constrained('kunjungan')->onDelete('restrict');
    $table->foreignId('master_tindakan_id')->constrained('master_tindakan')->onDelete('restrict');
    $table->unsignedSmallInteger('jumlah')->default(1);
    $table->text('catatan')->nullable();
    $table->timestamps();
});
```

#### billing
```php
// database/migrations/2026_01_01_000017_create_billing_table.php
Schema::create('billing', function (Blueprint $table) {
    $table->id();
    $table->foreignId('kunjungan_id')->unique()->constrained('kunjungan')->onDelete('restrict');
    $table->string('nomor_invoice')->unique();
    $table->decimal('total_tagihan', 14, 2);
    $table->decimal('total_bayar', 14, 2)->default(0);
    $table->decimal('sisa', 14, 2);
    $table->enum('status', ['belum_bayar', 'sebagian', 'lunas', 'dibatalkan'])
          ->default('belum_bayar');
    $table->timestamps();
});
```

#### pembayaran
```php
// database/migrations/2026_01_01_000018_create_pembayaran_table.php
Schema::create('pembayaran', function (Blueprint $table) {
    $table->id();
    $table->foreignId('billing_id')->constrained('billing')->onDelete('restrict');
    $table->decimal('jumlah', 14, 2);
    $table->enum('metode', ['tunai', 'transfer', 'bpjs', 'asuransi', 'kartu_debit', 'kartu_kredit']);
    $table->string('referensi')->nullable(); // Nomor referensi transfer/BPJS
    $table->dateTime('tanggal')->useCurrent();
    $table->timestamps();
});
```

---

## 8. Fitur & Modul Utama

### 8.1 Modul Autentikasi
- Login dengan email & password (Laravel Breeze)
- Lupa password (reset via email, Laravel built-in)
- Manajemen sesi berbasis cookie (stateful)
- Proteksi route berdasarkan role via Spatie Permission

### 8.2 Modul Dashboard
- Ringkasan statistik harian (total kunjungan, pasien baru, pendapatan)
- Grafik tren kunjungan mingguan/bulanan (Chart.js via Alpine.js)
- Antrean real-time dengan Livewire polling
- Notifikasi sistem (Laravel Notifications)

### 8.3 Modul Manajemen Pasien
- Registrasi pasien baru
- Pencarian pasien real-time (Livewire, cari nama/NIK/nomor RM)
- Edit data demografi pasien
- Riwayat kunjungan lengkap
- Cetak kartu pasien (PDF via DomPDF)

### 8.4 Modul Pendaftaran & Antrean
- Pendaftaran kunjungan pasien lama/baru
- Pemilihan poli & dokter
- Generate nomor antrean otomatis
- Display antrean real-time (Livewire polling setiap 5 detik)
- Update status antrean

### 8.5 Modul Pemeriksaan (Rekam Medis)
**Asesmen Perawat:**
- Input tanda vital (TD, nadi, suhu, SpO2, BB, TB)
- Anamnesis awal & keluhan utama

**SOAP Note Dokter:**
- Subjektif: keluhan pasien
- Objektif: hasil pemeriksaan fisik
- Asesmen: diagnosis (dengan pencarian kode ICD-10)
- Plan: rencana terapi & tindak lanjut

### 8.6 Modul Rawat Inap
- Admisi pasien rawat inap
- Manajemen kamar & bed
- Catatan perkembangan harian (CPPT)
- Discharge planning
- Surat keterangan rawat inap (PDF)

### 8.7 Modul Farmasi
- Input resep elektronik dari dokter
- Verifikasi & validasi resep oleh apoteker (update status Livewire)
- Dispensing & labeling obat
- Manajemen stok obat
- Alert stok minimum & expired (Laravel Notifications)
- Laporan penggunaan obat (Excel / PDF)

### 8.8 Modul Billing & Kasir
- Generate invoice otomatis dari kunjungan
- Kalkulasi tarif tindakan + obat + kamar
- Proses pembayaran multi-metode
- Cetak kwitansi & invoice (DomPDF)
- Rekap pendapatan harian/bulanan

### 8.9 Modul Laporan
- Laporan kunjungan harian/bulanan
- Laporan 10 besar penyakit (ICD-10)
- Laporan pendapatan
- Laporan penggunaan obat
- Export PDF (DomPDF) & Excel (Maatwebsite)
- Laporan untuk BPJS (format SEP)

### 8.10 Modul Pengaturan
- Manajemen user & role (Spatie Permission)
- Konfigurasi data klinik/faskes
- Master data poli, dokter, tindakan, obat
- Konfigurasi tarif
- Log aktivitas sistem (Spatie Activity Log)

---

## 9. Hak Akses per Role (RBAC)

### Spatie Permission Seeder

```php
// database/seeders/RolePermissionSeeder.php

use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

$permissions = [
    // Pasien
    'pasien.view', 'pasien.create', 'pasien.edit', 'pasien.delete',
    // Kunjungan
    'kunjungan.view', 'kunjungan.create', 'kunjungan.edit', 'kunjungan.delete',
    // Asesmen
    'asesmen.view', 'asesmen.create', 'asesmen.edit',
    // SOAP
    'soap.view', 'soap.create', 'soap.edit',
    // Resep
    'resep.view', 'resep.create', 'resep.edit',
    // Obat
    'obat.view', 'obat.create', 'obat.edit', 'obat.delete',
    // Tindakan
    'tindakan.view', 'tindakan.create',
    // Billing
    'billing.view', 'billing.create', 'billing.edit',
    // Pembayaran
    'pembayaran.view', 'pembayaran.create',
    // Laporan
    'laporan.view', 'laporan.keuangan', 'laporan.farmasi',
    // Rekam Medis
    'rekammedis.view', 'rekammedis.create', 'rekammedis.edit',
    // Pengaturan
    'pengaturan.view', 'pengaturan.edit',
    // User Management
    'user.view', 'user.create', 'user.edit', 'user.delete',
];

foreach ($permissions as $perm) {
    Permission::firstOrCreate(['name' => $perm]);
}

$roles = [
    'pasien' => [
        'kunjungan.view', 'rekammedis.view', 'billing.view',
    ],
    'kasir' => [
        'pasien.view', 'kunjungan.view',
        'billing.view', 'billing.create', 'billing.edit',
        'pembayaran.view', 'pembayaran.create',
        'laporan.keuangan',
    ],
    'perawat' => [
        'pasien.view', 'pasien.create', 'pasien.edit',
        'kunjungan.view', 'kunjungan.create', 'kunjungan.edit',
        'asesmen.view', 'asesmen.create', 'asesmen.edit',
        'tindakan.view', 'tindakan.create',
    ],
    'apoteker' => [
        'resep.view', 'resep.edit',
        'obat.view', 'obat.create', 'obat.edit',
        'laporan.farmasi',
    ],
    'rekam_medis' => [
        'pasien.view', 'pasien.create', 'pasien.edit',
        'rekammedis.view', 'rekammedis.create', 'rekammedis.edit',
        'laporan.view', 'laporan.view',
    ],
    'dokter' => [
        'pasien.view',
        'kunjungan.view', 'kunjungan.edit',
        'soap.view', 'soap.create', 'soap.edit',
        'resep.view', 'resep.create', 'resep.edit',
        'tindakan.view', 'tindakan.create',
        'laporan.view',
    ],
    'admin' => [
        'pasien.view', 'pasien.create', 'pasien.edit', 'pasien.delete',
        'kunjungan.view', 'kunjungan.create', 'kunjungan.edit', 'kunjungan.delete',
        'user.view', 'user.create', 'user.edit',
        'laporan.view', 'laporan.keuangan',
        'pengaturan.view', 'pengaturan.edit',
    ],
    'super_admin' => [],  // diberikan semua permission via Gate::before
];

foreach ($roles as $roleName => $rolePerms) {
    $role = Role::firstOrCreate(['name' => $roleName]);
    $role->syncPermissions($rolePerms);
}
```

### Gate Super Admin di AuthServiceProvider

```php
// app/Providers/AuthServiceProvider.php

use Illuminate\Support\Facades\Gate;

Gate::before(function ($user, $ability) {
    if ($user->hasRole('super_admin')) {
        return true;
    }
});
```

### Middleware Cek Permission di Route

```php
// routes/web.php

Route::middleware(['auth', 'permission:pasien.view'])->group(function () {
    Route::get('/pasien', [PasienController::class, 'index'])->name('pasien.index');
    Route::get('/pasien/{id}', [PasienController::class, 'show'])->name('pasien.show');
});

Route::middleware(['auth', 'permission:pasien.create'])->group(function () {
    Route::get('/pasien/create', [PasienController::class, 'create'])->name('pasien.create');
    Route::post('/pasien', [PasienController::class, 'store'])->name('pasien.store');
});
```

### Contoh Penggunaan di Blade

```blade
@can('pasien.create')
    <a href="{{ route('pasien.create') }}" class="btn btn-primary">Tambah Pasien</a>
@endcan

@hasrole('dokter|super_admin')
    <a href="{{ route('soap.create', $kunjungan) }}">Input SOAP Note</a>
@endhasrole
```

---

## 10. Route & Controller

### Routes (web.php)

```php
// routes/web.php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\{
    DashboardController, PasienController, KunjunganController,
    PemeriksaanController, RawatInapController, FarmasiController,
    BillingController, LaporanController, PengaturanController
};

// Auth routes (dari Laravel Breeze)
require __DIR__.'/auth.php';

Route::middleware('auth')->group(function () {

    // Dashboard
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    // Pasien
    Route::resource('pasien', PasienController::class)
         ->middleware('permission:pasien.view');

    // Kunjungan & Pendaftaran
    Route::prefix('kunjungan')->name('kunjungan.')->middleware('permission:kunjungan.view')->group(function () {
        Route::get('/', [KunjunganController::class, 'index'])->name('index');
        Route::get('/pendaftaran', [KunjunganController::class, 'pendaftaran'])->name('pendaftaran');
        Route::post('/', [KunjunganController::class, 'store'])->name('store')->middleware('permission:kunjungan.create');
        Route::patch('/{kunjungan}/status', [KunjunganController::class, 'updateStatus'])->name('update-status');
    });

    // Pemeriksaan (Asesmen + SOAP)
    Route::prefix('pemeriksaan')->name('pemeriksaan.')->middleware('permission:asesmen.view')->group(function () {
        Route::get('/', [PemeriksaanController::class, 'index'])->name('index');
        Route::get('/{kunjungan}', [PemeriksaanController::class, 'show'])->name('show');
        Route::post('/asesmen', [PemeriksaanController::class, 'storeAsesmen'])->name('asesmen.store')->middleware('permission:asesmen.create');
        Route::post('/soap', [PemeriksaanController::class, 'storeSoap'])->name('soap.store')->middleware('permission:soap.create');
    });

    // Rawat Inap
    Route::resource('rawat-inap', RawatInapController::class);

    // Farmasi
    Route::prefix('farmasi')->name('farmasi.')->middleware('permission:resep.view')->group(function () {
        Route::get('/resep', [FarmasiController::class, 'resepIndex'])->name('resep.index');
        Route::patch('/resep/{resep}/status', [FarmasiController::class, 'updateStatusResep'])->name('resep.update-status');
        Route::get('/stok-obat', [FarmasiController::class, 'stokIndex'])->name('stok.index')->middleware('permission:obat.view');
        Route::post('/stok-obat', [FarmasiController::class, 'storeObat'])->name('stok.store')->middleware('permission:obat.create');
    });

    // Billing
    Route::prefix('billing')->name('billing.')->middleware('permission:billing.view')->group(function () {
        Route::get('/', [BillingController::class, 'index'])->name('index');
        Route::post('/generate/{kunjungan}', [BillingController::class, 'generate'])->name('generate')->middleware('permission:billing.create');
        Route::post('/{billing}/bayar', [BillingController::class, 'prosesBayar'])->name('bayar')->middleware('permission:pembayaran.create');
        Route::get('/{billing}/invoice', [BillingController::class, 'cetakInvoice'])->name('invoice');
    });

    // Laporan
    Route::prefix('laporan')->name('laporan.')->middleware('permission:laporan.view')->group(function () {
        Route::get('/', [LaporanController::class, 'index'])->name('index');
        Route::get('/kunjungan', [LaporanController::class, 'kunjungan'])->name('kunjungan');
        Route::get('/keuangan', [LaporanController::class, 'keuangan'])->name('keuangan')->middleware('permission:laporan.keuangan');
        Route::get('/export/excel/{type}', [LaporanController::class, 'exportExcel'])->name('export.excel');
        Route::get('/export/pdf/{type}', [LaporanController::class, 'exportPdf'])->name('export.pdf');
    });

    // Pengaturan
    Route::prefix('pengaturan')->name('pengaturan.')->middleware('permission:pengaturan.view')->group(function () {
        Route::get('/pengguna', [PengaturanController::class, 'pengguna'])->name('pengguna');
        Route::get('/klinik', [PengaturanController::class, 'klinik'])->name('klinik');
        Route::get('/poli', [PengaturanController::class, 'poli'])->name('poli');
        Route::get('/tindakan', [PengaturanController::class, 'tindakan'])->name('tindakan');
    });
});
```

### Contoh Livewire Component

```php
// app/Livewire/Pasien/PasienTable.php

namespace App\Livewire\Pasien;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\Pasien;

class PasienTable extends Component
{
    use WithPagination;

    public string $search = '';
    public string $sortBy = 'nama';
    public string $sortDir = 'asc';
    public int $perPage = 15;

    protected $queryString = ['search', 'sortBy', 'sortDir'];

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function sortColumn(string $column): void
    {
        if ($this->sortBy === $column) {
            $this->sortDir = $this->sortDir === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $column;
            $this->sortDir = 'asc';
        }
    }

    public function render()
    {
        $pasien = Pasien::query()
            ->when($this->search, fn($q) => $q
                ->where('nama', 'like', "%{$this->search}%")
                ->orWhere('nik', 'like', "%{$this->search}%")
                ->orWhere('nomor_rm', 'like', "%{$this->search}%")
            )
            ->orderBy($this->sortBy, $this->sortDir)
            ->paginate($this->perPage);

        return view('livewire.pasien.pasien-table', compact('pasien'));
    }
}
```

```php
// app/Livewire/Dashboard/AntreanRealtime.php

namespace App\Livewire\Dashboard;

use Livewire\Component;
use App\Models\Kunjungan;

class AntreanRealtime extends Component
{
    public function getListeners()
    {
        return ['refreshAntrean' => '$refresh'];
    }

    public function render()
    {
        $antrean = Kunjungan::query()
            ->with(['pasien', 'poli', 'dokter.user'])
            ->whereDate('tanggal', today())
            ->whereIn('status', ['menunggu', 'dalam_pemeriksaan'])
            ->orderBy('tanggal')
            ->get();

        return view('livewire.dashboard.antrean-realtime', compact('antrean'));
    }
}
```

---

## 11. Autentikasi & Keamanan

### Laravel Breeze Setup

```bash
composer require laravel/breeze --dev
php artisan breeze:install blade
php artisan migrate
npm install && npm run dev
```

### Konfigurasi Spatie Permission

```php
// config/permission.php (key penting)
'models' => [
    'permission' => Spatie\Permission\Models\Permission::class,
    'role'       => Spatie\Permission\Models\Role::class,
],
'table_names' => [
    'roles'                 => 'roles',
    'permissions'           => 'permissions',
    'model_has_permissions' => 'model_has_permissions',
    'model_has_roles'       => 'model_has_roles',
    'role_has_permissions'  => 'role_has_permissions',
],
'cache' => [
    'expiration_time'  => \DateInterval::createFromDateString('24 hours'),
    'key'              => 'spatie.permission.cache',
    'store'            => 'default',
],
```

### Keamanan Data

- Password di-hash dengan **bcrypt** (default Laravel, cost factor: 12)
- CSRF protection aktif di semua form (default Laravel + Livewire)
- HTTPS wajib di production (konfigurasi `APP_URL` dengan `https://`)
- Validasi input via **Form Request** (`StoreXxxRequest.php`)
- **Rate limiting** pada endpoint login (`throttle:login` middleware)
- Audit trail dengan **Spatie Activity Log** untuk aksi sensitif
- SQL injection prevention via **Eloquent ORM** (parameterized queries)
- XSS prevention via **Blade auto-escaping** (`{{ }}`)
- Session di-enkripsi dengan `APP_KEY` (default Laravel)

---

## 12. UI/UX Guidelines

### Design System

- **Framework:** Tailwind CSS 3.4+ + Flowbite 2.x
- **Font:** Inter (via Fontsource npm)
- **Warna Primer:** Biru medis (`blue-600` / `#2563EB`)
- **Mode:** Light mode default (dark mode opsional fase berikutnya)
- **Breakpoint utama:** `md` (768px) — minimum tampilan tablet

---

### 12.1 Instalasi Tailwind CSS

```bash
# Install Tailwind CSS dan plugin
npm install -D tailwindcss@3 postcss autoprefixer
npm install -D @tailwindcss/forms
npm install -D @tailwindcss/typography
npm install -D tailwind-scrollbar

# Install Flowbite
npm install flowbite

# Install font Inter via Fontsource
npm install @fontsource/inter

# Generate tailwind.config.js
npx tailwindcss init -p
```

---

### 12.2 Konfigurasi `tailwind.config.js`

```js
// tailwind.config.js

import defaultTheme from 'tailwindcss/defaultTheme';
import colors from 'tailwindcss/colors';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './resources/**/*.blade.php',
        './resources/**/*.js',
        './app/Livewire/**/*.php',         // scan class Tailwind di Livewire PHP
        './node_modules/flowbite/**/*.js', // scan komponen Flowbite
    ],

    theme: {
        extend: {
            // Font
            fontFamily: {
                sans: ['Inter', ...defaultTheme.fontFamily.sans],
            },

            // Warna kustom EMR
            colors: {
                primary: {
                    50:  '#eff6ff',
                    100: '#dbeafe',
                    200: '#bfdbfe',
                    300: '#93c5fd',
                    400: '#60a5fa',
                    500: '#3b82f6',
                    600: '#2563eb',  // primary default
                    700: '#1d4ed8',
                    800: '#1e40af',
                    900: '#1e3a8a',
                    950: '#172554',
                },
                // Status warna konsisten seluruh aplikasi
                status: {
                    menunggu:          '#f59e0b',  // amber-500
                    dalam_pemeriksaan: '#3b82f6',  // blue-500
                    selesai:           '#10b981',  // emerald-500
                    dibatalkan:        '#ef4444',  // red-500
                },
            },

            // Ukuran sidebar
            spacing: {
                sidebar: '16rem',     // 256px — lebar sidebar desktop
                'sidebar-sm': '4rem', // 64px — lebar sidebar collapsed
            },

            // Tinggi navbar
            height: {
                navbar: '4rem', // 64px
            },

            // Border radius kustom
            borderRadius: {
                card: '0.75rem', // rounded-xl untuk card
            },

            // Box shadow kustom
            boxShadow: {
                card:    '0 1px 3px 0 rgb(0 0 0 / 0.08), 0 1px 2px -1px rgb(0 0 0 / 0.08)',
                sidebar: '2px 0 8px 0 rgb(0 0 0 / 0.06)',
            },

            // Animasi tambahan
            keyframes: {
                'fade-in': {
                    '0%':   { opacity: '0', transform: 'translateY(-4px)' },
                    '100%': { opacity: '1', transform: 'translateY(0)' },
                },
                'slide-in': {
                    '0%':   { opacity: '0', transform: 'translateX(-8px)' },
                    '100%': { opacity: '1', transform: 'translateX(0)' },
                },
            },
            animation: {
                'fade-in':  'fade-in 0.2s ease-out',
                'slide-in': 'slide-in 0.2s ease-out',
            },
        },
    },

    plugins: [
        require('@tailwindcss/forms')({
            strategy: 'class', // gunakan class (.form-input, .form-select, dll)
        }),
        require('@tailwindcss/typography'),
        require('tailwind-scrollbar')({ nocompatible: true }),
        require('flowbite/plugin'),
    ],
};
```

---

### 12.3 Konfigurasi `vite.config.js`

```js
// vite.config.js

import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
            ],
            refresh: [
                'resources/views/**',
                'app/Livewire/**',  // hot-reload saat Livewire PHP berubah
            ],
        }),
    ],
});
```

---

### 12.4 File CSS Utama

```css
/* resources/css/app.css */

/* Font Inter */
@import '@fontsource/inter/400.css';
@import '@fontsource/inter/500.css';
@import '@fontsource/inter/600.css';
@import '@fontsource/inter/700.css';

@tailwind base;
@tailwind components;
@tailwind utilities;

/* ─── Base Layer ─────────────────────────────────── */
@layer base {
    html {
        @apply antialiased;
    }
    body {
        @apply bg-gray-50 text-gray-800 text-sm font-sans;
    }
    h1 { @apply text-2xl font-bold text-gray-900; }
    h2 { @apply text-xl  font-semibold text-gray-900; }
    h3 { @apply text-lg  font-semibold text-gray-800; }
}

/* ─── Component Layer ────────────────────────────── */
@layer components {

    /* --- Button --- */
    .btn {
        @apply inline-flex items-center justify-center gap-2
               px-4 py-2 text-sm font-medium rounded-lg
               transition-colors duration-150 focus:outline-none
               focus:ring-2 focus:ring-offset-1 disabled:opacity-50 disabled:cursor-not-allowed;
    }
    .btn-primary {
        @apply btn bg-primary-600 text-white hover:bg-primary-700
               focus:ring-primary-500;
    }
    .btn-secondary {
        @apply btn bg-white text-gray-700 border border-gray-300
               hover:bg-gray-50 focus:ring-gray-400;
    }
    .btn-danger {
        @apply btn bg-red-600 text-white hover:bg-red-700
               focus:ring-red-500;
    }
    .btn-success {
        @apply btn bg-emerald-600 text-white hover:bg-emerald-700
               focus:ring-emerald-500;
    }
    .btn-warning {
        @apply btn bg-amber-500 text-white hover:bg-amber-600
               focus:ring-amber-400;
    }
    .btn-info {
        @apply btn bg-sky-500 text-white hover:bg-sky-600
               focus:ring-sky-400;
    }
    .btn-sm {
        @apply px-3 py-1.5 text-xs;
    }
    .btn-lg {
        @apply px-6 py-3 text-base;
    }
    .btn-icon {
        @apply btn p-2;
    }

    /* --- Form Input --- */
    .form-input {
        @apply form-input w-full rounded-lg border-gray-300 text-sm
               shadow-sm placeholder-gray-400
               focus:border-primary-500 focus:ring-primary-500;
    }
    .form-select {
        @apply form-select w-full rounded-lg border-gray-300 text-sm
               shadow-sm focus:border-primary-500 focus:ring-primary-500;
    }
    .form-textarea {
        @apply form-textarea w-full rounded-lg border-gray-300 text-sm
               shadow-sm placeholder-gray-400 resize-y
               focus:border-primary-500 focus:ring-primary-500;
    }
    .form-checkbox {
        @apply form-checkbox rounded border-gray-300 text-primary-600
               focus:ring-primary-500;
    }
    .form-radio {
        @apply form-radio border-gray-300 text-primary-600
               focus:ring-primary-500;
    }
    .form-label {
        @apply block text-sm font-medium text-gray-700 mb-1;
    }
    .form-error {
        @apply mt-1 text-xs text-red-600;
    }
    .form-hint {
        @apply mt-1 text-xs text-gray-500;
    }
    .form-group {
        @apply space-y-1;
    }

    /* --- Card --- */
    .card {
        @apply bg-white rounded-card shadow-card border border-gray-100;
    }
    .card-header {
        @apply px-6 py-4 border-b border-gray-100 flex items-center justify-between;
    }
    .card-body {
        @apply px-6 py-4;
    }
    .card-footer {
        @apply px-6 py-4 border-t border-gray-100 bg-gray-50 rounded-b-card;
    }

    /* --- Table --- */
    .table-wrapper {
        @apply overflow-x-auto rounded-lg border border-gray-200;
    }
    .table {
        @apply w-full text-sm text-left text-gray-700;
    }
    .table thead {
        @apply bg-gray-50 text-xs text-gray-500 uppercase tracking-wider;
    }
    .table thead th {
        @apply px-4 py-3 font-medium;
    }
    .table tbody tr {
        @apply border-t border-gray-100 hover:bg-gray-50 transition-colors;
    }
    .table tbody td {
        @apply px-4 py-3;
    }
    .table-sortable {
        @apply cursor-pointer select-none hover:text-gray-900;
    }

    /* --- Badge / Status --- */
    .badge {
        @apply inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium;
    }
    .badge-primary   { @apply badge bg-primary-100 text-primary-700; }
    .badge-success   { @apply badge bg-emerald-100 text-emerald-700; }
    .badge-warning   { @apply badge bg-amber-100 text-amber-700; }
    .badge-danger    { @apply badge bg-red-100 text-red-700; }
    .badge-info      { @apply badge bg-sky-100 text-sky-700; }
    .badge-gray      { @apply badge bg-gray-100 text-gray-600; }

    /* Badge status kunjungan */
    .badge-menunggu          { @apply badge bg-amber-100 text-amber-700; }
    .badge-dalam_pemeriksaan { @apply badge bg-blue-100 text-blue-700; }
    .badge-selesai           { @apply badge bg-emerald-100 text-emerald-700; }
    .badge-dibatalkan        { @apply badge bg-red-100 text-red-700; }

    /* --- Alert / Flash --- */
    .alert {
        @apply flex items-start gap-3 p-4 rounded-lg text-sm;
    }
    .alert-success { @apply alert bg-emerald-50 text-emerald-800 border border-emerald-200; }
    .alert-error   { @apply alert bg-red-50 text-red-800 border border-red-200; }
    .alert-warning { @apply alert bg-amber-50 text-amber-800 border border-amber-200; }
    .alert-info    { @apply alert bg-blue-50 text-blue-800 border border-blue-200; }

    /* --- Page Header --- */
    .page-header {
        @apply mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3;
    }
    .page-title {
        @apply text-xl font-semibold text-gray-900;
    }
    .page-subtitle {
        @apply text-sm text-gray-500 mt-0.5;
    }

    /* --- Sidebar --- */
    .sidebar {
        @apply fixed inset-y-0 left-0 z-40 w-sidebar bg-white border-r border-gray-200
               shadow-sidebar flex flex-col transition-transform duration-200;
    }
    .sidebar-logo {
        @apply flex items-center gap-3 h-navbar px-5 border-b border-gray-100;
    }
    .sidebar-nav {
        @apply flex-1 overflow-y-auto py-4 px-3 space-y-1
               scrollbar-thin scrollbar-thumb-gray-200 scrollbar-track-transparent;
    }
    .nav-item {
        @apply flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium
               text-gray-600 hover:bg-gray-100 hover:text-gray-900
               transition-colors duration-150 cursor-pointer;
    }
    .nav-item.active {
        @apply bg-primary-50 text-primary-700 font-semibold;
    }
    .nav-icon {
        @apply w-5 h-5 flex-shrink-0;
    }
    .nav-group-label {
        @apply px-3 mb-1 mt-4 text-xs font-semibold text-gray-400 uppercase tracking-wider;
    }

    /* --- Navbar --- */
    .navbar {
        @apply fixed top-0 right-0 z-30 h-navbar bg-white border-b border-gray-200
               flex items-center justify-between px-5
               left-0 md:left-sidebar;
    }

    /* --- Modal (Flowbite) override --- */
    .modal-header {
        @apply flex items-center justify-between p-5 border-b border-gray-200 rounded-t-lg;
    }
    .modal-title {
        @apply text-base font-semibold text-gray-900;
    }
    .modal-body {
        @apply p-5 space-y-4;
    }
    .modal-footer {
        @apply flex items-center justify-end gap-3 p-5 border-t border-gray-200;
    }

    /* --- Stat Card (Dashboard) --- */
    .stat-card {
        @apply card p-5 flex items-center gap-4;
    }
    .stat-icon {
        @apply w-12 h-12 rounded-xl flex items-center justify-center flex-shrink-0;
    }
    .stat-value {
        @apply text-2xl font-bold text-gray-900;
    }
    .stat-label {
        @apply text-sm text-gray-500;
    }

    /* --- Loading (Livewire) --- */
    .loading-overlay {
        @apply absolute inset-0 bg-white/60 flex items-center justify-center
               rounded-lg z-10;
    }
    .spinner {
        @apply w-5 h-5 border-2 border-primary-600 border-t-transparent
               rounded-full animate-spin;
    }

    /* --- Breadcrumb --- */
    .breadcrumb {
        @apply flex items-center gap-1.5 text-sm text-gray-500 mb-4;
    }
    .breadcrumb-item {
        @apply hover:text-gray-700 transition-colors;
    }
    .breadcrumb-separator {
        @apply text-gray-300;
    }
    .breadcrumb-active {
        @apply text-gray-900 font-medium;
    }

    /* --- Divider --- */
    .divider {
        @apply border-t border-gray-200 my-4;
    }

    /* --- Empty State --- */
    .empty-state {
        @apply flex flex-col items-center justify-center py-12 text-center;
    }
    .empty-state-icon {
        @apply w-12 h-12 text-gray-300 mb-3;
    }
    .empty-state-text {
        @apply text-sm text-gray-400;
    }
}

/* ─── Utility Layer ──────────────────────────────── */
@layer utilities {
    .content-area {
        @apply pt-navbar pl-0 md:pl-sidebar min-h-screen;
    }
    .main-content {
        @apply p-6 max-w-screen-2xl;
    }
    .text-truncate {
        @apply truncate max-w-xs;
    }
    /* Livewire wire:loading helper */
    [wire\:loading] {
        @apply opacity-50 pointer-events-none;
    }
}
```

---

### 12.5 File JavaScript Utama

```js
// resources/js/app.js

import './bootstrap';

// Alpine.js sudah di-bundle Livewire — tidak perlu import manual
// Flowbite: inisialisasi komponen
import { initFlowbite } from 'flowbite';

// Inisialisasi Flowbite setiap kali Livewire update DOM
document.addEventListener('livewire:navigated', () => initFlowbite());
document.addEventListener('DOMContentLoaded', () => initFlowbite());
```

---

### 12.6 Komponen Blade Reusable

#### Button Component

```blade
{{-- resources/views/components/button.blade.php --}}
@props([
    'variant' => 'primary',  // primary | secondary | danger | success | warning | info
    'size'    => '',          // sm | lg | ''
    'type'    => 'button',
    'href'    => null,
])

@php
    $classes = "btn btn-{$variant}" . ($size ? " btn-{$size}" : '');
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->class([$classes]) }}>{{ $slot }}</a>
@else
    <button type="{{ $type }}" {{ $attributes->class([$classes]) }}>{{ $slot }}</button>
@endif
```

**Penggunaan:**
```blade
<x-button variant="primary" href="{{ route('pasien.create') }}">+ Tambah Pasien</x-button>
<x-button variant="danger" wire:click="hapus({{ $id }})">Hapus</x-button>
<x-button variant="secondary" size="sm">Batal</x-button>
```

#### Form Input Component

```blade
{{-- resources/views/components/form/input.blade.php --}}
@props([
    'label'    => '',
    'name'     => '',
    'type'     => 'text',
    'hint'     => '',
    'required' => false,
])

<div class="form-group">
    @if ($label)
        <label for="{{ $name }}" class="form-label">
            {{ $label }}
            @if ($required) <span class="text-red-500">*</span> @endif
        </label>
    @endif

    <input
        id="{{ $name }}"
        name="{{ $name }}"
        type="{{ $type }}"
        {{ $attributes->class(['form-input', 'border-red-400' => $errors->has($name)]) }}
    />

    @error($name)
        <p class="form-error">{{ $message }}</p>
    @enderror

    @if ($hint)
        <p class="form-hint">{{ $hint }}</p>
    @endif
</div>
```

**Penggunaan:**
```blade
<x-form.input
    label="Nama Lengkap"
    name="nama"
    wire:model.blur="nama"
    required
/>
<x-form.input
    label="Tanggal Lahir"
    name="tanggal_lahir"
    type="date"
    wire:model="tanggal_lahir"
/>
```

#### Badge Status Component

```blade
{{-- resources/views/components/badge-status.blade.php --}}
@props(['status'])

@php
    $map = [
        'menunggu'          => ['label' => 'Menunggu',          'class' => 'badge-menunggu'],
        'dalam_pemeriksaan' => ['label' => 'Dalam Pemeriksaan', 'class' => 'badge-dalam_pemeriksaan'],
        'selesai'           => ['label' => 'Selesai',           'class' => 'badge-selesai'],
        'dibatalkan'        => ['label' => 'Dibatalkan',        'class' => 'badge-dibatalkan'],
        'menunggu_resep'    => ['label' => 'Menunggu',          'class' => 'badge-warning'],
        'diproses'          => ['label' => 'Diproses',          'class' => 'badge-info'],
        'siap'              => ['label' => 'Siap Diambil',      'class' => 'badge-success'],
        'diambil'           => ['label' => 'Sudah Diambil',     'class' => 'badge-gray'],
        'belum_bayar'       => ['label' => 'Belum Bayar',       'class' => 'badge-danger'],
        'sebagian'          => ['label' => 'Sebagian',          'class' => 'badge-warning'],
        'lunas'             => ['label' => 'Lunas',             'class' => 'badge-success'],
    ];
    $item = $map[$status] ?? ['label' => $status, 'class' => 'badge-gray'];
@endphp

<span class="{{ $item['class'] }}">{{ $item['label'] }}</span>
```

**Penggunaan:**
```blade
<x-badge-status :status="$kunjungan->status" />
<x-badge-status :status="$resep->status" />
<x-badge-status :status="$billing->status" />
```

#### Alert / Flash Notification

```blade
{{-- resources/views/components/alert.blade.php --}}
@if (session('success'))
    <div class="alert-success animate-fade-in" role="alert">
        <svg class="w-5 h-5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
        </svg>
        <span>{{ session('success') }}</span>
    </div>
@endif

@if (session('error'))
    <div class="alert-error animate-fade-in" role="alert">
        <svg class="w-5 h-5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
        </svg>
        <span>{{ session('error') }}</span>
    </div>
@endif
```

#### Livewire Table dengan Tailwind

```blade
{{-- resources/views/livewire/pasien/pasien-table.blade.php --}}
<div class="relative">

    {{-- Toolbar --}}
    <div class="mb-4 flex flex-col sm:flex-row gap-3 justify-between">
        <div class="relative w-full sm:max-w-xs">
            <span class="absolute inset-y-0 left-3 flex items-center text-gray-400">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M21 21l-4.35-4.35M17 11A6 6 0 1 1 5 11a6 6 0 0 1 12 0z"/>
                </svg>
            </span>
            <input
                wire:model.live.debounce.300ms="search"
                type="text"
                placeholder="Cari nama, NIK, No. RM..."
                class="form-input pl-9"
            />
        </div>

        @can('pasien.create')
        <a href="{{ route('pasien.create') }}" class="btn-primary">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            Tambah Pasien
        </a>
        @endcan
    </div>

    {{-- Loading overlay --}}
    <div wire:loading.flex class="loading-overlay">
        <div class="spinner"></div>
    </div>

    {{-- Tabel --}}
    <div class="table-wrapper">
        <table class="table">
            <thead>
                <tr>
                    <th wire:click="sortColumn('nomor_rm')" class="table-sortable">
                        No. RM
                        @if ($sortBy === 'nomor_rm')
                            <span>{{ $sortDir === 'asc' ? '↑' : '↓' }}</span>
                        @endif
                    </th>
                    <th wire:click="sortColumn('nama')" class="table-sortable">
                        Nama
                        @if ($sortBy === 'nama')
                            <span>{{ $sortDir === 'asc' ? '↑' : '↓' }}</span>
                        @endif
                    </th>
                    <th>NIK</th>
                    <th>Tgl. Lahir</th>
                    <th>Telepon</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($pasien as $p)
                <tr>
                    <td class="font-mono text-xs">{{ $p->nomor_rm }}</td>
                    <td class="font-medium text-gray-900">{{ $p->nama }}</td>
                    <td class="text-gray-500">{{ $p->nik ?? '-' }}</td>
                    <td>{{ $p->tanggal_lahir->format('d/m/Y') }}</td>
                    <td>{{ $p->telepon ?? '-' }}</td>
                    <td>
                        <div class="flex items-center gap-1">
                            <a href="{{ route('pasien.show', $p) }}" class="btn-info btn-sm">Detail</a>
                            @can('pasien.edit')
                            <a href="{{ route('pasien.edit', $p) }}" class="btn-warning btn-sm">Edit</a>
                            @endcan
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6">
                        <div class="empty-state">
                            <svg class="empty-state-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                      d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0"/>
                            </svg>
                            <p class="empty-state-text">Tidak ada data pasien ditemukan</p>
                        </div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Pagination --}}
    <div class="mt-4 flex items-center justify-between text-sm text-gray-500">
        <span>Menampilkan {{ $pasien->firstItem() ?? 0 }}–{{ $pasien->lastItem() ?? 0 }} dari {{ $pasien->total() }} data</span>
        {{ $pasien->links() }}
    </div>
</div>
```

---

### 12.7 Aksesibilitas

- Semua `<input>` memiliki `<label>` dengan atribut `for` yang benar
- Tombol aksi memiliki `aria-label` jika hanya berisi icon
- Keyboard navigation support (focus ring aktif via Tailwind `focus:ring-*`)
- Contrast ratio minimum WCAG AA (4.5:1) — terjamin dengan palet warna yang didefinisikan
- Modal Flowbite mendukung `aria-modal`, `role="dialog"`, dan `Escape` untuk menutup

---

### 12.8 Konfigurasi Pagination Tailwind di Laravel

```php
// app/Providers/AppServiceProvider.php

use Illuminate\Pagination\Paginator;

public function boot(): void
{
    Paginator::useTailwind(); // gunakan tampilan pagination Tailwind bawaan Laravel
}
```

---

## 13. Setup & Konfigurasi Awal

```bash
# 1. Buat project Laravel 12
composer create-project laravel/laravel emr-laravel
cd emr-laravel

# 2. Install Laravel Breeze (auth scaffolding)
composer require laravel/breeze --dev
php artisan breeze:install blade
# Pilih: Blade with Alpine ✓

# 3. Install Livewire 3
composer require livewire/livewire

# 4. Install Spatie Permission (RBAC)
composer require spatie/laravel-permission
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"

# 5. Install Spatie Activity Log (Audit Trail)
composer require spatie/laravel-activitylog
php artisan vendor:publish --provider="Spatie\Activitylog\ActivitylogServiceProvider" --tag="activitylog-migrations"

# 6. Install Laravel Excel (export Excel)
composer require maatwebsite/excel

# 7. Install DomPDF (export PDF)
composer require barryvdh/laravel-dompdf

# 8. Install Laravel Telescope (dev only)
composer require laravel/telescope --dev
php artisan telescope:install

# 9. Konfigurasi database di .env
# (lihat bagian Environment Variables)

# 10. Jalankan semua migrasi
php artisan migrate

# 11. Seed data awal
php artisan db:seed

# 12. Install Tailwind CSS & plugins
npm install -D tailwindcss@3 postcss autoprefixer
npm install -D @tailwindcss/forms @tailwindcss/typography tailwind-scrollbar
npm install flowbite
npm install @fontsource/inter
npx tailwindcss init -p

# 13. Build frontend assets
npm install
npm run dev

# 13. Tambahkan HasRoles trait ke User model
# app/Models/User.php → use HasRoles;

# 14. Jalankan development server
php artisan serve
```

### Tambahkan HasRoles ke User Model

```php
// app/Models/User.php

use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasRoles;
    // ...
}
```

### Daftarkan Livewire Components (opsional, jika tidak auto-discover)

```php
// app/Providers/AppServiceProvider.php

use Livewire\Livewire;
use App\Livewire\Pasien\PasienTable;

public function boot(): void
{
    Livewire::component('pasien.pasien-table', PasienTable::class);
}
```

---

## 14. Environment Variables

```env
# .env

# Aplikasi
APP_NAME="EMR System"
APP_ENV=local
APP_KEY=base64:...  # generate dengan: php artisan key:generate
APP_DEBUG=true
APP_URL=http://localhost:8000
APP_TIMEZONE=Asia/Makassar

# Database MySQL
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=emr_db
DB_USERNAME=root
DB_PASSWORD=

# Session & Cache
SESSION_DRIVER=database
SESSION_LIFETIME=480        # 8 jam (menit)
CACHE_STORE=database
QUEUE_CONNECTION=database

# Email (reset password & notifikasi)
MAIL_MAILER=smtp
MAIL_HOST=smtp.mailtrap.io  # ganti dengan SMTP production
MAIL_PORT=587
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS="noreply@emr-app.com"
MAIL_FROM_NAME="${APP_NAME}"

# Livewire
LIVEWIRE_ASSET_URL="${APP_URL}"

# Filesystem
FILESYSTEM_DISK=local

# Log
LOG_CHANNEL=daily
LOG_LEVEL=debug
```

---

## 15. Roadmap Pengembangan

### Phase 1 — MVP (Bulan 1–2)
- [ ] Setup project Laravel 12 & Livewire 3
- [ ] Autentikasi (login, lupa password, RBAC Spatie)
- [ ] Manajemen pasien (CRUD + Livewire search)
- [ ] Pendaftaran & antrean (Livewire real-time)
- [ ] Asesmen perawat & SOAP note dokter
- [ ] Resep elektronik sederhana
- [ ] Billing & pembayaran tunai

### Phase 2 — Core Features (Bulan 3–4)
- [ ] Rawat inap & manajemen kamar
- [ ] Farmasi lengkap (stok, dispensing, alert)
- [ ] Laporan dasar (PDF DomPDF & Excel Maatwebsite)
- [ ] Dashboard analytics (Chart.js)
- [ ] Notifikasi in-app (Laravel Notifications + database)
- [ ] Audit trail (Spatie Activity Log)

### Phase 3 — Enhancement (Bulan 5–6)
- [ ] Integrasi BPJS (SEP & klaim)
- [ ] Portal pasien (riwayat kunjungan sendiri)
- [ ] Signature digital dokter
- [ ] Pencarian kode ICD-10 terintegrasi (Livewire search)
- [ ] Cetak label obat & kartu pasien

### Phase 4 — Scale (Bulan 7+)
- [ ] Multi-faskes / multi-klinik (multi-tenancy)
- [ ] WhatsApp notification (Fonnte / WATI API)
- [ ] Mobile app (PWA / React Native)
- [ ] AI Medical Assistant (opsional)
- [ ] Integrasi DICOM / PACS (radiologi)

---

*Dokumen ini bersifat living document dan akan diperbarui seiring perkembangan proyek.*
