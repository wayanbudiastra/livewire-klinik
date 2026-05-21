<?php

namespace App\Repositories;

use App\Models\Dokter;
use App\Models\DokterPoli;
use App\Models\SharingFee;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class DokterRepository
{
    public function paginate(array $filters = [], int $perPage = 10): LengthAwarePaginator
    {
        return Dokter::with(['user:id,nama,email,is_active', 'poli:id,nama,kode'])
            ->whereHas('user', function ($q) use ($filters) {
                $q->where('is_active', true);
                if ($filters['search'] ?? null) {
                    $q->where('nama', 'like', "%{$filters['search']}%")
                      ->orWhere('email', 'like', "%{$filters['search']}%");
                }
            })
            ->when($filters['filter_sip'] ?? null, function ($q, $sip) {
                if ($sip === 'expired') {
                    return $q->whereNotNull('tgl_expired_sip')
                             ->where('tgl_expired_sip', '<', now());
                }
                if ($sip === 'segera_expired') {
                    return $q->whereNotNull('tgl_expired_sip')
                             ->whereBetween('tgl_expired_sip', [now(), now()->addDays(30)]);
                }
                if ($sip === 'aktif') {
                    return $q->where('tgl_expired_sip', '>=', now());
                }
            })
            ->orderBy('id')
            ->paginate($perPage);
    }

    public function findById(int $id): ?Dokter
    {
        return Dokter::with([
            'user:id,nama,email,telepon,nip,is_active',
            'dokterPoli.poli:id,nama,kode',
            'dokterPoli.jadwalPraktek',
            'sharingFee',
        ])->find($id);
    }

    public function findByUserId(int $userId): ?Dokter
    {
        return Dokter::where('user_id', $userId)->first();
    }

    public function updateProfil(int $id, array $data): Dokter
    {
        $dokter = Dokter::findOrFail($id);
        $dokter->update($data);
        return $dokter->fresh();
    }

    public function upsertMappingPoli(int $dokterId, int $poliId): DokterPoli
    {
        return DokterPoli::updateOrCreate(
            ['dokter_id' => $dokterId, 'poli_id' => $poliId],
            ['is_aktif'  => true]
        );
    }

    public function removeMappingPoli(int $dokterId, int $poliId): void
    {
        DokterPoli::where('dokter_id', $dokterId)
                  ->where('poli_id', $poliId)
                  ->update(['is_aktif' => false]);
    }

    public function upsertSharingFee(int $dokterId, array $fees): void
    {
        foreach ($fees as $kategori => $persentase) {
            SharingFee::updateOrCreate(
                ['dokter_id' => $dokterId, 'kategori' => $kategori],
                ['persentase' => $persentase]
            );
        }
    }

    public function getSharingFee(int $dokterId): Collection
    {
        return SharingFee::where('dokter_id', $dokterId)
                         ->orderBy('kategori')
                         ->get();
    }

    public function hitungSharingFee(int $dokterId, array $items): array
    {
        $feeMap = $this->getSharingFee($dokterId)
                       ->pluck('persentase', 'kategori')
                       ->toArray();

        return collect($items)->map(function ($item) use ($feeMap) {
            $persen = (float) ($feeMap[$item['kategori']] ?? 0);
            return [
                'kategori'    => $item['kategori'],
                'total_tarif' => $item['total_tarif'],
                'persentase'  => $persen,
                'nominal_fee' => $item['total_tarif'] * ($persen / 100),
            ];
        })->toArray();
    }
}
