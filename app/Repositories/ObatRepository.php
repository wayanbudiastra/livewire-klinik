<?php

namespace App\Repositories;

use App\Models\BatchExpired;
use App\Models\Obat;
use App\Models\StokGudang;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class ObatRepository
{
    public function paginate(array $filters = [], int $perPage = 10): LengthAwarePaginator
    {
        return Obat::with(['satuanBesar:id,nama', 'satuanKecil:id,nama'])
            ->when($filters['search']       ?? null, fn ($q, $s) => $q->search($s))
            ->when($filters['jenis_barang'] ?? null, fn ($q, $j) => $q->where('jenis_barang', $j))
            ->when(isset($filters['is_active']),
                fn ($q) => $q->where('is_active', $filters['is_active']))
            ->when($filters['reorder'] ?? false,
                fn ($q) => $q->whereHas('stokGudang', fn ($sq) =>
                    $sq->whereColumn('stok', '<=', 'stok_min')))
            ->orderBy('nama')
            ->paginate($perPage);
    }

    public function getReorderAlert(): Collection
    {
        return Obat::aktif()
            ->with(['stokGudang.lokasiGudang:id,nama,kode'])
            ->whereHas('stokGudang', fn ($q) =>
                $q->whereColumn('stok', '<=', 'stok_min'))
            ->get();
    }

    public function getBatchAkanExpired(int $hariKedepan = 90): Collection
    {
        return BatchExpired::with('obat:id,kode,nama,satuan')
            ->where('tanggal_expired', '>=', now())
            ->where('tanggal_expired', '<=', now()->addDays($hariKedepan))
            ->where('stok_batch', '>', 0)
            ->orderBy('tanggal_expired')
            ->get();
    }

    public function getBatchExpired(): Collection
    {
        return BatchExpired::with('obat:id,kode,nama,satuan')
            ->where('tanggal_expired', '<', now())
            ->where('stok_batch', '>', 0)
            ->orderByDesc('tanggal_expired')
            ->get();
    }

    public function upsertStokGudang(int $obatId, int $lokasiId, array $data): StokGudang
    {
        return StokGudang::updateOrCreate(
            ['obat_id' => $obatId, 'lokasi_gudang_id' => $lokasiId],
            $data
        );
    }
}
