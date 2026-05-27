<?php

namespace App\Services;

use App\Models\Barang;
use App\Models\BatchExpired;
use App\Models\StokGudang;
use App\Repositories\ObatRepository;
use Illuminate\Validation\ValidationException;

class FarmasiService
{
    public function __construct(
        private readonly ObatRepository $repo
    ) {}

    // ── Master Obat/Alkes (via Barang) ────────────────────────

    public function createObat(array $data): Barang
    {
        $barang = Barang::create($data);

        activity('farmasi')
            ->performedOn($barang)
            ->causedBy(auth()->user())
            ->log('Obat/Alkes baru ditambahkan');

        return $barang;
    }

    public function updateObat(int $id, array $data): Barang
    {
        $barang = Barang::findOrFail($id);
        $barang->update($data);

        activity('farmasi')
            ->performedOn($barang)
            ->causedBy(auth()->user())
            ->log('Data obat/alkes diupdate');

        return $barang;
    }

    public function toggleAktif(int $id): Barang
    {
        $barang = Barang::findOrFail($id);
        $barang->update(['is_active' => ! $barang->is_active]);

        activity('farmasi')
            ->performedOn($barang)
            ->causedBy(auth()->user())
            ->log($barang->is_active ? 'Obat diaktifkan' : 'Obat dinonaktifkan');

        return $barang;
    }

    // ── Stok Gudang ───────────────────────────────────────────

    public function aturMinMax(int $barangId, int $lokasiId, int $stokMin, int $stokMax): StokGudang
    {
        if ($stokMin >= $stokMax) {
            throw ValidationException::withMessages([
                'stok_min' => 'Stok minimum harus lebih kecil dari stok maksimum.',
            ]);
        }

        return $this->repo->upsertStokGudang($barangId, $lokasiId, [
            'stok_min' => $stokMin,
            'stok_max' => $stokMax,
        ]);
    }

    // ── Batch Expired ─────────────────────────────────────────

    public function tambahBatch(int $barangId, array $data): BatchExpired
    {
        return BatchExpired::create(array_merge($data, ['barang_id' => $barangId]));
    }

    public function hapusBatch(int $batchId): void
    {
        BatchExpired::findOrFail($batchId)->delete();
    }

    // ── Alert ─────────────────────────────────────────────────

    public function getReorderAlert()
    {
        return $this->repo->getReorderAlert();
    }

    public function getBatchAkanExpired(int $hari = 90)
    {
        return $this->repo->getBatchAkanExpired($hari);
    }

    public function getBatchExpired()
    {
        return $this->repo->getBatchExpired();
    }

    public function getStokTotal(int $barangId): int
    {
        return StokGudang::where('barang_id', $barangId)->sum('stok');
    }
}
