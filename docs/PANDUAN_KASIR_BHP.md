# Panduan Kasir: Menambah BHP ke Tagihan Pasien

BHP (Bahan Habis Pakai) adalah barang yang terpakai saat layanan, misalnya kasa steril. BHP ditagihkan ke pasien dan stoknya berkurang otomatis setelah tagihan lunas.

## Menambah BHP
1. Buka menu **Billing & Kasir**, lalu tab **Tagihan Pasien**.
2. Pilih pasien sampai **Rincian Tagihan** tampil.
3. Klik tombol **Tambah Komponen** (tombol ini hanya ada selama tagihan belum bayar).
4. Pilih tab **BHP**.
5. Cari nama atau kode barang. Daftar menampilkan harga dan sisa stok. Barang yang stoknya habis tidak muncul.
6. Atur **Qty**, lalu klik **+ Tambah**. BHP langsung masuk ke Rincian Tagihan.

Jika BHP yang sama ditambahkan lagi, qty-nya digabung ke baris yang sudah ada. Tidak muncul baris ganda.

## Mengubah atau menghapus
- Ubah **Qty** langsung di Rincian Tagihan, seperti item lain. Qty harus bilangan bulat dan tidak boleh lebih besar dari stok.
- Hapus dengan tombol hapus di baris BHP, lalu konfirmasi "Hapus item ini dari tagihan?".

## Kapan stok berkurang
Stok berkurang **saat tagihan lunas**, bukan saat BHP ditambahkan. BHP yang dihapus sebelum pembayaran tidak mengubah stok.

## Jika stok tidak cukup saat Bayar
Pembayaran **ditolak seluruhnya** dan muncul pesan stok tidak mencukupi. Tagihan tetap **belum bayar** dan tidak ada uang yang tercatat. Kurangi qty BHP atau hubungi bagian stok, lalu ulangi pembayaran.

## Setelah lunas
BHP tidak bisa ditambah, diubah, atau dihapus lagi.

Jika tagihan dibatalkan (memerlukan password SuperAdmin), stok BHP yang sudah terpotong dikembalikan otomatis.

## Pertanyaan umum
**Kenapa BHP yang saya cari tidak muncul?**
Barang itu bukan BHP, tidak aktif, atau stoknya 0.

**Saya sudah klik Tambah, tapi stok barang belum berkurang. Apakah salah?**
Tidak. Stok baru berkurang setelah tagihan lunas.

**Qty tidak bisa disimpan.**
Gunakan bilangan bulat (tanpa koma) dan pastikan tidak melebihi stok yang tersedia.

**Saya salah menambah BHP setelah pembayaran.**
BHP tidak bisa diubah setelah lunas. Hubungi pengelola untuk membatalkan tagihan.
