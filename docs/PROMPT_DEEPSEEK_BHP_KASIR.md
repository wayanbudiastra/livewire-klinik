# Prompt untuk DeepSeek/Qwen: pekerjaan seputar fitur BHP Kasir

Cara pakai: salin **satu blok prompt** ke sesi agen yang BARU (satu tugas = satu sesi = satu branch). Urutan: **D2 → D3 → D1**. D2 dan D3 boleh dalam satu branch.

## Prasyarat (dilakukan pemilik repo, sekali)
1. `git pull` agar commit BHP kasir (`2aa2087`, `6bd3db7`, `bbd1c9b`) ada di salinan agen.
2. `git config core.hooksPath .githooks` di salinan yang dipakai agen.
3. **Khusus D3 (tes):** database `emr_test` harus ada dan berisi skema + role. Sebagai root MySQL:
   ```bash
   mysql -u root -p -e "CREATE DATABASE emr_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; GRANT ALL ON emr_test.* TO 'emr_user'@'localhost';"
   DB_DATABASE=emr_test php artisan migrate --force
   DB_DATABASE=emr_test php artisan db:seed --class=RolePermissionSeeder --force
   ```
   Perintah ini dijalankan pemilik repo, bukan agen.
4. Setelah agen selesai: `git diff --stat main...ai/<branch>` lalu `scripts/check-protected-paths.sh main`, baca diff, baru merge.

---

## Blok umum (letakkan di awal SETIAP prompt)

```
Kamu mengerjakan repo Laravel 12 + Livewire 3 "livewire-klinik" (aplikasi klinik yang SUDAH BERJALAN).
Baca dulu AGENTS.md dan CLAUDE.md di root repo, lalu patuhi.

ATURAN KERAS:
- DILARANG menyentuh keuangan dan stok. Daftar path terlarang: .github/protected-paths.txt
  (Kasir, Akuntansi, Inventory, Farmasi, model invoice/stok, migration, seeder, phpunit.xml, composer.*, deploy/update.sh, .env*, .github, CLAUDE.md, AGENTS.md).
  Jika task menuntut menyentuh path itu: BERHENTI dan laporkan. Jangan cari jalan lain.
- Kerja di branch: ai/deepseek-<topik>. Jangan commit ke main. Jangan push.
- Satu commit per perubahan logis, pesan Indonesia: feat(modul): ..., test(modul): ..., docs: ...
- Jalankan scripts/check-protected-paths.sh staged sebelum commit.
- Jangan menjalankan test RefreshDatabase. Jangan mengubah DB_DATABASE / phpunit.xml.
  Test hanya yang kamu buat/ubah: php artisan test tests/Feature/NamaTest.php (database emr_test).
- Jangan mengarang nama tabel/kolom/class. Cari di kode dulu; jika tidak ada, tanyakan.
- Jangan menambah package. Jangan mengubah perilaku fitur lain.
- Di akhir laporkan: file yang diubah, hasil test apa adanya, hal yang perlu dicek manual.
```

---

## D2 — Laporan baca-saja "BHP Kasir" (tingkat: sedang)

```
[sisipkan BLOK UMUM di sini]

TUGAS: buat laporan BACA-SAJA "BHP Kasir" sebagai tab baru di halaman Laporan Kasir.

Konteks fitur: kasir bisa menambah BHP (bahan habis pakai) ke tagihan pasien. Datanya disimpan di tabel
pemakaian_alkes dengan kolom: kunjungan_id, barang_id, jumlah, sumber ('dokter'|'kasir'), dicatat_oleh (user id),
stok_dipotong_at (null = tagihan belum lunas / stok belum dipotong), created_at.

Data laporan: baris pemakaian_alkes dengan sumber = 'kasir' pada periode terpilih (filter created_at).
Kolom tabel: Tanggal (created_at), No. RM, Nama Pasien, Nama Barang, Qty + satuan, Dicatat oleh (nama kasir),
Status ("Menunggu lunas" jika stok_dipotong_at null; "Stok dipotong dd/mm/yyyy" jika terisi).
Tambahkan ringkasan di atas tabel: jumlah baris dan total qty. Tidak menampilkan harga atau nominal uang.

Ikuti pola laporan yang sudah ada (CONTOH: tab "Cancel Bill"):
- app/Livewire/Laporan/Kasir/CancelBillReport.php  (extends BaseLaporanComponent, mountPeriode(), generate())
- resources/views/livewire/laporan/kasir/cancel-bill-report.blade.php
- tab di resources/views/laporan/kasir/index.blade.php
- method di app/Http/Controllers/Laporan/LaporanKasirController.php
- route di routes/web.php dalam grup laporan.kasir.* (middleware permission:laporan.kasir.view)
  -> route baru: GET /laporan/kasir/bhp, nama laporan.kasir.bhp.

File yang BOLEH kamu buat/ubah:
- BUAT app/Services/Laporan/BhpKasirLaporanService.php  (query; JANGAN mengubah KasirLaporanService.php)
- BUAT app/Livewire/Laporan/Kasir/BhpKasirReport.php
- BUAT resources/views/livewire/laporan/kasir/bhp-kasir-report.blade.php
- UBAH resources/views/laporan/kasir/index.blade.php (tambah tab)
- UBAH app/Http/Controllers/Laporan/LaporanKasirController.php (tambah method bhp)
- UBAH routes/web.php (HANYA menambah 1 route di grup laporan.kasir)
Dilarang mengubah: model apa pun (app/Models/*), migration, seeder, RolePermissionSeeder.

Aturan teknis:
- Baca-saja: tidak ada create/update/delete. Gunakan query builder atau Eloquent dengan relasi yang SUDAH ada
  (PemakaianAlkes::kunjungan, ::barang; Kunjungan::pasien). Nama kasir: join ke tabel users (jangan menambah relasi di model).
- Eager load / join agar tidak N+1. Wajib paginate (misal 25/halaman) atau batasi baris; jangan memuat seluruh tabel.
- Pembatasan data seperti CancelBillReport::userIdScope(): jika user TIDAK punya permission laporan.kasir.view_all,
  tampilkan hanya baris dengan dicatat_oleh = auth()->id().
- Pakai permission yang sudah ada (laporan.kasir.view). JANGAN membuat permission baru.
- Tanpa export PDF/Excel pada versi ini.
- Bahasa Indonesia untuk semua label.
- Jangan menjalankan migrate, seed, atau perintah apa pun terhadap database kerja.

Selesai bila: tab "BHP Kasir" muncul di Laporan Kasir, filter periode berfungsi, data sesuai, dan scope per-user berlaku.
```

---

## D3 — Test untuk laporan D2 (tingkat: sedang)

```
[sisipkan BLOK UMUM di sini]

TUGAS: tulis test fitur untuk laporan "BHP Kasir" (hasil tugas D2, branch yang sama).
Buat SATU file baru: tests/Feature/LaporanBhpKasirTest.php. Jangan mengubah file test lain.

Aturan test:
- Pakai trait Illuminate\Foundation\Testing\DatabaseTransactions (BUKAN RefreshDatabase).
- Contoh gaya + pembuatan fixture user/kunjungan/barang: tests/Feature/BhpKasirTest.php dan tests/Feature/RevisiSoapNoteTest.php.
- Livewire::test(App\Livewire\Laporan\Kasir\BhpKasirReport::class) untuk komponen; $this->get(route('laporan.kasir.bhp')) untuk halaman.
- Jalankan hanya: php artisan test tests/Feature/LaporanBhpKasirTest.php  (database emr_test, sudah disiapkan pemilik repo).
  Jika error "Unknown database emr_test", BERHENTI dan laporkan. Jangan membuat database sendiri, jangan ganti DB_DATABASE.

Kasus yang harus dicakup:
1. Halaman dapat dibuka user dengan permission laporan.kasir.view (role kasir) dan menampilkan tab "BHP Kasir".
2. User tanpa laporan.kasir.view ditolak (cek status response 403 atau redirect, sesuai perilaku route lain).
3. Hanya baris pemakaian_alkes sumber 'kasir' yang tampil; baris sumber 'dokter' tidak tampil.
4. Filter periode: baris di luar periode tidak tampil.
5. Status: stok_dipotong_at null -> "Menunggu lunas"; terisi -> teks "Stok dipotong".
6. Scope: kasir tanpa laporan.kasir.view_all hanya melihat baris yang dicatat_oleh dirinya; user dengan view_all melihat semua.
7. Laporan baca-saja: setelah laporan dibuka, jumlah baris pemakaian_alkes dan stok barang tidak berubah.

Laporkan hasil test apa adanya. Jangan klaim lulus jika belum dijalankan.
```

---

## D1 — Panduan singkat untuk kasir (tingkat: mudah)

```
[sisipkan BLOK UMUM di sini]

TUGAS: buat SATU file dokumentasi: docs/PANDUAN_KASIR_BHP.md. Hanya dokumen; jangan ubah kode apa pun.
Pembaca: kasir klinik (bukan programmer). Bahasa Indonesia sederhana, ringkas (maks ~1 halaman), langkah bernomor.

Isi (gunakan HANYA fakta di bawah; jangan menambah fitur yang tidak disebut):
1. Apa itu BHP: bahan habis pakai (mis. kasa steril) yang dipakai saat layanan dan ditagihkan ke pasien.
2. Cara menambah: menu Billing & Kasir -> Tagihan Pasien -> pilih pasien -> tombol "Tambah Komponen" ->
   tab "BHP" -> cari nama/kode -> atur Qty -> klik "+ Tambah". Daftar menampilkan harga dan sisa stok.
   (Periksa label menu & tombol sebenarnya di resources/views/layouts/app.blade.php dan
   resources/views/livewire/kasir/tagihan-pasien.blade.php; sesuaikan tulisan bila berbeda.)
3. BHP yang sama ditambah lagi -> qty ditambahkan ke baris yang ada.
4. Mengubah Qty / menghapus: lewat rincian tagihan seperti item lain. Qty harus bilangan bulat dan tidak boleh melebihi stok.
5. Stok barang BERKURANG saat tagihan LUNAS, bukan saat BHP ditambahkan.
6. Jika stok ternyata tidak cukup saat menekan Bayar: pembayaran ditolak seluruhnya dan muncul pesan stok tidak mencukupi;
   tagihan tetap "belum bayar". Kurangi qty atau hubungi bagian stok.
7. Setelah LUNAS: BHP tidak bisa ditambah, diubah, atau dihapus.
8. Jika billing dibatalkan (butuh password SuperAdmin), stok BHP dikembalikan otomatis.
9. Bagian "Pertanyaan umum" 3-4 butir berdasarkan poin di atas.
Jangan mencantumkan kode akun, jurnal, atau istilah teknis database.
```
