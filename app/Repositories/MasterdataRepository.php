<?php

namespace App\Repositories;

use App\Models\ItemPenunjang;
use App\Models\MasterTindakan;
use Illuminate\Support\Collection;

class MasterdataRepository
{
    public function searchOrderable(string $keyword, int $poliId, int $limit = 30): Collection
    {
        $tindakan = MasterTindakan::aktif()
            ->untukPoli($poliId)
            ->where('nama', 'like', "%{$keyword}%")
            ->select('id', 'kode', 'nama', 'tarif', 'tarif_bpjs', 'kategori')
            ->limit($limit)
            ->get()
            ->map(fn ($t) => array_merge($t->toArray(), ['sumber' => 'tindakan']));

        $penunjang = ItemPenunjang::aktif()
            ->whereIn('kategori', ['lab', 'radiologi'])
            ->where('nama', 'like', "%{$keyword}%")
            ->select('id', 'kode', 'nama', 'tarif', 'tarif_bpjs', 'kategori', 'satuan_waktu')
            ->limit($limit)
            ->get()
            ->map(fn ($p) => array_merge($p->toArray(), ['sumber' => 'penunjang']));

        return $tindakan->merge($penunjang)->sortBy('nama')->values();
    }

    public function getTindakanByPoli(int $poliId): Collection
    {
        return MasterTindakan::aktif()
            ->untukPoli($poliId)
            ->orderBy('nama')
            ->get();
    }

    public function getAllPenunjang(?string $kategori = null): Collection
    {
        return ItemPenunjang::aktif()
            ->when($kategori, fn ($q, $k) => $q->where('kategori', $k),
                             fn ($q)      => $q->whereIn('kategori', ['lab', 'radiologi']))
            ->orderBy('nama')
            ->get();
    }

    public function getMappingByTindakan(int $tindakanId): Collection
    {
        return MasterTindakan::with('poli:id,nama,kode')
            ->findOrFail($tindakanId)
            ->poli;
    }

    public function syncMappingPoli(int $tindakanId, array $poliIds): void
    {
        MasterTindakan::findOrFail($tindakanId)->poli()->sync($poliIds);
    }
}
