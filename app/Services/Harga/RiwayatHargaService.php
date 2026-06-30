<?php

namespace App\Services\Harga;

use App\Models\ProposalHargaItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class RiwayatHargaService
{
    /**
     * Query builder untuk halaman riwayat global.
     *
     * @param array{
     *   search?: string,
     *   item_type?: string,
     *   item_kategori?: string,
     *   proposal_harga_id?: int,
     *   tahun?: int|string,
     *   dari?: string,
     *   sampai?: string,
     * } $filter
     */
    public function query(array $filter): Builder
    {
        return ProposalHargaItem::query()
            ->join('proposal_harga as ph', 'ph.id', '=', 'proposal_harga_item.proposal_harga_id')
            ->select('proposal_harga_item.*')
            ->with([
                'proposal:id,judul,tanggal_efektif,diterapkan_pada,diterapkan_oleh',
                'proposal.diterapkanOleh:id,nama',
            ])
            ->where('ph.status', 'efektif')
            ->where('proposal_harga_item.is_skip', false)
            ->when($filter['search'] ?? null, fn ($q, $s) =>
                $q->where('proposal_harga_item.item_nama', 'like', "%{$s}%")
            )
            ->when($filter['item_type'] ?? null, fn ($q, $t) =>
                $q->where('proposal_harga_item.item_type', $t)
            )
            ->when($filter['item_kategori'] ?? null, fn ($q, $k) =>
                $q->where('proposal_harga_item.item_kategori', $k)
            )
            ->when($filter['proposal_harga_id'] ?? null, fn ($q, $id) =>
                $q->where('proposal_harga_item.proposal_harga_id', $id)
            )
            ->when($filter['tahun'] ?? null, fn ($q, $tahun) =>
                $q->where('ph.tahun', $tahun)
            )
            ->when($filter['dari'] ?? null, fn ($q, $dari) =>
                $q->where('ph.tanggal_efektif', '>=', $dari)
            )
            ->when($filter['sampai'] ?? null, fn ($q, $sampai) =>
                $q->where('ph.tanggal_efektif', '<=', $sampai)
            )
            ->orderByDesc('ph.tanggal_efektif')
            ->orderBy('proposal_harga_item.item_nama');
    }

    /**
     * Timeline semua perubahan harga untuk satu item tertentu, urut ASC.
     */
    public function timelineItem(string $itemType, int $itemId): Collection
    {
        return ProposalHargaItem::with([
                'proposal:id,judul,tanggal_efektif,diterapkan_pada,diterapkan_oleh',
                'proposal.diterapkanOleh:id,nama',
            ])
            ->efektif()
            ->untukItem($itemType, $itemId)
            ->where('is_skip', false)
            ->get()
            ->sortBy(fn ($i) => optional($i->proposal)->tanggal_efektif);
    }
}
