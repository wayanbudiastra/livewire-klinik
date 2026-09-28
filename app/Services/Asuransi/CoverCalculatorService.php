<?php

namespace App\Services\Asuransi;

use App\Models\{Invoice, Asuransi, PiutangAsuransi};

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

        // Terapkan plafon per tahun jika ada -- sebelumnya TIDAK PERNAH
        // dicek sama sekali (cuma plafon per kunjungan), jadi klinik bisa
        // menagih asuransi melebihi limit tahunan pasien tanpa pengecekan
        // apa pun. Dihitung dari akumulasi PiutangAsuransi (yg belum
        // ditolak asuransi) utk pasien+asuransi ini di tahun berjalan.
        if ($asuransi->plafon_per_tahun) {
            $sudahDipakaiTahunIni = PiutangAsuransi::where('pasien_id', $billing->kunjungan->pasien_id)
                ->where('asuransi_id', $asuransi->id)
                ->where('status', '!=', 'ditolak')
                ->whereYear('tanggal_piutang', now()->year)
                ->sum('jumlah_piutang');

            $sisaPlafonTahun = max(0, (float) $asuransi->plafon_per_tahun - (float) $sudahDipakaiTahunIni);

            if ($totalCover > $sisaPlafonTahun) {
                $selisih      = $totalCover - $sisaPlafonTahun;
                $totalCover   = $sisaPlafonTahun;
                $totalPasien += $selisih;
            }
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

        // Item obat/racikan hasil resep (billing.items) -- sebelumnya
        // dideteksi lewat kolom 'keterangan' yang TIDAK PERNAH ADA di
        // InvoiceItem (kolomnya nama_item + jenis), jadi kondisi ini
        // selalu false dan obat TIDAK PERNAH masuk hitungan cover
        // asuransi sama sekali. Diperbaiki pakai kolom jenis yang benar
        // (lihat InvoiceService -- jenis 'obat'/'racikan' dipakai utk
        // item hasil resep). Belum ada kategori cover_obat tersendiri di
        // master Asuransi, jadi tetap dikelompokkan ke 'peralatan' spt
        // niat kode aslinya.
        foreach ($billing->items ?? [] as $item) {
            if (in_array($item->jenis, ['obat', 'racikan'], true)) {
                $items[] = [
                    'nama'     => $item->nama_item,
                    'kategori' => 'peralatan',
                    'subtotal' => $item->subtotal,
                ];
            }
        }

        return $items;
    }
}
