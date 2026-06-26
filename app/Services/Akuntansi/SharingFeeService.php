<?php

namespace App\Services\Akuntansi;

use App\Models\Invoice;
use App\Models\SharingFee;

/**
 * Scope v1 (sederhana): hanya menghitung sharing fee kategori 'tindakan',
 * berdasarkan dokter penanggung jawab kunjungan (Kunjungan::dokter_id).
 * Kategori lab/radiologi/peralatan BELUM dihitung — perlu kategorisasi
 * ItemPenunjang per jenis yang belum tersedia untuk dipetakan dari InvoiceItem.
 */
class SharingFeeService
{
    const AKUN_BIAYA_JASA_DOKTER  = '5-1200';
    const AKUN_HUTANG_JASA_DOKTER = '2-1200';

    public function __construct(private JurnalService $jurnal) {}

    /** Dipanggil saat billing lunas (BillingService / PembayaranAsuransiService). */
    public function catatSharingFee(Invoice $billing): void
    {
        $nilaiFee = $this->hitungNilaiFee($billing);
        if ($nilaiFee === null) return;

        [$nominal, $dokterId, $persentase] = $nilaiFee;

        $this->jurnal->catat(
            sumberTipe:    'billing',
            sumberId:      $billing->id,
            tipeTransaksi: 'sharing_fee_dokter',
            tanggal:       $billing->updated_at ?? now(),
            akunDebit:     self::AKUN_BIAYA_JASA_DOKTER,
            akunKredit:    self::AKUN_HUTANG_JASA_DOKTER,
            nominal:       $nominal,
            keterangan:    "Sharing fee dokter - {$billing->nomor_invoice}",
            metadata:      ['dokter_id' => $dokterId, 'persentase' => $persentase],
        );
    }

    /**
     * Reversal saat billing yang sudah lunas dibatalkan (BillingService::batalkanBilling).
     * Membaca persis baris jurnal "sharing_fee_dokter" yang sudah tercatat, lalu membalik
     * debit/kreditnya -- kalau sudah diposting, reversal-nya juga langsung diposting.
     */
    public function catatPembatalanSharingFee(Invoice $billing, int $userId): void
    {
        $this->jurnal->reversal('billing', $billing->id, ['sharing_fee_dokter'], $userId);
    }

    /** @return array{0: float, 1: int, 2: float}|null [nominal, dokter_id, persentase] */
    private function hitungNilaiFee(Invoice $billing): ?array
    {
        $kunjungan = $billing->kunjungan;
        if (!$kunjungan || !$kunjungan->dokter_id) return null;

        $sharingFee = SharingFee::where('dokter_id', $kunjungan->dokter_id)
            ->where('kategori', 'tindakan')
            ->first();
        if (!$sharingFee || (float) $sharingFee->persentase <= 0) return null;

        $totalTindakan = (float) $billing->items->where('jenis', 'tindakan')->sum('subtotal');
        if ($totalTindakan <= 0) return null;

        $nominal = round($totalTindakan * ((float) $sharingFee->persentase / 100), 2);
        if ($nominal <= 0) return null;

        return [$nominal, $kunjungan->dokter_id, (float) $sharingFee->persentase];
    }
}
