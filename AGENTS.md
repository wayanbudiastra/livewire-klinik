# AGENTS.md — aturan untuk agen AI (DeepSeek, Qwen, dll)

Repo ini adalah aplikasi klinik yang SUDAH BERJALAN (data pasien, uang, stok). Tugasmu: mengerjakan perubahan kecil dan aman. Baca file ini sampai habis, lalu `CLAUDE.md` untuk konteks lengkap.

## Aturan keras (tidak ada pengecualian)

1. **DILARANG menyentuh keuangan dan stok.** Daftar resmi path terlarang ada di `.github/protected-paths.txt`. Intinya: Kasir, Akuntansi, Asuransi, Harga, Inventory, Farmasi, semua model invoice/pembayaran/stok, `Prosedur`, `ResepObat`, `Penunjang`, migration, seeder, `phpunit.xml`, `composer.*`, `deploy.sh`, `update.sh`, `.env*`, `.github/`, `CLAUDE.md`, `AGENTS.md`.
2. Jika task menuntut menyentuh path terlarang, atau kamu ragu: **BERHENTI dan laporkan**. Jangan mengerjakan "sebagian", jangan mencari jalan lain, jangan mengubah daftar terlarang.
3. **JANGAN menjalankan test yang memakai `RefreshDatabase` terhadap database apa pun selain `emr_test`.** `RefreshDatabase` menghapus seluruh isi database. Jangan mengubah `DB_DATABASE` atau `phpunit.xml`. Jika `emr_test` tidak ada, laporkan; jangan membuatnya sendiri.
4. **JANGAN** menjalankan `php artisan migrate:fresh`, `migrate:rollback`, `db:wipe`, `db:seed`, atau SQL `DROP`/`TRUNCATE`/`DELETE` pada database kerja.
5. **JANGAN** mengubah, menghapus, atau mengganti nama migration yang sudah ada; jangan menambah package Composer/NPM.
6. **JANGAN** push, `--force`, atau menulis ulang riwayat git. Jangan commit `.env`, `vendor/`, `node_modules/`.
7. Jangan mengubah perilaku fitur yang sudah berjalan kecuali diminta secara eksplisit. Jangan menghapus fitur, permission, atau test.
8. Jangan mengarang nama tabel, kolom, class, atau method. Cari dulu di kode; jika tidak ada, tanyakan.

## Alur kerja

1. Kerjakan di branch **`ai/<nama-model>-<topik>`** (contoh `ai/deepseek-label-pasien`). Jangan commit langsung ke `main`.
2. Satu commit per perubahan logis, pesan Indonesia: `feat(modul): ...`, `fix(modul): ...`.
3. Sebelum commit, jalankan `scripts/check-protected-paths.sh staged`. Hook pre-commit di branch `ai/*` menjalankannya otomatis dan akan menolak commit yang menyentuh path terlarang.
4. Buka Pull Request; CI akan menolak PR `ai/*` yang menyentuh path terlarang, dan pemilik repo mereview setiap PR.
5. Di akhir, laporkan: file yang diubah, test yang dijalankan beserta hasil apa adanya (jangan klaim lulus jika belum dijalankan), dan hal yang perlu dicek manual di browser.

## Boleh dikerjakan

Tampilan (blade/Tailwind), CRUD data master non-uang (pasien, dokter, poli, jadwal, ICD-10, sumber informasi, tujuan rujukan), laporan baca-saja, perbaikan teks/label/layout, test untuk area tersebut, dokumentasi.

## Data medis (SOAP, asesmen, surat)

Hanya dengan rencana yang disetujui pemilik repo terlebih dahulu. SOAP final tidak boleh diubah selain lewat mekanisme Revisi SOAP yang sudah ada.

## Test aman

Tes baru memakai `DatabaseTransactions` (contoh: `tests/Feature/RevisiSoapNoteTest.php`). Jalankan hanya file test yang kamu buat atau ubah: `php artisan test tests/Feature/NamaTest.php`. Jangan menjalankan seluruh suite.
