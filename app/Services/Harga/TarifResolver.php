<?php

namespace App\Services\Harga;

class TarifResolver
{
    /**
     * Pilih harga/tarif yang berlaku dari 3 kemungkinan kolom master data:
     * umum, BPJS, dan WNA.
     *
     * Aturan prioritas:
     * - Pasien WNA & kolom tarif_wna diisi → pakai tarif_wna.
     *   (Asumsi bisnis: WNA tidak memakai BPJS, jadi WNA selalu prioritas
     *   di atas status BPJS kalau nilainya tersedia.)
     * - Selain itu, ikuti logic lama: BPJS → tarif_bpjs, umum → tarif.
     * - Kolom yang belum diisi admin (null) otomatis fallback ke tarif umum,
     *   supaya item yang belum sempat di-generate harga WNA-nya tetap
     *   punya harga (tidak Rp 0) dan tidak mengganggu transaksi.
     */
    public static function pilih(
        ?float $umum,
        ?float $bpjs,
        ?float $wna,
        bool $isBpjs = false,
        bool $isWna = false,
    ): float {
        if ($isWna && $wna !== null) {
            return $wna;
        }

        if ($isBpjs && $bpjs !== null) {
            return $bpjs;
        }

        return $umum ?? 0.0;
    }
}
