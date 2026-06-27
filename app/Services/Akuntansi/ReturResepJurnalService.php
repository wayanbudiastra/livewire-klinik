<?php

namespace App\Services\Akuntansi;

use App\Models\ReturResep;

class ReturResepJurnalService
{
    const AKUN_KAS              = '1-1100';
    const AKUN_BANK              = '1-1200';
    const AKUN_TITIPAN_DEPOSIT   = '2-1300';
    const AKUN_PENDAPATAN_OBAT   = '4-1300';

    public function __construct(private JurnalService $jurnal) {}

    /**
     * Debit Pendapatan Obat/Racikan (keduanya satu akun di COA ini) / Kredit
     * Kas-Bank-atau-Titipan Deposit sesuai metode pengembalian (lihat PRD §4.4).
     * Tidak ada jurnal HPP/Persediaan -- dispensing resep saat ini juga tidak
     * mencatat jurnal HPP (gap terpisah, lihat PRD §9.5).
     */
    public function catatRetur(ReturResep $retur): void
    {
        $akunKredit = match ($retur->metode_pengembalian) {
            'tunai'   => self::AKUN_KAS,
            'bank'    => self::AKUN_BANK,
            'deposit' => self::AKUN_TITIPAN_DEPOSIT,
            default   => self::AKUN_KAS,
        };

        $this->jurnal->catat(
            sumberTipe:    'retur_resep',
            sumberId:      $retur->id,
            tipeTransaksi: 'retur_resep',
            tanggal:       $retur->tanggal_retur,
            akunDebit:     self::AKUN_PENDAPATAN_OBAT,
            akunKredit:    $akunKredit,
            nominal:       (float) $retur->total_nilai_retur,
            keterangan:    "Retur resep {$retur->nomor_retur} ({$retur->alasan}) — {$retur->metode_pengembalian}",
            metadata:      ['resep_id' => $retur->resep_id, 'billing_id' => $retur->billing_id],
        );
    }
}
