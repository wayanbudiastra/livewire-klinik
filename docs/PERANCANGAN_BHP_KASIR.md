# Perancangan: Input BHP dari Panel "Tambah Komponen Tagihan" (Kasir)

Status: **RENCANA — menunggu persetujuan pemilik repo. Belum ada kode yang diubah.**
Menyentuh uang dan stok, jadi implementasi inti hanya dikerjakan Claude/pemilik repo (lihat `CLAUDE.md` bagian 2).

## 1. Kebutuhan
Kasir dapat menambahkan BHP (bahan habis pakai) ke tagihan pasien dari panel "Tambah Komponen Tagihan" (menu Billing Pasien), seperti menambah tindakan/lab.

## 2. Kondisi sekarang (hasil baca kode)
- Panel punya 4 tab: Prosedur, Peralatan, Laboratorium, Radiologi (`TagihanPasien::komponenList()` / `addKomponenItem()`).
- Tab **Peralatan** membaca tabel `peralatan_medis` (alat, bukan BHP) dengan harga **0** dan tidak mengurangi stok. Bukan tempat yang tepat untuk BHP.
- BHP adalah `barang.jenis = 'bahan_habis_pakai'` (punya stok, `harga_jual`, `harga_wna`, `harga_pokok`).
- Dokter sudah bisa mencatat alkes lewat modul Prosedur (`pemakaian_alkes`). Itu masuk tagihan sebagai item `alkes` (akun pendapatan 4-1300, harga dari `barang.harga_jual`), **tetapi tidak ada kode yang memotong stok** untuk `pemakaian_alkes`.
- **Risiko yang ditemukan:** `InvoiceService::createOrRefresh()` menghapus dan membangun ulang semua item bukan-`manual` setiap kali tagihan dibuka/dimuat ulang (`fetchTagihan()`). Item yang ditambah lewat panel (`ref_id = null`) tampaknya **hilang saat tagihan dimuat ulang**. Ini harus dipastikan dengan uji sebelum desain dipakai; BHP baru tidak boleh bernasib sama.
- Pemakaian BHP internal (`PemakaianBhpService`, tipe mutasi `keluar_bhp`) adalah dokumen non-tagihan; tidak dipakai di sini.

## 3. Rancangan yang direkomendasikan
1. **Tab baru "BHP"** di panel, berisi `barang` aktif berjenis `bahan_habis_pakai`, dengan pencarian nama/kode, harga (`harga_jual`, pakai `TarifResolver` untuk WNA), dan **sisa stok**. Barang stok 0 dinonaktifkan.
2. **Penyimpanan persisten:** tambah baris `pemakaian_alkes` (`kunjungan_id`, `barang_id`, `jumlah`) dengan kolom baru `sumber` (`dokter`/`kasir`), `dicatat_oleh`, dan `stok_dipotong_at` (nullable). Karena `InvoiceService::buildItems()` sudah menagih `pemakaian_alkes`, item tidak hilang saat tagihan dimuat ulang dan konsisten dengan alkes dokter.
3. **Ubah qty / hapus** (hanya saat tagihan `belum_bayar`): mengubah/menghapus baris `pemakaian_alkes` bersangkutan, lalu `recalcTotal`.
4. **Stok dipotong saat tagihan LUNAS**, di dalam `DB::transaction()` pembayaran yang sudah ada: untuk baris `sumber = 'kasir'` dan `stok_dipotong_at` null → `Barang::pastikanCukup()` (lock), `decrement`, catat `mutasi_stok` (`keluar_tindakan`, `referensi_tipe = 'pemakaian_alkes'`), isi `stok_dipotong_at`. Stok kurang → pembayaran ditolak dengan pesan jelas; bukan stok negatif.
5. **Jurnal:** pendapatan tetap lewat `BillingJurnalService` (4-1300). Tambah jurnal HPP: Dr HPP / Cr Persediaan (1-1300) sebesar `harga_pokok × qty`, memakai pola `RitelJurnalService::catatHpp`.
6. **Setelah lunas terkunci** (aturan yang sudah ada): tidak bisa tambah/ubah/hapus BHP. Koreksi lewat pembatalan billing yang sudah ada; **pembatalan harus mengembalikan stok** (mutasi balik) — perlu dicek di `BillingService::batalkanBilling`.
7. Hak akses: permission `billing.edit` yang sudah ada. Tidak ada permission baru.

## 4. Keputusan yang dibutuhkan dari pemilik repo
| # | Pertanyaan | Rekomendasi |
|---|---|---|
| 1 | Stok dipotong saat lunas (bukan saat ditambahkan)? | **Ya**, agar BHP yang dihapus kasir sebelum bayar tidak mengubah stok |
| 2 | Hanya `bahan_habis_pakai`, atau termasuk `alkes`? | Hanya BHP dulu; `alkes` bisa ditambah kemudian |
| 3 | Akun HPP untuk BHP | Pakai HPP yang ada (5-1100) kecuali COA punya akun HPP khusus BHP |
| 4 | Alkes input dokter yang tidak memotong stok | **Di luar lingkup**; catat sebagai temuan terpisah, putuskan nanti (mengubahnya akan memotong stok untuk transaksi baru) |
| 5 | Item panel lama (tindakan/lab/radiologi) yang hilang saat muat ulang | Buktikan dulu dengan uji; perbaikan terpisah |

## 5. Pembagian tugas

### Claude (area terlarang bagi agen lain: keuangan/stok)
| # | Tugas | Tingkat |
|---|---|---|
| C0 | Uji: apakah item panel hilang saat muat ulang tagihan | Kecil |
| C1 | Migration kolom `pemakaian_alkes` (`sumber`, `dicatat_oleh`, `stok_dipotong_at`) + model | Sedang |
| C2 | `TagihanPasien`: tab BHP, tambah/ubah qty/hapus, validasi stok | Sedang |
| C3 | Pemotongan stok + `mutasi_stok` dalam transaksi pembayaran | Tinggi |
| C4 | Jurnal HPP BHP + pengembalian stok saat billing dibatalkan | Tinggi |
| C5 | View panel (`tagihan-pasien.blade.php`) tab BHP | Sedang |
| C6 | Tes (stok, jurnal, lunas, batal, double-pay) | Sedang |

### DeepSeek/Qwen (hanya area yang boleh; branch `ai/<model>-<topik>`)
| # | Tugas | Tingkat | Catatan |
|---|---|---|---|
| D1 | `docs/PANDUAN_KASIR_BHP.md`: panduan singkat untuk kasir (cara tambah BHP, hapus, apa yang terjadi saat stok kurang). Dikerjakan setelah C2-C5 selesai | Mudah | Hanya dokumen |
| D2 | **Laporan baca-saja** "Pemakaian BHP via Kasir": daftar `pemakaian_alkes` `sumber='kasir'` per rentang tanggal (tanggal, pasien, barang, qty, kasir), dengan pagination dan filter tanggal maks 1 bulan. Letakkan di `app/Livewire/Laporan/` dan `resources/views/livewire/laporan/`, route di grup `laporan.kasir.*` memakai permission yang sudah ada (`laporan.kasir.view`). Tidak boleh mengubah data apa pun. Dikerjakan setelah C1 | Sedang | Review manual untuk `routes/web.php` |
| D3 | Tes fitur D2 (`DatabaseTransactions`, file baru di `tests/Feature/`) | Sedang | Jangan jalankan test `RefreshDatabase`; butuh DB `emr_test` |

Tugas D untuk UI kasir sengaja tidak ada: panel itu ada di area terlarang. Jika ingin mendelegasikannya, pemilik repo perlu membuat pengecualian eksplisit pada `.github/protected-paths.txt` untuk satu file partial baru.

## 6. Urutan
C0 → (keputusan #1-#5) → C1 → C2 + C5 → C3 → C4 → C6 → D2 → D3 → D1.
