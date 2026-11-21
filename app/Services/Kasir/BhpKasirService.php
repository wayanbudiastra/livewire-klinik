<?php

namespace App\Services\Kasir;

use App\Models\Barang;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Akuntansi\JurnalPending;
use App\Models\MutasiStok;
use App\Models\PemakaianAlkes;
use App\Services\Akuntansi\JurnalService;
use App\Services\Harga\TarifResolver;
use App\Services\InvoiceService;
use Illuminate\Support\Facades\DB;

/**
 * BHP (bahan habis pakai) dan OBAT (non-racikan) yang diinput kasir dari panel
 * "Tambah Komponen Tagihan" (docs/PERANCANGAN_BHP_KASIR.md, docs/PERANCANGAN_OBAT_KASIR.md).
 *
 * - Disimpan sebagai pemakaian_alkes (sumber 'kasir') + baris invoice_item 'alkes',
 *   sehingga tidak hilang saat tagihan dimuat ulang (lihat InvoiceService::buildItems).
 * - Stok TIDAK dipotong saat ditambahkan; baru dipotong saat tagihan lunas
 *   (potongStokSaatLunas, dipanggil di dalam transaksi pembayaran).
 * - Pembatalan billing mengembalikan stok & membalik jurnal HPP.
 * - Qty selalu bilangan bulat (kolom jumlah/stok integer).
 */
class BhpKasirService
{
    const AKUN_HPP        = '5-1100'; // HPP Farmasi (Obat & Alkes Terjual)
    const AKUN_PERSEDIAAN = '1-1300';
    const TIPE_JURNAL_HPP = 'hpp_bhp_kasir';

    public function __construct(
        private InvoiceService $invoiceService,
        private JurnalService $jurnal,
    ) {}

    const JENIS_BHP  = 'bahan_habis_pakai';
    const JENIS_OBAT = 'obat';

    /** Barang aktif berjenis $jenis (BHP atau obat) yang bisa dipilih kasir (stok > 0). */
    public function daftar(?string $q, bool $isWna, int $limit = 40, string $jenis = self::JENIS_BHP): array
    {
        return Barang::where('jenis', $jenis)
            ->where('is_active', true)
            ->where('stok', '>', 0)
            ->when($q, fn ($query) => $query->where(
                fn ($w) => $w->where('nama', 'like', "%{$q}%")->orWhere('kode', 'like', "%{$q}%")
            ))
            ->orderBy('nama')
            ->limit($limit)
            ->get()
            ->map(fn (Barang $b) => [
                'id'     => $b->id,
                'nama'   => $b->nama,
                'harga'  => $this->hargaJual($b, $isWna),
                'satuan' => $b->satuan,
                'info'   => 'Stok ' . $b->stok,
            ])
            ->toArray();
    }

    public function tambah(Invoice $invoice, int $barangId, int $qty, int $userId, string $jenis = self::JENIS_BHP): InvoiceItem
    {
        if (! in_array($jenis, [self::JENIS_BHP, self::JENIS_OBAT], true)) {
            throw new \DomainException('Jenis barang tidak valid.');
        }
        if ($invoice->status !== 'belum_bayar') {
            throw new \DomainException('Tagihan sudah diproses, BHP tidak dapat ditambahkan.');
        }
        if ($qty < 1) {
            throw new \DomainException('Qty minimal 1.');
        }

        $barang = Barang::find($barangId);
        if (! $barang || $barang->jenis !== $jenis || ! $barang->is_active) {
            throw new \DomainException($jenis === self::JENIS_OBAT ? 'Barang bukan obat aktif.' : 'Barang bukan BHP aktif.');
        }

        return DB::transaction(function () use ($invoice, $barang, $qty, $userId) {
            $kunjungan = $invoice->kunjungan;
            $isWna     = $kunjungan?->pasien?->tipe_pasien === 'WNA';

            // Baris kasir yang sama (belum dipotong) digabung, bukan diduplikasi.
            $row = PemakaianAlkes::where('kunjungan_id', $invoice->kunjungan_id)
                ->where('barang_id', $barang->id)
                ->where('sumber', 'kasir')
                ->whereNull('stok_dipotong_at')
                ->lockForUpdate()
                ->first();

            $qtyBaru = $qty + (int) ($row?->jumlah ?? 0);
            $this->pastikanStokCukup($barang, $qtyBaru);

            if ($row) {
                $row->update(['jumlah' => $qtyBaru]);
            } else {
                $row = PemakaianAlkes::create([
                    'kunjungan_id' => $invoice->kunjungan_id,
                    'barang_id'    => $barang->id,
                    'jumlah'       => $qtyBaru,
                    'sumber'       => 'kasir',
                    'dicatat_oleh' => $userId,
                ]);
            }

            $harga = $this->hargaJual($barang, $isWna);
            $item  = $invoice->items()->where('jenis', 'alkes')->where('ref_id', $row->id)->first();

            if ($item) {
                $bruto = $harga * $qtyBaru;
                $diskon = $item->diskon_tipe === 'persen'
                    ? ($bruto * max(0, min(100, (float) $item->diskon_nilai))) / 100
                    : min((float) $item->diskon_nilai, $bruto);
                $item->update([
                    'qty' => $qtyBaru, 'diskon_item' => $diskon, 'subtotal' => max(0, $bruto - $diskon),
                ]);
            } else {
                $item = $invoice->items()->create([
                    'jenis'        => 'alkes',
                    'ref_id'       => $row->id,
                    'nama_item'    => $barang->nama,
                    'qty'          => $qtyBaru,
                    'satuan'       => $barang->satuan,
                    'harga_satuan' => $harga,
                    'diskon_item'  => 0,
                    'diskon_tipe'  => 'nominal',
                    'diskon_nilai' => 0,
                    'subtotal'     => $harga * $qtyBaru,
                ]);
            }

            $this->invoiceService->recalcTotal($invoice);

            return $item;
        });
    }

    /** Baris pemakaian_alkes kasir (belum dipotong) milik item tagihan ini, jika ada. */
    public function barisKasir(InvoiceItem $item): ?PemakaianAlkes
    {
        if ($item->jenis !== 'alkes' || ! $item->ref_id) return null;

        return PemakaianAlkes::where('id', $item->ref_id)
            ->where('sumber', 'kasir')
            ->whereNull('stok_dipotong_at')
            ->first();
    }

    /** Validasi & simpan qty baru (bilangan bulat, tidak melebihi stok) untuk item BHP kasir. */
    public function ubahQty(InvoiceItem $item, float $qty): void
    {
        $row = $this->barisKasir($item);
        if (! $row) return;

        if ($qty < 1 || abs($qty - round($qty)) > 0.0001) {
            throw new \DomainException('Qty BHP harus bilangan bulat minimal 1.');
        }

        $this->pastikanStokCukup(Barang::findOrFail($row->barang_id), (int) round($qty));
        $row->update(['jumlah' => (int) round($qty)]);
    }

    public function hapus(InvoiceItem $item): void
    {
        $this->barisKasir($item)?->delete();
    }

    /**
     * Potong stok BHP kasir saat tagihan lunas. HARUS dipanggil di dalam DB::transaction
     * pembayaran, setelah status billing menjadi 'lunas'. Stok kurang → DomainException
     * (seluruh pembayaran dibatalkan). Idempoten lewat stok_dipotong_at.
     */
    public function potongStokSaatLunas(Invoice $billing, int $userId): void
    {
        $billing->loadMissing('items');

        $totalHpp = 0.0;

        foreach ($billing->items->where('jenis', 'alkes') as $item) {
            $row = PemakaianAlkes::where('id', $item->ref_id)
                ->where('sumber', 'kasir')
                ->whereNull('stok_dipotong_at')
                ->lockForUpdate()
                ->first();
            if (! $row) continue;

            $qty    = (int) round((float) $item->qty);
            $barang = Barang::pastikanCukup($row->barang_id, (float) $qty);
            $sebelum = (int) $barang->stok;

            $barang->decrement('stok', $qty);

            MutasiStok::create([
                'barang_id'      => $barang->id,
                'user_id'        => $userId,
                // Obat dijual langsung = penjualan ritel; BHP = pemakaian pada tindakan.
                'tipe'           => $barang->jenis === self::JENIS_OBAT ? 'keluar_ritel' : 'keluar_tindakan',
                'jumlah'         => $qty,
                'stok_sebelum'   => $sebelum,
                'stok_sesudah'   => $sebelum - $qty,
                'hpr_sebelum'    => $barang->harga_pokok,
                'hpr_sesudah'    => $barang->harga_pokok,
                'referensi_tipe' => 'pemakaian_alkes',
                'referensi_id'   => $row->id,
                'keterangan'     => ($barang->jenis === self::JENIS_OBAT ? 'Obat' : 'BHP') . " kasir {$billing->nomor_invoice}",
            ]);

            $row->update(['jumlah' => $qty, 'stok_dipotong_at' => now()]);
            $totalHpp += $qty * (float) $barang->harga_pokok;
        }

        if ($totalHpp > 0) {
            $this->jurnal->catat(
                sumberTipe:    'billing',
                sumberId:      $billing->id,
                tipeTransaksi: self::TIPE_JURNAL_HPP,
                tanggal:       now(),
                akunDebit:     self::AKUN_HPP,
                akunKredit:    self::AKUN_PERSEDIAAN,
                nominal:       $totalHpp,
                keterangan:    "HPP BHP/obat kasir {$billing->nomor_invoice}",
            );
        }
    }

    /**
     * Billing lunas dibatalkan: kembalikan stok BHP kasir yang sudah dipotong dan balik jurnal HPP.
     * Dipanggil di dalam transaksi pembatalan. stok_dipotong_at dikosongkan agar invoice
     * pengganti (-R1) memotong lagi saat lunas.
     */
    public function kembalikanStokSaatBatal(Invoice $billing, int $userId): void
    {
        $rows = PemakaianAlkes::where('kunjungan_id', $billing->kunjungan_id)
            ->where('sumber', 'kasir')
            ->whereNotNull('stok_dipotong_at')
            ->lockForUpdate()
            ->get();

        foreach ($rows as $row) {
            $barang  = Barang::lockForUpdate()->findOrFail($row->barang_id);
            $sebelum = (int) $barang->stok;
            $qty     = (int) $row->jumlah;

            $barang->increment('stok', $qty);

            MutasiStok::create([
                'barang_id'      => $barang->id,
                'user_id'        => $userId,
                'tipe'           => 'penyesuaian_masuk',
                'jumlah'         => $qty,
                'stok_sebelum'   => $sebelum,
                'stok_sesudah'   => $sebelum + $qty,
                'hpr_sebelum'    => $barang->harga_pokok,
                'hpr_sesudah'    => $barang->harga_pokok,
                'referensi_tipe' => 'pemakaian_alkes',
                'referensi_id'   => $row->id,
                'keterangan'     => "Pembatalan billing {$billing->nomor_invoice} (BHP/obat kasir)",
            ]);

            $row->update(['stok_dipotong_at' => null]);
        }

        $adaJurnal = JurnalPending::where('sumber_tipe', 'billing')
            ->where('sumber_id', $billing->id)
            ->where('tipe_transaksi', self::TIPE_JURNAL_HPP)
            ->exists();

        if ($adaJurnal) {
            $this->jurnal->reversal('billing', $billing->id, [self::TIPE_JURNAL_HPP], $userId);
        }
    }

    private function pastikanStokCukup(Barang $barang, int $qty): void
    {
        if ($barang->stok < $qty) {
            throw new \DomainException(
                "Stok {$barang->nama} tidak mencukupi. Tersedia: {$barang->stok} {$barang->satuan}, diminta: {$qty}."
            );
        }
    }

    private function hargaJual(Barang $barang, bool $isWna): float
    {
        // Sama dengan InvoiceService::buildItems untuk 'alkes'.
        return TarifResolver::pilih(
            (float) $barang->harga_jual,
            null,
            $barang->harga_wna !== null ? (float) $barang->harga_wna : null,
            false,
            $isWna,
        );
    }
}
