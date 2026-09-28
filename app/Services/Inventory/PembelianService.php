<?php

namespace App\Services\Inventory;

use App\Models\PurchaseOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PembelianService
{
    public function buatPo(array $data): PurchaseOrder
    {
        return DB::transaction(function () use ($data) {
            $items = $data['items'] ?? [];
            unset($data['items']);

            $po = PurchaseOrder::create(array_merge($data, [
                'nomor_po' => PurchaseOrder::generateNomorPo(),
                'status'   => 'draft',
            ]));

            foreach ($items as $item) {
                $subtotal = $item['jumlah_pesan'] * $item['harga_satuan']
                          * (1 - ($item['diskon_persen'] ?? 0) / 100);
                $po->items()->create(array_merge($item, ['subtotal' => $subtotal]));
            }

            // Hitung total nilai
            $totalNilai = $po->items()->sum('subtotal');
            $po->update(['total_nilai' => $totalNilai]);

            activity('inventory')
                ->performedOn($po)
                ->causedBy(auth()->user())
                ->withProperties(['nomor_po' => $po->nomor_po])
                ->log('Purchase Order dibuat');

            return $po->load('items.barang');
        });
    }

    public function approvePo(PurchaseOrder $po, int $userId): PurchaseOrder
    {
        return DB::transaction(function () use ($po, $userId) {
            // Kunci ulang & cek ulang status DI DALAM transaksi -- mencegah
            // 2 klik "Approve" bersamaan memproses baris yang sama dobel.
            $poLocked = PurchaseOrder::where('id', $po->id)->lockForUpdate()->firstOrFail();

            if ($poLocked->status !== 'draft') {
                throw ValidationException::withMessages([
                    'status' => 'Hanya PO berstatus draft yang bisa di-approve.',
                ]);
            }

            // Maker-checker: pembuat PO tidak boleh meng-approve PO-nya
            // sendiri -- beda dari GR (yang memang sengaja punya opsi
            // "Simpan & Verifikasi" sekaligus sbg fitur), PO tidak pernah
            // punya jalur create+approve gabungan, jadi guard ini aman
            // ditambahkan tanpa mematahkan fitur yang ada.
            if ((int) $poLocked->dibuat_oleh === (int) $userId) {
                throw ValidationException::withMessages([
                    'status' => 'Pembuat PO tidak boleh meng-approve PO-nya sendiri.',
                ]);
            }

            $poLocked->update([
                'status'            => 'dikirim',
                'disetujui_oleh'    => $userId,
                'tanggal_disetujui' => now(),
            ]);

            activity('inventory')
                ->performedOn($poLocked)
                ->causedBy(auth()->user())
                ->log('PO disetujui dan dikirim ke supplier');

            return $poLocked;
        });
    }

    public function batalkanPo(PurchaseOrder $po): PurchaseOrder
    {
        return DB::transaction(function () use ($po) {
            $poLocked = PurchaseOrder::where('id', $po->id)->lockForUpdate()->firstOrFail();

            if (! in_array($poLocked->status, ['draft', 'dikirim'])) {
                throw ValidationException::withMessages([
                    'status' => 'PO yang sudah ada penerimaannya tidak bisa dibatalkan.',
                ]);
            }

            $grVerifikasi = $poLocked->goodsReceipts()->where('status', 'diverifikasi')->exists();
            if ($grVerifikasi) {
                throw ValidationException::withMessages([
                    'status' => 'PO memiliki GR yang sudah diverifikasi. Tidak bisa dibatalkan.',
                ]);
            }

            $poLocked->update(['status' => 'dibatalkan']);

            activity('inventory')
                ->performedOn($poLocked)
                ->causedBy(auth()->user())
                ->log('PO dibatalkan');

            return $poLocked;
        });
    }
}
