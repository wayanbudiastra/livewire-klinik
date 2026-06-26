<?php

namespace App\Services\Akuntansi;

use App\Models\TransaksiRitel;

class RitelJurnalService
{
    const AKUN_KAS              = '1-1100';
    const AKUN_BANK              = '1-1200';
    const AKUN_PERSEDIAAN_BARANG = '1-1300';
    const AKUN_PENDAPATAN_OBAT   = '4-1300';
    const AKUN_HPP_FARMASI       = '5-1100';

    public function __construct(private JurnalService $jurnal) {}

    /** Dipanggil saat status ritel -> 'dibayar' (ObatRitelService::prosesBayar). */
    public function catatPenjualan(TransaksiRitel $tr): void
    {
        $akunDebit = $tr->metode_bayar === 'tunai' ? self::AKUN_KAS : self::AKUN_BANK;

        $this->jurnal->catat(
            sumberTipe:    'transaksi_ritel',
            sumberId:      $tr->id,
            tipeTransaksi: 'penjualan_ritel',
            tanggal:       $tr->dibayar_at ?? now(),
            akunDebit:     $akunDebit,
            akunKredit:    self::AKUN_PENDAPATAN_OBAT,
            nominal:       (float) $tr->total_bayar,
            keterangan:    "Penjualan ritel {$tr->nomor_ritel}",
            metadata:      ['metode_bayar' => $tr->metode_bayar],
        );
    }

    /** Dipanggil saat obat diserahkan & stok dipotong (ObatRitelService::serahkanObat). */
    public function catatHpp(TransaksiRitel $tr): void
    {
        $tr->loadMissing('items.barang');

        $totalHpp = (float) $tr->items->sum(
            fn ($item) => $item->jumlah * (float) ($item->barang->harga_pokok ?? 0)
        );

        if ($totalHpp <= 0) return;

        $this->jurnal->catat(
            sumberTipe:    'transaksi_ritel',
            sumberId:      $tr->id,
            tipeTransaksi: 'hpp_ritel',
            tanggal:       $tr->diserahkan_at ?? now(),
            akunDebit:     self::AKUN_HPP_FARMASI,
            akunKredit:    self::AKUN_PERSEDIAAN_BARANG,
            nominal:       $totalHpp,
            keterangan:    "HPP penjualan ritel {$tr->nomor_ritel}",
        );
    }
}
