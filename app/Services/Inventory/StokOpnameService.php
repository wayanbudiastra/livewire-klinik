<?php

namespace App\Services\Inventory;

use App\Models\{StokOpname, StokOpnameItem, Barang, MutasiStok};
use App\Services\Akuntansi\InventoriJurnalService;
use Illuminate\Support\Facades\DB;

class StokOpnameService
{
    public function buatOpname(array $data, int $userId, ?string $filterJenis = null): StokOpname
    {
        return DB::transaction(function () use ($data, $userId, $filterJenis) {
            $opname = StokOpname::create([
                'nomor_opname'       => $this->generateNomor(),
                'dibuat_oleh'        => $userId,
                'tanggal_opname'     => $data['tanggal_opname'],
                'keterangan_periode' => $data['keterangan_periode'] ?? null,
                'catatan'            => $data['catatan'] ?? null,
            ]);

            $query = Barang::where('is_active', true);
            if ($filterJenis) {
                $query->where('jenis', $filterJenis);
            }

            foreach ($query->get() as $barang) {
                $opname->items()->create([
                    'barang_id'    => $barang->id,
                    'stok_sistem'  => $barang->stok,
                    'hpr_saat_itu' => $barang->harga_pokok,
                    'stok_fisik'   => null,
                ]);
            }

            return $opname->load('items.barang');
        });
    }

    public function inputStokFisik(StokOpnameItem $item, float $stokFisik): StokOpnameItem
    {
        $selisih = $stokFisik - $item->stok_sistem;

        $tipeSelisih = match (true) {
            abs($selisih) < 0.001 => 'sesuai',
            $selisih > 0          => 'lebih',
            default               => 'kurang',
        };

        $item->update([
            'stok_fisik'    => $stokFisik,
            'selisih'       => $selisih,
            'tipe_selisih'  => $tipeSelisih,
            'nilai_selisih' => abs($selisih) * $item->hpr_saat_itu,
        ]);

        return $item->fresh();
    }

    public function submitUntukVerifikasi(StokOpname $opname): StokOpname
    {
        if ($opname->status !== 'draft') {
            throw new \DomainException('Hanya opname draft yang bisa disubmit.');
        }

        $belumDiisi = $opname->items()->whereNull('stok_fisik')->count();

        if ($belumDiisi > 0) {
            throw new \DomainException("Masih ada {$belumDiisi} item yang belum diisi stok fisiknya.");
        }

        $opname->update(['status' => 'menunggu_verifikasi']);
        return $opname;
    }

    public function verifikasi(StokOpname $opname, int $userId): StokOpname
    {
        if ($opname->status !== 'menunggu_verifikasi') {
            throw new \DomainException('Status opname harus "menunggu_verifikasi".');
        }

        return DB::transaction(function () use ($opname, $userId) {
            $opname->load('items');

            foreach ($opname->items as $item) {
                if ($item->tipe_selisih === 'sesuai') continue;

                $barang   = Barang::lockForUpdate()->findOrFail($item->barang_id);
                $stokLama = $barang->stok;

                $barang->update(['stok' => $item->stok_fisik]);

                $tipeMutasi = $item->tipe_selisih === 'lebih'
                    ? 'penyesuaian_masuk'
                    : 'penyesuaian_keluar';

                MutasiStok::create([
                    'barang_id'      => $barang->id,
                    'user_id'        => $userId,
                    'tipe'           => $tipeMutasi,
                    'jumlah'         => abs($item->selisih),
                    'stok_sebelum'   => $stokLama,
                    'stok_sesudah'   => $item->stok_fisik,
                    'hpr_sebelum'    => $item->hpr_saat_itu,
                    'hpr_sesudah'    => $item->hpr_saat_itu,
                    'referensi_tipe' => 'stok_opname',
                    'referensi_id'   => $opname->id,
                    'keterangan'     => "Stok Opname {$opname->nomor_opname}: {$item->tipe_selisih}",
                ]);
            }

            $opname->update([
                'status'            => 'selesai',
                'diverifikasi_oleh' => $userId,
            ]);

            app(InventoriJurnalService::class)->catatStokOpname($opname->fresh(['items.barang']));

            return $opname->fresh();
        });
    }

    public function batalkan(StokOpname $opname): StokOpname
    {
        if ($opname->status === 'selesai') {
            throw new \DomainException('Opname yang sudah selesai tidak bisa dibatalkan.');
        }

        $opname->update(['status' => 'dibatalkan']);
        return $opname;
    }

    private function generateNomor(): string
    {
        $prefix = 'OPN-' . now()->format('Y-m-');
        $last   = StokOpname::where('nomor_opname', 'like', $prefix . '%')
                    ->orderByDesc('nomor_opname')
                    ->value('nomor_opname');
        $seq    = $last ? (int) substr($last, -4) + 1 : 1;
        return $prefix . str_pad($seq, 4, '0', STR_PAD_LEFT);
    }
}
