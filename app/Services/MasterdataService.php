<?php

namespace App\Services;

use App\Models\ItemPenunjang;
use App\Models\MasterTindakan;
use App\Models\PeralatanMedis;
use App\Models\PenggunaanAlat;
use App\Models\PermintaanPenunjang;
use App\Repositories\MasterdataRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MasterdataService
{
    public function __construct(
        private readonly MasterdataRepository $repo
    ) {}

    // ── Search Engine ────────────────────────────────────────

    public function searchOrderable(string $keyword, int $poliId)
    {
        if (! $poliId) {
            throw ValidationException::withMessages([
                'poli_id' => 'Poli dokter tidak ditemukan. Pastikan profil dokter sudah dikonfigurasi.',
            ]);
        }
        return $this->repo->searchOrderable($keyword, $poliId);
    }

    // ── Master Tindakan ──────────────────────────────────────

    public function createTindakan(array $data, array $poliIds): MasterTindakan
    {
        if (empty($poliIds)) {
            throw ValidationException::withMessages([
                'poli_ids' => 'Tindakan wajib dipetakan ke minimal satu Poli.',
            ]);
        }

        return DB::transaction(function () use ($data, $poliIds) {
            $tindakan = MasterTindakan::create(array_merge($data, ['kategori' => 'tindakan']));
            $tindakan->poli()->sync($poliIds);

            activity('masterdata')
                ->performedOn($tindakan)
                ->causedBy(auth()->user())
                ->withProperties(['poli_count' => count($poliIds)])
                ->log('Tindakan baru dibuat');

            return $tindakan->load('poli');
        });
    }

    public function updateTindakan(int $id, array $data, array $poliIds): MasterTindakan
    {
        if (empty($poliIds)) {
            throw ValidationException::withMessages([
                'poli_ids' => 'Tindakan wajib dipetakan ke minimal satu Poli.',
            ]);
        }

        return DB::transaction(function () use ($id, $data, $poliIds) {
            $tindakan = MasterTindakan::findOrFail($id);
            $tindakan->update($data);
            $tindakan->poli()->sync($poliIds);

            activity('masterdata')
                ->performedOn($tindakan)
                ->causedBy(auth()->user())
                ->log('Tindakan diupdate');

            return $tindakan->fresh('poli');
        });
    }

    public function toggleAktifTindakan(int $id): MasterTindakan
    {
        $item = MasterTindakan::findOrFail($id);
        $item->update(['is_active' => ! $item->is_active]);
        return $item;
    }

    // ── Item Penunjang ───────────────────────────────────────

    public function createPenunjang(array $data): ItemPenunjang
    {
        $item = ItemPenunjang::create($data);

        activity('masterdata')
            ->performedOn($item)
            ->causedBy(auth()->user())
            ->log("Item penunjang ({$item->kategori}) ditambahkan");

        return $item;
    }

    public function updatePenunjang(int $id, array $data): ItemPenunjang
    {
        $item = ItemPenunjang::findOrFail($id);
        $item->update($data);
        return $item;
    }

    public function toggleAktifPenunjang(int $id): ItemPenunjang
    {
        $item = ItemPenunjang::findOrFail($id);
        $item->update(['is_active' => ! $item->is_active]);
        return $item;
    }

    // ── Peralatan Medis ──────────────────────────────────────

    public function toggleAktifPeralatan(int $id): PeralatanMedis
    {
        $alat = PeralatanMedis::findOrFail($id);
        $alat->update(['is_active' => ! $alat->is_active]);
        return $alat;
    }

    public function createPeralatan(array $data): PeralatanMedis
    {
        return PeralatanMedis::create($data);
    }

    public function updatePeralatan(int $id, array $data): PeralatanMedis
    {
        $alat = PeralatanMedis::findOrFail($id);
        $alat->update($data);
        return $alat;
    }

    public function pakaiAlat(int $peralatanId, int $poliId, ?int $kunjunganId = null): PenggunaanAlat
    {
        $alat = PeralatanMedis::findOrFail($peralatanId);

        if ($alat->status === 'digunakan') {
            throw ValidationException::withMessages([
                'peralatan' => 'Alat sedang digunakan di poli lain.',
            ]);
        }
        if (in_array($alat->status, ['maintenance', 'rusak'])) {
            throw ValidationException::withMessages([
                'peralatan' => "Alat tidak dapat digunakan: status {$alat->status}.",
            ]);
        }

        return DB::transaction(function () use ($alat, $peralatanId, $poliId, $kunjunganId) {
            $alat->update(['status' => 'digunakan', 'poli_terakhir_id' => $poliId]);
            return PenggunaanAlat::create([
                'peralatan_id' => $peralatanId,
                'poli_id'      => $poliId,
                'kunjungan_id' => $kunjunganId,
                'dipakai_oleh' => auth()->user()->nama,
            ]);
        });
    }

    public function selesaiPakaiAlat(int $penggunaanId): PenggunaanAlat
    {
        return DB::transaction(function () use ($penggunaanId) {
            $p = PenggunaanAlat::with('peralatan')->findOrFail($penggunaanId);
            $p->update(['waktu_selesai' => now()]);
            $p->peralatan->update(['status' => 'tersedia']);
            return $p;
        });
    }

    // ── Permintaan Penunjang ─────────────────────────────────

    public function buatPermintaan(int $kunjunganId, array $items): void
    {
        $data = collect($items)->map(fn ($item) => [
            'kunjungan_id'      => $kunjunganId,
            'item_penunjang_id' => $item['id'],
            'jumlah'            => $item['jumlah'] ?? 1,
            'catatan'           => $item['catatan'] ?? null,
            'status'            => 'dipesan',
            'created_at'        => now(),
            'updated_at'        => now(),
        ])->toArray();

        PermintaanPenunjang::insert($data);
    }
}
