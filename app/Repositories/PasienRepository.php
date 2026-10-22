<?php

namespace App\Repositories;

use App\Models\KontakDarurat;
use App\Models\Pasien;
use App\Models\PasienAsuransi;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class PasienRepository
{
    public function paginate(array $filters = [], int $perPage = 10): LengthAwarePaginator
    {
        return Pasien::with('kontakPrimary')
            ->when($filters['search']      ?? null, fn ($q, $s) => $q->search($s))
            ->when($filters['tipe_pasien'] ?? null, fn ($q, $t) => $q->where('tipe_pasien', $t))
            ->when(isset($filters['is_active']),
                fn ($q) => $q->where('is_active', $filters['is_active']))
            ->orderBy($filters['sort_by']  ?? 'created_at', $filters['sort_dir'] ?? 'desc')
            ->paginate($perPage);
    }

    public function findById(int $id): ?Pasien
    {
        return Pasien::with([
            'kontakDarurat',
            'kunjungan' => fn ($q) => $q
                ->with(['poli:id,nama', 'dokter.user:id,nama'])
                ->take(5),
        ])->find($id);
    }

    public function findByNomorRM(string $nomorRM): ?Pasien
    {
        return Pasien::where('nomor_rm', $nomorRM)->first();
    }

    public function create(array $data, array $kontakList = [], ?array $asuransiData = null): Pasien
    {
        return DB::transaction(function () use ($data, $kontakList, $asuransiData) {
            $pasien = Pasien::create($data);

            if (! empty($kontakList)) {
                if (count($kontakList) === 1) {
                    $kontakList[0]['is_primary'] = true;
                }
                foreach ($kontakList as $k) {
                    $pasien->kontakDarurat()->create($k);
                }
            }

            // Handle asuransi if provided
            if ($asuransiData && isset($asuransiData['asuransi_id']) && isset($asuransiData['nomor_polis'])) {
                PasienAsuransi::create([
                    'pasien_id'   => $pasien->id,
                    'asuransi_id' => $asuransiData['asuransi_id'],
                    'nomor_polis' => $asuransiData['nomor_polis'],
                    'is_primary'  => true,
                    'is_active'   => true,
                ]);
            }

            return $pasien->load('kontakDarurat');
        });
    }

    public function update(int $id, array $data, ?array $kontakList = null, ?array $asuransiData = null): Pasien
    {
        return DB::transaction(function () use ($id, $data, $kontakList, $asuransiData) {
            $pasien = Pasien::findOrFail($id);
            $pasien->update($data);

            if ($kontakList !== null) {
                $pasien->kontakDarurat()->delete();
                if (! empty($kontakList)) {
                    if (count($kontakList) === 1) {
                        $kontakList[0]['is_primary'] = true;
                    }
                    foreach ($kontakList as $k) {
                        $pasien->kontakDarurat()->create($k);
                    }
                }
            }

            // Handle asuransi update
            if ($asuransiData) {
                if (isset($asuransiData['asuransi_id']) && isset($asuransiData['nomor_polis'])) {
                    // Clear existing primary asuransi
                    PasienAsuransi::where('pasien_id', $id)
                        ->where('is_primary', true)
                        ->delete();
                    
                    // Create new primary asuransi
                    PasienAsuransi::create([
                        'pasien_id'   => $id,
                        'asuransi_id' => $asuransiData['asuransi_id'],
                        'nomor_polis' => $asuransiData['nomor_polis'],
                        'is_primary'  => true,
                        'is_active'   => true,
                    ]);
                }
            }

            return $pasien->fresh('kontakDarurat');
        });
    }

    public function toggleActive(int $id, bool $state): Pasien
    {
        $pasien = Pasien::findOrFail($id);
        $pasien->update(['is_active' => $state]);
        return $pasien;
    }

    public function setPrimaryKontak(int $kontakId): void
    {
        $kontak = KontakDarurat::findOrFail($kontakId);
        KontakDarurat::where('pasien_id', $kontak->pasien_id)->update(['is_primary' => false]);
        $kontak->update(['is_primary' => true]);
    }

    public function deleteKontak(int $kontakId): void
    {
        DB::transaction(function () use ($kontakId) {
            $kontak     = KontakDarurat::findOrFail($kontakId);
            $wasPrimary = $kontak->is_primary;
            $pasienId   = $kontak->pasien_id;
            $kontak->delete();

            if ($wasPrimary) {
                $nextPrimary = KontakDarurat::where('pasien_id', $pasienId)
                                            ->oldest()
                                            ->first();
                if ($nextPrimary) {
                    $nextPrimary->update(['is_primary' => true]);
                }
            }
        });
    }
}
