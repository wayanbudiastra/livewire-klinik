# Perancangan: Input Obat dari Panel "Tambah Komponen Tagihan" (Kasir)

Status: **SUDAH DIIMPLEMENTASI** (mengikuti `docs/PERANCANGAN_BHP_KASIR.md`).

## Keputusan pemilik repo
1. Konteks: obat ditambahkan ke **bill pasien yang sedang dibuka** (belum lunas), lewat tab "Obat" di panel.
2. Mekanisme **sama seperti BHP**: stok dipotong saat lunas, tanpa konfirmasi apoteker.
3. Hanya **obat aktif dengan stok tersedia** (barang jenis `obat`).
4. **Racikan tidak termasuk.**
5. **Tanpa sharing fee** untuk obat input kasir.

## Alur yang sudah ada (hasil analisis)
- **Resep dokter:** apoteker konfirmasi → stok dipotong (`keluar_resep`) → ditagih sebagai `obat`/`racikan`. Kasir tidak bisa tambah/hapus/ubah qty. Tidak ada jurnal HPP (gap PRD §9.5, tidak diubah).
- **Ritel:** draft apoteker → bayar kasir → serah apoteker (stok `keluar_ritel` + HPP).
- **Order Susulan:** obat/lab untuk bill yang sudah lunas (≤14 hari).
- Obat input kasir ini mengisi celah: menambah obat ke bill yang belum lunas tanpa resep.

## Implementasi
- `BhpKasirService` dipakai ulang dengan parameter jenis (`bahan_habis_pakai` | `obat`). Penyimpanan tetap di `pemakaian_alkes` (`sumber='kasir'`), ditagih sebagai item `alkes` (pendapatan 4-1300, harga dari `barang.harga_jual`/`harga_wna`).
- Stok dipotong saat lunas di dalam transaksi pembayaran; mutasi `keluar_ritel` untuk obat (BHP tetap `keluar_tindakan`). Stok kurang → pembayaran ditolak utuh.
- Jurnal HPP 5-1100/1-1300 (`harga_pokok × qty`); pembatalan billing mengembalikan stok dan membalik jurnal.
- Jenis barang divalidasi sesuai tab: obat tidak bisa masuk lewat jalur BHP dan sebaliknya.
- Laporan "BHP & Obat Kasir" menampilkan kolom Jenis.

## Hal yang perlu diketahui
- Apoteker **tidak melihat** obat ini (tidak ada konfirmasi/penyerahan farmasi). Wajar untuk obat bebas; kasir bertanggung jawab memilih obat yang sesuai.
- Kasir bisa menambah obat yang sama dengan yang sudah diresepkan dokter; kedua baris tetap ada, jadi rincian perlu diperiksa sebelum Bayar.
- Item di tagihan berlabel jenis `alkes` (bukan `obat`), sehingga tombol hapus dan ubah qty tetap tersedia bagi kasir selama belum lunas.
