<?php

namespace App\Services\Inventory;

use App\Models\{PemakaianBhp, PemakaianBhpItem, Barang, MutasiStok};
use App\Services\Akuntansi\InventoriJurnalService;
use Illuminate\Support\Facades\DB;

class PemakaianBhpService
{
    public function buatDraft(array $data, int $userId): PemakaianBhp
    {
        return PemakaianBhp::create([
            'nomor_bhp'         => $this->generateNomor(),
            'dicatat_oleh'      => $userId,
            'tanggal_pemakaian' => $data['tanggal_pemakaian'],
            'catatan'           => $data['catatan'] ?? null,
            'status'            => 'draft',
        ]);
    }

    public function tambahItem(PemakaianBhp $bhp, int $barangId, float $jumlah, ?string $keterangan = null): PemakaianBhpItem
    {
        if ($bhp->status !== 'draft') {
            throw new \DomainException('Hanya dokumen draft yang bisa ditambah item.');
        }

        $barang = Barang::findOrFail($barangId);

        if ($barang->jenis !== 'bahan_habis_pakai') {
            throw new \DomainException("Barang '{$barang->nama}' bukan bahan habis pakai.");
        }

        return $bhp->items()->create([
            'barang_id'            => $barangId,
            'jumlah'               => $jumlah,
            'harga_pokok_saat_itu' => $barang->harga_pokok,
            'nilai_total'          => $jumlah * $barang->harga_pokok,
            'keterangan'           => $keterangan,
        ]);
    }

    public function hapusItem(PemakaianBhp $bhp, int $itemId): void
    {
        if ($bhp->status !== 'draft') {
            throw new \DomainException('Hanya dokumen draft yang bisa diedit.');
        }

        $bhp->items()->findOrFail($itemId)->delete();
    }

    public function verifikasi(PemakaianBhp $bhp, int $userId): PemakaianBhp
    {
        if ($bhp->status !== 'draft') {
            throw new \DomainException('Hanya dokumen draft yang bisa diverifikasi.');
        }

        $bhp->load('items');

        if ($bhp->items->isEmpty()) {
            throw new \DomainException('Dokumen BHP tidak boleh kosong.');
        }

        return DB::transaction(function () use ($bhp, $userId) {
            foreach ($bhp->items as $item) {
                $barang     = Barang::pastikanCukup($item->barang_id, (float) $item->jumlah);
                $stokBefore = $barang->stok;
                $hprBefore  = $barang->harga_pokok;

                $barang->decrement('stok', $item->jumlah);

                MutasiStok::create([
                    'barang_id'      => $item->barang_id,
                    'user_id'        => $userId,
                    'tipe'           => 'keluar_bhp',
                    'jumlah'         => $item->jumlah,
                    'stok_sebelum'   => $stokBefore,
                    'stok_sesudah'   => $stokBefore - $item->jumlah,
                    'hpr_sebelum'    => $hprBefore,
                    'hpr_sesudah'    => $hprBefore,
                    'referensi_tipe' => 'pemakaian_bhp',
                    'referensi_id'   => $bhp->id,
                    'keterangan'     => "Pemakaian BHP {$bhp->nomor_bhp}",
                ]);

                $item->update(['nilai_total' => $item->jumlah * $barang->fresh()->harga_pokok]);
            }

            $bhp->update([
                'status'            => 'selesai',
                'diverifikasi_oleh' => $userId,
            ]);

            app(InventoriJurnalService::class)->catatPemakaianBhp($bhp->fresh(['items.barang']));

            return $bhp->fresh();
        });
    }

    public function batalkan(PemakaianBhp $bhp): PemakaianBhp
    {
        if ($bhp->status === 'selesai') {
            throw new \DomainException('Dokumen yang sudah selesai tidak bisa dibatalkan.');
        }

        $bhp->update(['status' => 'dibatalkan']);
        return $bhp;
    }

    private function generateNomor(): string
    {
        $prefix = 'BHP-' . now()->format('Y-m-');
        $last   = PemakaianBhp::where('nomor_bhp', 'like', $prefix . '%')
                    ->orderByDesc('nomor_bhp')
                    ->value('nomor_bhp');
        $seq    = $last ? (int) substr($last, -4) + 1 : 1;
        return $prefix . str_pad($seq, 4, '0', STR_PAD_LEFT);
    }
}
