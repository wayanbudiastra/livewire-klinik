<?php

namespace App\Services\Harga;

use App\Models\Barang;
use App\Models\ItemPenunjang;
use App\Models\KonfigurasiMarkupHarga;

/**
 * Markup harga jual OTOMATIS berbasis harga modal, per kategori
 * ('obat_bhp' / 'lab'). Dipanggil setiap kali harga modal berubah
 * (BarangForm/PenerimaanService utk Obat & BHP, PenunjangForm utk Lab) --
 * BUKAN cuma generator manual spt KonfigurasiHargaWna.
 */
class MarkupHargaService
{
    /**
     * @return array{ktp: ?float, wna: ?float}
     */
    public function hitung(string $kategori, ?float $modal): array
    {
        if (! $modal || $modal <= 0) {
            return ['ktp' => null, 'wna' => null];
        }

        $config = KonfigurasiMarkupHarga::untuk($kategori);
        if (! $config) {
            return ['ktp' => null, 'wna' => null];
        }

        return [
            'ktp' => round($modal * (float) $config->multiplier_ktp),
            'wna' => round($modal * (float) $config->multiplier_wna),
        ];
    }

    /**
     * Hitung ulang harga_jual & harga_wna SEMUA Barang jenis obat/bahan_habis_pakai
     * berdasarkan harga_pokok saat ini -- dipakai utk backfill data lama saat
     * fitur ini pertama kali aktif, atau setelah multiplier diubah.
     */
    public function hitungUlangObatBhp(): int
    {
        $jumlah = 0;

        Barang::whereIn('jenis', ['obat', 'bahan_habis_pakai'])
            ->whereNotNull('harga_pokok')
            ->where('harga_pokok', '>', 0)
            ->each(function (Barang $b) use (&$jumlah) {
                $hasil = $this->hitung('obat_bhp', (float) $b->harga_pokok);
                if ($hasil['ktp'] === null) return;

                $b->update([
                    'harga_jual' => $hasil['ktp'],
                    'harga_wna'  => $hasil['wna'],
                ]);
                $jumlah++;
            });

        return $jumlah;
    }

    /**
     * Hitung ulang tarif & tarif_wna SEMUA ItemPenunjang kategori 'lab'
     * berdasarkan harga_modal saat ini -- sama tujuannya dgn hitungUlangObatBhp().
     * Item yang belum diisi harga_modal-nya dilewati (tidak disentuh).
     */
    public function hitungUlangLab(): int
    {
        $jumlah = 0;

        ItemPenunjang::where('kategori', 'lab')
            ->whereNotNull('harga_modal')
            ->where('harga_modal', '>', 0)
            ->each(function (ItemPenunjang $p) use (&$jumlah) {
                $hasil = $this->hitung('lab', (float) $p->harga_modal);
                if ($hasil['ktp'] === null) return;

                $p->update([
                    'tarif'     => $hasil['ktp'],
                    'tarif_wna' => $hasil['wna'],
                ]);
                $jumlah++;
            });

        return $jumlah;
    }

    /** @return array{obat_bhp: int, lab: int} */
    public function hitungUlangSemua(): array
    {
        return [
            'obat_bhp' => $this->hitungUlangObatBhp(),
            'lab'      => $this->hitungUlangLab(),
        ];
    }
}
