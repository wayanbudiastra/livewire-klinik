<?php

namespace App\Services\Kasir;

use App\Models\Invoice;
use App\Models\TransaksiRitel;

/**
 * Tagihan Gabungan = billing kunjungan asal + semua susulan (lab/obat) yang
 * ditautkan ke kunjungan itu. Hanya TAMPILAN/DOKUMEN (bukti klaim reimburse
 * asuransi): tidak mengubah billing, jurnal, atau rekap sesi kas.
 * Susulan dibatalkan tidak dihitung.
 */
class TagihanGabunganService
{
    /**
     * @return array{billing:Invoice, susulan:\Illuminate\Support\Collection,
     *               total_billing:float, total_susulan:float, total_gabungan:float,
     *               total_dibayar:float, sisa:float}
     */
    public function susun(Invoice $billing): array
    {
        $billing->loadMissing('kunjungan.pasien', 'kunjungan.dokter.user', 'kunjungan.poli', 'items');

        $susulan = TransaksiRitel::susulan()
            ->with('items.barang', 'items.itemPenunjang', 'dokter.user')
            ->where('kunjungan_id', $billing->kunjungan_id)
            ->where('status', '!=', 'dibatalkan')
            ->orderBy('created_at')
            ->get();

        $totalBilling = (float) $billing->total_tagihan;
        $totalSusulan = (float) $susulan->sum('total_harga');
        $dibayarSusulan = (float) $susulan->whereIn('status', ['dibayar', 'selesai'])->sum('total_harga');
        $dibayar = (float) $billing->total_bayar + $dibayarSusulan;
        $gabungan = $totalBilling + $totalSusulan;

        return [
            'billing'        => $billing,
            'susulan'        => $susulan,
            'total_billing'  => $totalBilling,
            'total_susulan'  => $totalSusulan,
            'total_gabungan' => $gabungan,
            'total_dibayar'  => $dibayar,
            'sisa'           => max(0, $gabungan - $dibayar),
        ];
    }
}
