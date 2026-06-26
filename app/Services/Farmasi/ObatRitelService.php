<?php

namespace App\Services\Farmasi;

use App\Models\{Barang, MutasiStok, SesiKas, TransaksiRitel, TransaksiRitelItem};
use App\Services\Akuntansi\RitelJurnalService;
use Illuminate\Support\Facades\DB;

class ObatRitelService
{
    public function buatDraft(array $data, int $apotekerId): TransaksiRitel
    {
        return TransaksiRitel::create([
            'nomor_ritel'  => TransaksiRitel::generateNomor(),
            'nama_pembeli' => $data['nama_pembeli'],
            'nomor_hp'     => $data['nomor_hp'] ?? null,
            'pasien_id'    => $data['pasien_id'] ?? null,
            'catatan'      => $data['catatan'] ?? null,
            'apoteker_id'  => $apotekerId,
            'status'       => 'draft',
            'total_harga'  => 0,
        ]);
    }

    public function updateItem(TransaksiRitel $tr, array $items): TransaksiRitel
    {
        if ($tr->status !== 'draft') {
            throw new \DomainException('Hanya transaksi berstatus draft yang bisa diedit.');
        }

        DB::transaction(function () use ($tr, $items) {
            $tr->items()->delete();

            $total = 0;
            foreach ($items as $item) {
                $jumlah    = (int) $item['jumlah'];
                $harga     = (float) $item['harga_satuan'];
                $subtotal  = $jumlah * $harga;
                $total    += $subtotal;

                TransaksiRitelItem::create([
                    'transaksi_ritel_id' => $tr->id,
                    'barang_id'          => $item['barang_id'],
                    'jumlah'             => $jumlah,
                    'harga_satuan'       => $harga,
                    'subtotal'           => $subtotal,
                    'catatan'            => $item['catatan'] ?? null,
                ]);
            }

            $tr->update([
                'total_harga' => $total,
                'nama_pembeli' => $item['nama_pembeli'] ?? $tr->nama_pembeli,
            ]);
        });

        return $tr->fresh();
    }

    public function simpanDraft(TransaksiRitel $tr, array $header, array $items): TransaksiRitel
    {
        if ($tr->status !== 'draft') {
            throw new \DomainException('Hanya transaksi berstatus draft yang bisa diedit.');
        }

        DB::transaction(function () use ($tr, $header, $items) {
            $tr->update([
                'nama_pembeli' => $header['nama_pembeli'],
                'nomor_hp'     => $header['nomor_hp'] ?? null,
                'pasien_id'    => $header['pasien_id'] ?? null,
                'catatan'      => $header['catatan']   ?? null,
            ]);

            $tr->items()->delete();
            $total = 0;

            foreach ($items as $item) {
                $jumlah   = (int) $item['jumlah'];
                $harga    = (float) $item['harga_satuan'];
                $subtotal = $jumlah * $harga;
                $total   += $subtotal;

                TransaksiRitelItem::create([
                    'transaksi_ritel_id' => $tr->id,
                    'barang_id'          => $item['barang_id'],
                    'jumlah'             => $jumlah,
                    'harga_satuan'       => $harga,
                    'subtotal'           => $subtotal,
                    'catatan'            => $item['catatan'] ?? null,
                ]);
            }

            $tr->update(['total_harga' => $total]);
        });

        return $tr->fresh();
    }

    public function submitKeKasir(TransaksiRitel $tr): TransaksiRitel
    {
        if ($tr->status !== 'draft') {
            throw new \DomainException('Hanya transaksi draft yang bisa di-submit.');
        }
        if ($tr->items()->count() === 0) {
            throw new \DomainException('Tidak bisa submit transaksi tanpa item.');
        }

        $tr->update(['status' => 'menunggu_kasir']);
        return $tr->fresh();
    }

    public function prosesBayar(TransaksiRitel $tr, array $bayarData, int $kasirId): TransaksiRitel
    {
        if ($tr->status !== 'menunggu_kasir') {
            throw new \DomainException('Transaksi tidak dalam status menunggu kasir.');
        }

        $totalBayar = (float) $bayarData['total_bayar'];
        if ($totalBayar < (float) $tr->total_harga) {
            throw new \DomainException('Jumlah bayar kurang dari total harga.');
        }

        // Kasir wajib memiliki sesi kas aktif agar transaksi tercatat di laporan
        $sesiKas = SesiKas::where('user_id', $kasirId)
            ->where('status', 'buka')
            ->whereDate('tanggal', today())
            ->first();

        if (!$sesiKas) {
            throw new \DomainException('Kasir belum membuka sesi kas hari ini. Buka sesi kas terlebih dahulu sebelum memproses pembayaran ritel.');
        }

        return DB::transaction(function () use ($tr, $bayarData, $kasirId, $sesiKas, $totalBayar) {
            $tr->update([
                'status'       => 'dibayar',
                'kasir_id'     => $kasirId,
                'sesi_kas_id'  => $sesiKas?->id,
                'metode_bayar' => $bayarData['metode_bayar'],
                'total_bayar'  => $totalBayar,
                'kembalian'    => $bayarData['metode_bayar'] === 'tunai'
                                  ? max(0, $totalBayar - (float) $tr->total_harga)
                                  : null,
                'dibayar_at'   => now(),
            ]);

            $tr = $tr->fresh();
            app(RitelJurnalService::class)->catatPenjualan($tr);

            return $tr;
        });
    }

    public function serahkanObat(TransaksiRitel $tr, int $userId): TransaksiRitel
    {
        if ($tr->status !== 'dibayar') {
            throw new \DomainException('Hanya transaksi yang sudah dibayar yang bisa diserahkan.');
        }

        DB::transaction(function () use ($tr, $userId) {
            $this->potongStok($tr, $userId);
            $tr->update([
                'status'        => 'selesai',
                'diserahkan_at' => now(),
            ]);

            app(RitelJurnalService::class)->catatHpp($tr->fresh(['items.barang']));
        });

        return $tr->fresh();
    }

    public function batalkan(TransaksiRitel $tr): TransaksiRitel
    {
        if (!$tr->bisaDibatalkan()) {
            throw new \DomainException('Transaksi tidak bisa dibatalkan dari status "' . $tr->status_label . '".');
        }

        $tr->update(['status' => 'dibatalkan']);
        return $tr->fresh();
    }

    private function potongStok(TransaksiRitel $tr, int $userId): void
    {
        $tr->load('items');

        foreach ($tr->items as $item) {
            $barang = Barang::pastikanCukup($item->barang_id, $item->jumlah);

            $hpr         = (float) $barang->harga_pokok; // HPR tidak berubah saat barang keluar
            $stokSebelum = $barang->stok;
            $barang->decrement('stok', $item->jumlah);
            $stokSesudah = $stokSebelum - $item->jumlah;

            MutasiStok::create([
                'barang_id'      => $item->barang_id,
                'user_id'        => $userId,
                'tipe'           => 'keluar_ritel',
                'jumlah'         => $item->jumlah,
                'stok_sebelum'   => $stokSebelum,
                'stok_sesudah'   => $stokSesudah,
                'hpr_sebelum'    => $hpr,
                'hpr_sesudah'    => $hpr,
                'referensi_tipe' => 'transaksi_ritel',
                'referensi_id'   => $tr->id,
                'keterangan'     => 'Ritel: ' . $tr->nomor_ritel,
            ]);
        }
    }
}
