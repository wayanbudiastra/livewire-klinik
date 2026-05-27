<?php

namespace App\Repositories;

use App\Models\Barang;
use App\Models\BatchExpired;
use App\Models\StokGudang;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class ObatRepository
{
    public function paginate(array $filters = [], int $perPage = 10): LengthAwarePaginator
    {
        return Barang::whereIn('jenis', ['obat', 'alkes'])
            ->when($filters['search']       ?? null, fn ($q, $s) => $q->search($s))
            ->when($filters['jenis_barang'] ?? null, fn ($q, $j) => $q->where('jenis', $j))
            ->when(isset($filters['is_active']),
                fn ($q) => $q->where('is_active', $filters['is_active']))
            ->when($filters['reorder'] ?? false,
                fn ($q) => $q->whereRaw('stok <= stok_minimum'))
            ->orderBy('nama')
            ->paginate($perPage);
    }

    public function getReorderAlert(): Collection
    {
        return Barang::aktif()
            ->whereIn('jenis', ['obat', 'alkes'])
            ->whereRaw('stok <= stok_minimum')
            ->get();
    }

    public function getBatchAkanExpired(int $hariKedepan = 90): Collection
    {
        return BatchExpired::with('barang:id,kode,nama,satuan')
            ->where('tanggal_expired', '>=', now())
            ->where('tanggal_expired', '<=', now()->addDays($hariKedepan))
            ->where('stok_batch', '>', 0)
            ->orderBy('tanggal_expired')
            ->get();
    }

    public function getBatchExpired(): Collection
    {
        return BatchExpired::with('barang:id,kode,nama,satuan')
            ->where('tanggal_expired', '<', now())
            ->where('stok_batch', '>', 0)
            ->orderByDesc('tanggal_expired')
            ->get();
    }

    public function upsertStokGudang(int $barangId, int $lokasiId, array $data): StokGudang
    {
        return StokGudang::updateOrCreate(
            ['barang_id' => $barangId, 'lokasi_gudang_id' => $lokasiId],
            $data
        );
    }
}
