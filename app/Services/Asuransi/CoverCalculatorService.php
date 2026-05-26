<?php

namespace App\Services\Asuransi;

use App\Models\{Invoice, Asuransi};

class CoverCalculatorService
{
    public function hitungCover(Invoice $billing, Asuransi $asuransi): array
    {
        $items       = $this->kumpulkanItem($billing);
        $totalCover  = 0;
        $totalPasien = 0;
        $rincian     = [];

        foreach ($items as $item) {
            $coverPersen  = $this->getCoverPersen($asuransi, $item['kategori']);
            $jumlahCover  = round($item['subtotal'] * ($coverPersen / 100), 2);
            $jumlahPasien = $item['subtotal'] - $jumlahCover;

            $totalCover  += $jumlahCover;
            $totalPasien += $jumlahPasien;

            $rincian[] = array_merge($item, [
                'cover_persen'  => $coverPersen,
                'jumlah_cover'  => $jumlahCover,
                'jumlah_pasien' => $jumlahPasien,
            ]);
        }

        // Terapkan plafon per kunjungan jika ada
        if ($asuransi->plafon_per_kunjungan && $totalCover > $asuransi->plafon_per_kunjungan) {
            $selisih      = $totalCover - $asuransi->plafon_per_kunjungan;
            $totalCover   = $asuransi->plafon_per_kunjungan;
            $totalPasien += $selisih;
        }

        return [
            'total_tagihan' => $billing->total_tagihan,
            'total_cover'   => $totalCover,
            'total_pasien'  => $totalPasien,
            'rincian'       => $rincian,
        ];
    }

    private function getCoverPersen(Asuransi $asuransi, string $kategori): float
    {
        return match ($kategori) {
            'prosedur'     => $asuransi->cover_prosedur,
            'laboratorium' => $asuransi->cover_laboratorium,
            'radiologi'    => $asuransi->cover_radiologi,
            'peralatan'    => $asuransi->cover_peralatan,
            default        => 0,
        };
    }

    private function kumpulkanItem(Invoice $billing): array
    {
        $items = [];

        foreach ($billing->kunjungan->tindakan ?? [] as $t) {
            $kategori = match ($t->masterTindakan->kategori ?? 'prosedur') {
                'lab', 'laboratorium' => 'laboratorium',
                'radiologi'           => 'radiologi',
                default               => 'prosedur',
            };
            $items[] = [
                'nama'     => $t->masterTindakan->nama,
                'kategori' => $kategori,
                'subtotal' => $t->jumlah * ($t->tarif ?? $t->masterTindakan->tarif),
            ];
        }

        foreach ($billing->kunjungan->pemakaianAlkes ?? [] as $pa) {
            $items[] = [
                'nama'     => $pa->barang->nama ?? 'Alkes',
                'kategori' => 'peralatan',
                'subtotal' => $pa->jumlah * ($pa->harga_satuan ?? 0),
            ];
        }

        foreach ($billing->items ?? [] as $item) {
            if (str_contains(strtolower($item->keterangan ?? ''), 'obat')) {
                $items[] = [
                    'nama'     => $item->keterangan,
                    'kategori' => 'peralatan',
                    'subtotal' => $item->subtotal,
                ];
            }
        }

        return $items;
    }
}
