<?php

namespace App\Services;

use App\Models\BatchExpired;
use App\Models\Obat;
use App\Models\StokGudang;
use App\Repositories\ObatRepository;
use Illuminate\Validation\ValidationException;

class FarmasiService
{
    public function __construct(
        private readonly ObatRepository $repo
    ) {}

    // ── Master Obat ───────────────────────────────────────────

    public function createObat(array $data): Obat
    {
        $obat = Obat::create($data);

        activity('farmasi')
            ->performedOn($obat)
            ->causedBy(auth()->user())
            ->log('Obat/Alkes baru ditambahkan');

        return $obat;
    }

    public function updateObat(int $id, array $data): Obat
    {
        $obat = Obat::findOrFail($id);
        $obat->update($data);

        activity('farmasi')
            ->performedOn($obat)
            ->causedBy(auth()->user())
            ->log('Data obat/alkes diupdate');

        return $obat;
    }

    public function toggleAktif(int $id): Obat
    {
        $obat = Obat::findOrFail($id);
        $obat->update(['is_active' => ! $obat->is_active]);

        activity('farmasi')
            ->performedOn($obat)
            ->causedBy(auth()->user())
            ->log($obat->is_active ? 'Obat diaktifkan' : 'Obat dinonaktifkan');

        return $obat;
    }

    // ── Stok Gudang ───────────────────────────────────────────

    public function aturMinMax(int $obatId, int $lokasiId, int $stokMin, int $stokMax): StokGudang
    {
        if ($stokMin >= $stokMax) {
            throw ValidationException::withMessages([
                'stok_min' => 'Stok minimum harus lebih kecil dari stok maksimum.',
            ]);
        }

        return $this->repo->upsertStokGudang($obatId, $lokasiId, [
            'stok_min' => $stokMin,
            'stok_max' => $stokMax,
        ]);
    }

    // ── Batch Expired ─────────────────────────────────────────

    public function tambahBatch(int $obatId, array $data): BatchExpired
    {
        return BatchExpired::create(array_merge($data, ['obat_id' => $obatId]));
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

    public function getStokTotal(int $obatId): int
    {
        return StokGudang::where('obat_id', $obatId)->sum('stok');
    }
}
