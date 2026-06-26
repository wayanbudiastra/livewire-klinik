<?php

namespace App\Services\Akuntansi;

use App\Models\TransaksiDeposit;

class DepositJurnalService
{
    const AKUN_KAS             = '1-1100';
    const AKUN_TITIPAN_DEPOSIT = '2-1300';

    public function __construct(private JurnalService $jurnal) {}

    /** Dipanggil dari DepositService::topup() — uang masuk, jadi titipan. */
    public function catatTopup(TransaksiDeposit $trx): void
    {
        $this->jurnal->catat(
            sumberTipe:    'transaksi_deposit',
            sumberId:      $trx->id,
            tipeTransaksi: 'topup_deposit',
            tanggal:       $trx->created_at ?? now(),
            akunDebit:     self::AKUN_KAS,
            akunKredit:    self::AKUN_TITIPAN_DEPOSIT,
            nominal:       (float) $trx->jumlah,
            keterangan:    "Top-up deposit {$trx->nomor_transaksi}",
        );
    }

    /** Dipanggil dari DepositService::refundManual() — titipan dikembalikan tunai. */
    public function catatRefund(TransaksiDeposit $trx): void
    {
        $this->jurnal->catat(
            sumberTipe:    'transaksi_deposit',
            sumberId:      $trx->id,
            tipeTransaksi: 'refund_deposit',
            tanggal:       $trx->created_at ?? now(),
            akunDebit:     self::AKUN_TITIPAN_DEPOSIT,
            akunKredit:    self::AKUN_KAS,
            nominal:       (float) $trx->jumlah,
            keterangan:    "Refund deposit {$trx->nomor_transaksi}",
        );
    }
}
