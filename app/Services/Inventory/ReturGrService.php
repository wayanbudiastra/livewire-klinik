<?php

namespace App\Services\Inventory;

use App\Models\Barang;
use App\Models\GoodsReceipt;
use App\Models\GrItem;
use App\Models\MutasiStok;
use App\Models\PoItem;
use App\Models\ReturGr;
use App\Models\ReturGrItem;
use App\Services\Akuntansi\InventoriJurnalService;
use Illuminate\Support\Facades\DB;

class ReturGrService
{
    /** Sisa kuantitas dari satu gr_item yang masih bisa diretur (belum pernah diretur sebelumnya, kecuali yang dibatalkan). */
    public function hitungSisaBisaDiretur(GrItem $item): int
    {
        $sudahDiretur = ReturGrItem::where('gr_item_id', $item->id)
            ->whereHas('returGr', fn ($q) => $q->where('status', '!=', 'dibatalkan'))
            ->sum('jumlah_retur');

        return max(0, $item->jumlah_terima - $sudahDiretur);
    }

    /**
     * @param array{goods_receipt_id:int,alasan:string,catatan:?string,
     *              items: array<int,array{gr_item_id:int,barang_id:int,jumlah_retur:int,harga_satuan:float,diskon_persen:float}>} $data
     */
    public function buatDraft(array $data, int $userId): ReturGr
    {
        $gr = GoodsReceipt::findOrFail($data['goods_receipt_id']);

        if ($gr->status !== 'diverifikasi') {
            throw new \DomainException('Hanya GR berstatus diverifikasi yang bisa diretur.');
        }

        $items = $data['items'] ?? [];
        if (empty($items)) {
            throw new \DomainException('Pilih minimal satu item untuk diretur.');
        }

        return DB::transaction(function () use ($gr, $data, $items, $userId) {
            $totalNilai = 0;

            foreach ($items as $item) {
                $grItem = GrItem::findOrFail($item['gr_item_id']);
                $sisa   = $this->hitungSisaBisaDiretur($grItem);

                if ($item['jumlah_retur'] > $sisa) {
                    throw new \DomainException(
                        "Jumlah retur untuk {$grItem->barang->nama} melebihi sisa yang bisa diretur ({$sisa})."
                    );
                }
            }

            $retur = ReturGr::create([
                'nomor_retur'      => ReturGr::generateNomorRetur(),
                'goods_receipt_id' => $gr->id,
                'supplier_id'      => $gr->supplier_id,
                'tanggal_retur'    => now()->toDateString(),
                'alasan'           => $data['alasan'],
                'catatan'          => $data['catatan'] ?? null,
                'status'           => 'draft',
                'dibuat_oleh'      => $userId,
            ]);

            foreach ($items as $item) {
                $hargaEfektif = $item['harga_satuan'] * (1 - ($item['diskon_persen'] ?? 0) / 100);
                $subtotal     = $item['jumlah_retur'] * $hargaEfektif;
                $totalNilai  += $subtotal;

                $retur->items()->create([
                    'gr_item_id'    => $item['gr_item_id'],
                    'barang_id'     => $item['barang_id'],
                    'jumlah_retur'  => $item['jumlah_retur'],
                    'harga_satuan'  => $item['harga_satuan'],
                    'diskon_persen' => $item['diskon_persen'] ?? 0,
                    'subtotal'      => $subtotal,
                ]);
            }

            $retur->update(['total_nilai' => $totalNilai]);

            activity('inventory')
                ->performedOn($retur)
                ->causedBy(auth()->user())
                ->withProperties(['nomor_retur' => $retur->nomor_retur])
                ->log('Retur GR dibuat (draft)');

            return $retur->load('items.barang');
        });
    }

    /**
     * Verifikasi retur -- stok berkurang, po_item.jumlah_diterima berkurang, jurnal dicatat.
     */
    public function verifikasi(ReturGr $retur, int $userId): ReturGr
    {
        if ($retur->status !== 'draft') {
            throw new \DomainException('Hanya retur berstatus draft yang bisa diverifikasi.');
        }

        return DB::transaction(function () use ($retur, $userId) {
            $retur->load('items.barang', 'items.grItem');

            foreach ($retur->items as $item) {
                $barang = Barang::pastikanCukup($item->barang_id, $item->jumlah_retur);

                $stokSebelum = $barang->stok;
                $barang->decrement('stok', $item->jumlah_retur);
                $stokSesudah = $stokSebelum - $item->jumlah_retur;

                MutasiStok::create([
                    'barang_id'      => $barang->id,
                    'user_id'        => $userId,
                    'tipe'           => 'retur_ke_supplier',
                    'jumlah'         => $item->jumlah_retur,
                    'stok_sebelum'   => $stokSebelum,
                    'stok_sesudah'   => $stokSesudah,
                    'hpr_sebelum'    => $barang->harga_pokok,
                    'hpr_sesudah'    => $barang->harga_pokok,
                    'referensi_tipe' => 'retur_gr',
                    'referensi_id'   => $retur->id,
                    'keterangan'     => "Retur {$retur->nomor_retur}: {$barang->nama} ({$retur->alasan})",
                ]);

                // Kembalikan jumlah_diterima di po_item -- barang ini dianggap belum benar-benar diterima final
                $grItem = $item->grItem;
                if ($grItem && $grItem->po_item_id) {
                    PoItem::where('id', $grItem->po_item_id)
                        ->decrement('jumlah_diterima', $item->jumlah_retur);
                }
            }

            $retur->update([
                'status'             => 'diverifikasi',
                'diverifikasi_oleh'  => $userId,
                'diverifikasi_pada'  => now(),
            ]);

            app(InventoriJurnalService::class)->catatReturSupplier($retur->fresh(['items.barang']));

            activity('inventory')
                ->performedOn($retur)
                ->causedBy(auth()->user())
                ->log('Retur GR diverifikasi -- stok & hutang dagang dikoreksi');

            return $retur->fresh(['items.barang']);
        });
    }

    public function batalkanDraft(ReturGr $retur): ReturGr
    {
        if ($retur->status !== 'draft') {
            throw new \DomainException('Hanya retur berstatus draft yang bisa dibatalkan.');
        }

        $retur->update(['status' => 'dibatalkan']);

        activity('inventory')
            ->performedOn($retur)
            ->causedBy(auth()->user())
            ->log('Retur GR dibatalkan');

        return $retur;
    }
}
