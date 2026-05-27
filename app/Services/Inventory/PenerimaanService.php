<?php

namespace App\Services\Inventory;

use App\Models\Barang;
use App\Models\GoodsReceipt;
use App\Models\GrItem;
use App\Models\MutasiStok;
use App\Models\PoItem;
use App\Models\PurchaseOrder;
use App\Models\SupplierBarang;
use App\Services\Akuntansi\InventoriJurnalService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PenerimaanService
{
    public function buatGr(array $data): GoodsReceipt
    {
        return DB::transaction(function () use ($data) {
            $items = $data['items'] ?? [];
            unset($data['items']);

            $totalNilai = collect($items)->sum(fn ($i) =>
                ($i['jumlah_terima'] ?? 0)
                * ($i['harga_satuan'] ?? 0)
                * (1 - ($i['diskon_persen'] ?? 0) / 100)
            );

            $gr = GoodsReceipt::create(array_merge($data, [
                'nomor_gr'    => GoodsReceipt::generateNomorGr(),
                'total_nilai' => $totalNilai,
                'status'      => 'draft',
            ]));

            foreach ($items as $item) {
                $subtotal = ($item['jumlah_terima'] ?? 0)
                          * ($item['harga_satuan'] ?? 0)
                          * (1 - ($item['diskon_persen'] ?? 0) / 100);

                $gr->items()->create(array_merge($item, ['subtotal' => $subtotal]));
            }

            activity('inventory')
                ->performedOn($gr)
                ->causedBy(auth()->user())
                ->withProperties(['nomor_gr' => $gr->nomor_gr])
                ->log('Goods Receipt dibuat (draft)');

            return $gr->load('items.barang');
        });
    }

    /**
     * Verifikasi GR — trigger update stok & HPR (Moving Average)
     */
    public function verifikasiGr(GoodsReceipt $gr, int $userId): GoodsReceipt
    {
        if ($gr->status !== 'draft') {
            throw ValidationException::withMessages([
                'status' => 'GR sudah diverifikasi atau dibatalkan.',
            ]);
        }

        return DB::transaction(function () use ($gr, $userId) {
            $gr->load('items.barang');

            foreach ($gr->items as $grItem) {
                $this->prosesItemGr($grItem, $gr->supplier_id, $userId);
            }

            // Update status PO jika ada
            if ($gr->purchase_order_id) {
                $this->updateStatusPo($gr->purchase_order_id);
            }

            $gr->update([
                'status'        => 'diverifikasi',
                'diterima_oleh' => $userId,
            ]);

            // Hook akuntansi — catat jurnal pending pembelian
            app(InventoriJurnalService::class)->catatPembelian($gr->fresh(['items.barang']));

            activity('inventory')
                ->performedOn($gr)
                ->causedBy(auth()->user())
                ->log('GR diverifikasi — stok & HPR diperbarui');

            return $gr->fresh(['items.barang']);
        });
    }

    private function prosesItemGr(GrItem $grItem, int $supplierId, int $userId): void
    {
        // Lock row untuk mencegah race condition
        $barang = Barang::lockForUpdate()->findOrFail($grItem->barang_id);

        $stokLama    = $barang->stok;
        $hprLama     = (float) $barang->harga_pokok;
        $jumlahMasuk = $grItem->jumlah_terima;

        // Harga beli efektif setelah diskon
        $hargaBeli = $grItem->harga_satuan * (1 - $grItem->diskon_persen / 100);

        // ── Kalkulasi HPR (Moving Average) ──────────────────────
        if ($stokLama === 0) {
            $hprBaru = $hargaBeli;
        } else {
            $hprBaru = (($stokLama * $hprLama) + ($jumlahMasuk * $hargaBeli))
                     / ($stokLama + $jumlahMasuk);
        }

        $hprBaru  = round($hprBaru, 2);
        $stokBaru = $stokLama + $jumlahMasuk;

        // ── Simpan snapshot HPR ke gr_item ───────────────────────
        $grItem->update([
            'hpr_sebelum' => $hprLama,
            'hpr_sesudah' => $hprBaru,
        ]);

        // ── Update stok & HPR di tabel barang ────────────────────
        $barang->update([
            'stok'        => $stokBaru,
            'harga_pokok' => $hprBaru,
        ]);

        // ── Catat mutasi stok ─────────────────────────────────────
        MutasiStok::create([
            'barang_id'      => $barang->id,
            'user_id'        => $userId,
            'tipe'           => 'masuk_pembelian',
            'jumlah'         => $jumlahMasuk,
            'stok_sebelum'   => $stokLama,
            'stok_sesudah'   => $stokBaru,
            'hpr_sebelum'    => $hprLama,
            'hpr_sesudah'    => $hprBaru,
            'referensi_tipe' => 'goods_receipt',
            'referensi_id'   => $grItem->goods_receipt_id,
            'keterangan'     => "GR: {$grItem->goodsReceipt->nomor_gr}"
                              . ($grItem->nomor_batch ? " | Batch: {$grItem->nomor_batch}" : ''),
        ]);

        // ── Update jumlah_diterima di po_item ─────────────────────
        if ($grItem->po_item_id) {
            PoItem::where('id', $grItem->po_item_id)
                  ->increment('jumlah_diterima', $jumlahMasuk);
        }

        // ── Update harga_terakhir di supplier_barang ──────────────
        SupplierBarang::where('barang_id', $barang->id)
            ->where('supplier_id', $supplierId)
            ->update(['harga_terakhir' => $grItem->harga_satuan]);
    }

    public function batalkanGr(GoodsReceipt $gr): GoodsReceipt
    {
        if ($gr->status !== 'draft') {
            throw ValidationException::withMessages([
                'status' => 'Hanya GR berstatus draft yang bisa dibatalkan.',
            ]);
        }

        $gr->update(['status' => 'dibatalkan']);

        activity('inventory')
            ->performedOn($gr)
            ->causedBy(auth()->user())
            ->log('GR dibatalkan');

        return $gr;
    }

    private function updateStatusPo(int $poId): void
    {
        $po = PurchaseOrder::with('items')->find($poId);
        if (! $po) return;

        $totalPesan    = $po->items->sum('jumlah_pesan');
        $totalDiterima = $po->items->sum('jumlah_diterima');

        if ($totalDiterima >= $totalPesan) {
            $po->update(['status' => 'selesai']);
        } elseif ($totalDiterima > 0) {
            $po->update(['status' => 'sebagian']);
        }
    }
}
