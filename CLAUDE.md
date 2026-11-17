# Livewire Klinik - Development Instructions

Instruksi ini dibaca semua model/agen yang mengerjakan repo ini (Claude dan DeepSeek). Bagian 2 menentukan **siapa boleh menyentuh apa**; baca bagian itu lebih dulu.

## 1. Project Overview

Livewire Klinik adalah aplikasi Rekam Medis Elektronik (RME) untuk klinik praktek mandiri dengan SDM terbatas (1-3 admin, 1-2 dokter). Fokus: registrasi pasien cepat (< 5 menit) dan pencatatan medis yang rapi.

- Laravel 12 + Livewire 3 (full-stack), PHP 8.3, TailwindCSS (Vite), MySQL 8
- Permission: spatie/laravel-permission; audit: spatie/laravel-activitylog
- Integrasi SATUSEHAT: **baru lookup IHS** (`app/Services/SatuSehat/SatuSehatIhsService.php`). Pengiriman Encounter/Condition belum ada (target, bukan kondisi sekarang).
- Produksi: VPS 2 vCPU / 2GB RAM (dipakai bersama project lain). Tanpa Docker; deploy dengan `deploy.sh` / `update.sh`.
- UI, komentar kode, dan pesan commit berbahasa Indonesia.

Alur inti: Registrasi → Antrian → Panggil → Pemeriksaan (SOAP, resep, tindakan, penunjang) → Billing (kasir) → Pembayaran → Selesai. Susulan lab/obat setelah bill lunas lewat menu kasir "Order Susulan" (lihat `docs/PERANCANGAN_COMBINE_BILL_RITEL.md`).

## 2. Pembagian Wewenang (WAJIB)

### Area TERLARANG untuk DeepSeek: keuangan dan stok
DeepSeek **tidak boleh membuat, mengubah, atau menghapus** file di area berikut. Hanya Claude (atau pemilik repo) yang boleh.

- **Keuangan:** `app/Services/Akuntansi/`, `app/Services/Kasir/`, `app/Services/Asuransi/`, `app/Services/Harga/`, `app/Services/InvoiceService.php`, `app/Livewire/Kasir/`, `app/Livewire/Akuntansi/`, `app/Livewire/Keuangan/`, `app/Livewire/Harga/`, model Invoice, InvoiceItem, Pembayaran*, Deposit*, TransaksiDeposit, Piutang*, Penagihan*, Sesi/ShiftKasir, SharingFee*, Akuntansi*, Proposal*Harga, Konfigurasi*Harga*.
- **Stok & farmasi:** `app/Services/Inventory/`, `app/Services/Farmasi/`, `app/Livewire/Inventory/`, `app/Livewire/Farmasi/`, model Barang, BatchExpired, StokGudang, MutasiStok, StokOpname*, AdjustmentStok, GoodsReceipt, PurchaseOrder, ReturGr*, ReturResep*, TransaksiRitel*, PemakaianBhp*.
- **Titik yang diam-diam memengaruhi tagihan/stok** (dalam modul Pemeriksaan): `app/Livewire/Pemeriksaan/Prosedur.php`, `ResepObat.php`, `Penunjang.php`.
- **Migration, seeder, dan permission** yang menyentuh tabel di atas, serta `RolePermissionSeeder` untuk permission kasir/inventory/billing/ritel/adjustment.
- Sebelum mengedit file apa pun, cek apakah ia memanggil atau dipanggil kode di area terlarang (`grep` nama class/tabel). Kalau ya, anggap terlarang.

**Jika task menuntut menyentuh area terlarang: BERHENTI, jangan menebak, jangan "sedikit saja".** Laporkan: file yang perlu diubah dan alasannya, lalu serahkan ke Claude.

### Area yang boleh dikerjakan DeepSeek
Tampilan (blade/Tailwind) dan CRUD data master yang tidak berhubungan dengan uang/stok: pasien, dokter, poli, jadwal, ICD-10, sumber informasi, tujuan rujukan; laporan baca-saja (registrasi, pemeriksaan); perbaikan teks/label/layout; test untuk area tersebut; dokumentasi.

### Khusus data medis (SOAP, asesmen, surat)
Boleh dikerjakan **hanya jika ada rencana yang disetujui**. Lihat bagian 5.

## 3. Sebelum Mengubah Kode

1. Baca file ini, lalu struktur terkait (`app/Livewire/`, `app/Services/`, `app/Models/`, `database/migrations/`, `routes/web.php`).
2. Baca seluruh file task: model, migration, service, komponen Livewire, view, dan test yang ada.
3. Jangan mengasumsikan nama tabel, kolom, atau fungsi. Periksa; jika tidak ketemu, laporkan dan tanyakan.
4. Ikuti pola modul sejenis. Cari dulu di `app/Services/`, `app/Helpers/`, dan komponen lain sebelum membuat yang baru.

### Tingkat persetujuan
- **Perubahan kecil** (label, layout, validasi, bug lokal di area boleh): langsung kerjakan.
- **Menyentuh data medis, atau lintas modul**: beri dulu ringkasan, file yang akan diubah, rencana bertahap, dan risiko; tunggu persetujuan.
- **Area terlarang (bagian 2)**: berhenti dan laporkan.

## 4. Architecture & Coding Rules

- Logika bisnis di `app/Services/`; komponen Livewire hanya state UI, validasi, dan memanggil service.
- Pagination memakai `WithPagination` bawaan Livewire. Tidak ada trait `WithSearch`/`WithFilter`; jangan mengarangnya.
- Route mengikuti prefix yang ada: `pasien.*`, `kunjungan.*`, `pemeriksaan.*`, `farmasi.*`, `kasir.*`, `inventory.*`, `laporan.*`, `pengaturan.*`. Setiap route dijaga middleware `permission:...`.
- Polling real-time tetap 5-10 detik (server 2GB RAM). `dashboard` memakai 30 detik; jangan dipercepat.
- Validasi semua input di sisi server. Setiap aksi tulis diberi `authorize()` / permission; jangan menonaktifkan CSRF, validasi, atau authorization.
- Eager loading (`with()`) untuk mencegah N+1; jangan memuat seluruh tabel ke memori.
- **Migration:** jangan edit migration lama; buat yang baru, dengan `down()` yang benar.
- **Permission baru:** daftarkan di `database/seeders/RolePermissionSeeder.php`, berikan ke role yang tepat, lalu jalankan seeder (`php artisan db:seed --class=RolePermissionSeeder`). Tanpa itu tombol/menu tidak muncul. `update.sh` menjalankan seeder ini di server.
- Perilaku fitur yang sudah ada hanya diubah jika diminta, dan harus ada test-nya.
- Tidak menambah package Composer/NPM tanpa persetujuan.

## 5. Medical Data Integrity

Rekam medis adalah dokumen legal.

- Data pasien dan rekam medis tidak di-hard-delete. Pakai soft delete atau status pembatalan (saat ini baru sebagian kecil model memakai `SoftDeletes`; model baru wajib mengikuti aturan ini).
- **SOAP yang sudah final tidak diubah langsung.** Mekanismenya **Revisi SOAP**: hanya role dengan `soap.revisi` (dokter), alasan wajib (min 5 karakter), `is_final` tidak berubah, tercatat di activity log (sebelum/sesudah). Jangan membuat jalur edit lain.
- **Setelah bill lunas:** isi SOAP tetap boleh direvisi, tetapi lab, radiologi, obat, alkes/BHP, dan tindakan terkunci (`Invoice::kunjunganSudahLunas()`). Jangan melonggarkannya.
- Setiap create/update/pembatalan data medis dicatat ke audit log (spatie activitylog): user, waktu, sebelum, sesudah.
- Pasien unik: `nik` (nullable-unique) atau `no_paspor` untuk WNA. Cegah duplikasi.
- Diagnosa memakai kode ICD-10 dari master data.
- Data pasien tidak boleh ditulis utuh ke log aplikasi, pesan error, atau response API.

## 6. Keuangan, Akuntansi, dan Stok (aturan untuk Claude/pemilik repo)

DeepSeek tidak mengerjakan area ini; aturan dicatat agar tidak dilanggar ketika menyentuhnya.

- Transaksi uang di `DB::transaction()`, nomor referensi unik, cegah double posting.
- Total tagihan dihitung dari master tarif (`TarifResolver`), bukan dari input user. Tagihan lunas tidak diubah; gunakan pembatalan/refund tercatat.
- Setiap transaksi dijurnal lewat `JurnalService::catat` (→ `JurnalPending`); sharing fee lewat `SharingFeeService`.
- Perubahan stok wajib tercatat di `mutasi_stok` (via `KartuStokService`/service inventory), stok tidak boleh negatif, FEFO untuk batch. Pemakaian medis memotong stok dan menambah tagihan dalam satu transaksi.
- Billing asal dibayar tunai; reimburse asuransi adalah urusan pasien. Order susulan dan Tagihan Gabungan: lihat `docs/PERANCANGAN_COMBINE_BILL_RITEL.md`.

## 7. SATUSEHAT

- Kredensial hanya dari `.env` (atau `config_satusehat`), tidak di-hardcode. Token OAuth di-cache sampai habis masa berlakunya.
- Pengiriman resource (saat nanti dibuat) berjalan di queue/job, bukan di request user. Simpan status, ID resource, dan pesan error. Gunakan sandbox saat development.

## 8. Security & Privacy

- Data pasien rahasia; akses mengikuti role.
- Upload dokumen medis di storage privat, diakses lewat route yang memeriksa izin.
- Jangan commit `.env`, `vendor/`, `node_modules/`; jangan menulis kredensial ke file apa pun.

## 9. Testing (PENTING: jangan merusak database)

```bash
php artisan test                         # seluruh test
php artisan test --filter=NamaTest       # test tertentu
./vendor/bin/pint --test                 # cek format
php artisan migrate --pretend            # lihat SQL migration
```

- `phpunit.xml` mengarah ke database **`emr_test`** (MySQL), bukan `emr_db`. Database ini harus ada; jika belum, minta pemilik repo membuatnya.
- Banyak test memakai **`RefreshDatabase`, yang MENGHAPUS seluruh isi database**. **Jangan pernah** menjalankan test dengan `DB_DATABASE=emr_db` atau mengubah `phpunit.xml` agar menunjuk database kerja.
- Test baru memakai `DatabaseTransactions` (rollback otomatis), lihat `tests/Feature/RevisiSoapNoteTest.php` sebagai contoh.
- `Livewire::test()->call()` tidak melempar `AuthorizationException`; cek penolakan dari efek sampingnya (state/DB tidak berubah), bukan `expectException`.
- Tambahkan test untuk logika service dan hak akses. Laporkan hasil test apa adanya; **jangan mengklaim test berhasil jika belum dijalankan.**

## 10. Git

- Satu commit per perubahan logis, pesan Indonesia, format `feat(modul): ...` / `fix(...)`, `docs:`, `test:`.
- Commit setiap perubahan yang selesai. **Push hanya setelah pemilik repo setuju.**
- Jangan `--force`, jangan menulis ulang riwayat.

## 11. Dokumentasi

- `docs/PERANCANGAN_COMBINE_BILL_RITEL.md` adalah spesifikasi yang **aktif** (lab susulan, order susulan, tagihan gabungan).
- Dokumen lain di `docs/` adalah laporan sesi lama (COMPLETION_REPORT, FINAL_STATUS, SESSION_*, dll). Anggap **riwayat**, bukan spesifikasi; kode dan test lebih dipercaya.

## 12. Sebelum Selesai

- Jalankan test terkait (aman, lihat bagian 9) dan periksa regresi di modul yang terhubung.
- Periksa migration baru (index, foreign key, `down()`).
- Laporkan: file yang diubah, hasil test apa adanya, hal yang belum selesai, dan yang perlu dicek manual di browser.
- Jika selama mengerjakan ternyata perlu menyentuh area terlarang: berhenti dan laporkan.
