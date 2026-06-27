<?php

namespace App\Services\Akuntansi;

use App\Models\{GoodsReceipt, PemakaianBhp, ReturGr, StokOpname};

class InventoriJurnalService
{
    const AKUN = [
        'persediaan_barang' => '1-1300',
        'hutang_dagang'     => '2-1100',
        'hpp_farmasi'       => '5-1100',
        'hpp_tindakan'      => '5-1200',
        'biaya_bhp'         => '5-2100',
        'selisih_opname'    => '8-1100',
    ];

    public function __construct(private JurnalService $jurnal) {}

    public function catatPembelian(GoodsReceipt $gr): void
    {
        foreach ($gr->items as $item) {
            $hargaEfektif = $item->harga_satuan * (1 - ($item->diskon_persen ?? 0) / 100);
            $nilai = $item->jumlah_terima * $hargaEfektif;

            $this->jurnal->catat(
                sumberTipe:    'goods_receipt',
                sumberId:      $gr->id,
                tipeTransaksi: 'masuk_pembelian',
                tanggal:       $gr->tanggal_terima,
                akunDebit:     self::AKUN['persediaan_barang'],
                akunKredit:    self::AKUN['hutang_dagang'],
                nominal:       $nilai,
                keterangan:    "GR {$gr->nomor_gr}: {$item->barang->nama}",
                metadata:      ['barang_id' => $item->barang_id, 'gr_item_id' => $item->id],
            );
        }
    }

    public function catatPemakaianBhp(PemakaianBhp $bhp): void
    {
        foreach ($bhp->items as $item) {
            $this->jurnal->catat(
                sumberTipe:    'pemakaian_bhp',
                sumberId:      $bhp->id,
                tipeTransaksi: 'keluar_bhp',
                tanggal:       $bhp->tanggal_pemakaian,
                akunDebit:     self::AKUN['biaya_bhp'],
                akunKredit:    self::AKUN['persediaan_barang'],
                nominal:       (float) $item->nilai_total,
                keterangan:    "BHP {$bhp->nomor_bhp}: {$item->barang->nama}",
                metadata:      ['barang_id' => $item->barang_id, 'bhp_item_id' => $item->id],
            );
        }
    }

    /** Retur barang ke supplier dari GR yang sudah diverifikasi -- kebalikan catatPembelian(). */
    public function catatReturSupplier(ReturGr $retur): void
    {
        foreach ($retur->items as $item) {
            $this->jurnal->catat(
                sumberTipe:    'retur_gr',
                sumberId:      $retur->id,
                tipeTransaksi: 'retur_ke_supplier',
                tanggal:       $retur->tanggal_retur,
                akunDebit:     self::AKUN['hutang_dagang'],
                akunKredit:    self::AKUN['persediaan_barang'],
                nominal:       (float) $item->subtotal,
                keterangan:    "Retur {$retur->nomor_retur}: {$item->barang->nama} ({$retur->alasan})",
                metadata:      ['barang_id' => $item->barang_id, 'retur_gr_item_id' => $item->id],
            );
        }
    }

    public function catatStokOpname(StokOpname $opname): void
    {
        foreach ($opname->items->where('tipe_selisih', '!=', 'sesuai') as $item) {
            $isDrPersediaan = $item->tipe_selisih === 'lebih';

            $this->jurnal->catat(
                sumberTipe:    'stok_opname',
                sumberId:      $opname->id,
                tipeTransaksi: $isDrPersediaan ? 'penyesuaian_masuk' : 'penyesuaian_keluar',
                tanggal:       $opname->tanggal_opname,
                akunDebit:     $isDrPersediaan ? self::AKUN['persediaan_barang'] : self::AKUN['selisih_opname'],
                akunKredit:    $isDrPersediaan ? self::AKUN['selisih_opname']    : self::AKUN['persediaan_barang'],
                nominal:       (float) $item->nilai_selisih,
                keterangan:    "Opname {$opname->nomor_opname}: {$item->barang->nama} ({$item->tipe_selisih})",
                metadata:      ['barang_id' => $item->barang_id, 'opname_item_id' => $item->id],
            );
        }
    }
}
