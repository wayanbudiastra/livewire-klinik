<?php

namespace App\Services\Farmasi;

use App\Models\Barang;
use App\Models\DepositPasien;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\ItemResep;
use App\Models\MutasiStok;
use App\Models\PembayaranSplit;
use App\Models\Racikan;
use App\Models\Resep;
use App\Models\ReturResep;
use App\Models\ReturResepItem;
use App\Models\TransaksiDeposit;
use App\Services\Akuntansi\ReturResepJurnalService;
use App\Services\InvoiceService;
use App\Services\Kasir\SesiKasService;
use Illuminate\Support\Facades\DB;

class ReturResepService
{
    public function __construct(
        private InvoiceService $invoiceService,
        private ReturResepJurnalService $jurnal,
        private SesiKasService $sesiKasService,
    ) {}

    /** Validasi §4.1: resep terlock, invoice lunas, masih di hari pelunasan yang sama. */
    public function cekBolehRetur(Resep $resep): array
    {
        if (! $resep->is_locked) {
            return ['boleh' => false, 'alasan' => 'Resep belum dikonfirmasi apoteker.'];
        }

        $invoice = $resep->kunjungan?->invoice;
        if (! $invoice || $invoice->status !== 'lunas') {
            return ['boleh' => false, 'alasan' => 'Invoice kunjungan ini belum lunas.'];
        }

        $tanggalBayar = $invoice->pembayaranSplit()->latest('tanggal_bayar')->value('tanggal_bayar')
            ?? $invoice->updated_at;

        if (! $tanggalBayar || ! $tanggalBayar->isSameDay(now())) {
            return ['boleh' => false, 'alasan' => 'Retur hanya bisa dilakukan di hari yang sama dengan pelunasan invoice.'];
        }

        return ['boleh' => true, 'alasan' => null];
    }

    /** Sisa kuantitas item resep (non-racikan) yang masih bisa diretur. */
    public function hitungSisaBisaDiretur(ItemResep $item): float
    {
        $sudahDiretur = ReturResepItem::where('item_resep_id', $item->id)->sum('jumlah_retur');
        return max(0, (float) $item->jumlah - (float) $sudahDiretur);
    }

    /** Racikan diretur sebagai satu kesatuan (lihat PRD §9.6) -- true kalau belum pernah diretur. */
    public function racikanBisaDiretur(Racikan $racikan): bool
    {
        return ! ReturResepItem::where('racikan_id', $racikan->id)->exists();
    }

    /**
     * Proses retur resep one-shot (lihat PRD §4.2).
     *
     * @param array{resep_id:int,alasan:string,catatan:?string,metode_pengembalian:string,
     *              items: array<int,array{item_resep_id:?int,racikan_id:?int,barang_id:int,jumlah_retur:float,harga_satuan:float}>} $data
     */
    public function proses(array $data, int $userId): ReturResep
    {
        $resep = Resep::with(['kunjungan.invoice', 'itemResep.barang', 'racikan.bahanRacikan.barang'])
            ->findOrFail($data['resep_id']);

        $cek = $this->cekBolehRetur($resep);
        if (! $cek['boleh']) {
            throw new \DomainException($cek['alasan']);
        }

        $items = $data['items'] ?? [];
        if (empty($items)) {
            throw new \DomainException('Pilih minimal satu item untuk diretur.');
        }

        $invoice = $resep->kunjungan->invoice;
        $metode  = $data['metode_pengembalian'];

        $sesiKas = null;
        if (in_array($metode, ['tunai', 'bank'], true)) {
            $sesiKas = $this->sesiKasService->getSesiAktif($userId);
            if (! $sesiKas) {
                throw new \DomainException('Buka sesi kas terlebih dahulu untuk mengembalikan dana tunai/bank.');
            }
        }

        // Validasi sisa kuantitas per item sebelum eksekusi apa pun.
        foreach ($items as $row) {
            if (! empty($row['item_resep_id'])) {
                $itemResep = ItemResep::findOrFail($row['item_resep_id']);
                $sisa      = $this->hitungSisaBisaDiretur($itemResep);
                if ($row['jumlah_retur'] > $sisa) {
                    throw new \DomainException("Jumlah retur {$itemResep->barang->nama} melebihi sisa yang bisa diretur ({$sisa}).");
                }
            } elseif (! empty($row['racikan_id'])) {
                $racikan = Racikan::findOrFail($row['racikan_id']);
                if (! $this->racikanBisaDiretur($racikan)) {
                    throw new \DomainException("Racikan {$racikan->nama_racikan} sudah pernah diretur sebelumnya.");
                }
            }
        }

        return DB::transaction(function () use ($resep, $items, $invoice, $metode, $sesiKas, $data, $userId) {
            $retur = ReturResep::create([
                'nomor_retur'          => ReturResep::generateNomorRetur(),
                'resep_id'             => $resep->id,
                'kunjungan_id'         => $resep->kunjungan_id,
                'billing_id'           => $invoice->id,
                'tanggal_retur'        => now()->toDateString(),
                'alasan'               => $data['alasan'],
                'catatan'              => $data['catatan'] ?? null,
                'metode_pengembalian'  => $metode,
                'sesi_kas_id'          => $sesiKas?->id,
                'diproses_oleh'        => $userId,
            ]);

            $totalNilai = 0;

            foreach ($items as $row) {
                $barang   = Barang::lockForUpdate()->findOrFail($row['barang_id']);
                $jumlah   = (float) $row['jumlah_retur'];
                $subtotal = $jumlah * (float) $row['harga_satuan'];
                $totalNilai += $subtotal;

                $stokSebelum = $barang->stok;
                $barang->increment('stok', $jumlah);

                MutasiStok::create([
                    'barang_id'      => $barang->id,
                    'user_id'        => $userId,
                    'tipe'           => 'retur_resep',
                    'jumlah'         => $jumlah,
                    'stok_sebelum'   => $stokSebelum,
                    'stok_sesudah'   => $stokSebelum + $jumlah,
                    'hpr_sebelum'    => $barang->harga_pokok,
                    'hpr_sesudah'    => $barang->harga_pokok,
                    'referensi_tipe' => 'retur_resep',
                    'referensi_id'   => $retur->id,
                    'keterangan'     => "Retur {$retur->nomor_retur}: {$barang->nama} ({$retur->alasan})",
                ]);

                $retur->items()->create([
                    'item_resep_id' => $row['item_resep_id'] ?? null,
                    'racikan_id'    => $row['racikan_id'] ?? null,
                    'barang_id'     => $barang->id,
                    'jumlah_retur'  => $jumlah,
                    'harga_satuan'  => $row['harga_satuan'],
                    'subtotal'      => $subtotal,
                ]);

                $this->kurangiInvoiceItem($invoice, $row, $jumlah);
            }

            $retur->update(['total_nilai_retur' => $totalNilai]);

            $this->invoiceService->recalcTotal($invoice);

            $this->prosesPengembalianDana($retur, $invoice, $metode, $sesiKas, $userId);

            $this->invoiceService->recalcTotal($invoice);

            $this->jurnal->catatRetur($retur->fresh('items'));

            activity('farmasi')
                ->performedOn($retur)
                ->causedBy(auth()->user())
                ->withProperties(['nomor_retur' => $retur->nomor_retur, 'total_nilai_retur' => $totalNilai])
                ->log('Retur resep diproses');

            return $retur->fresh(['items.barang']);
        });
    }

    /** Kurangi/hapus invoice_item terkait sesuai jumlah yang diretur. */
    private function kurangiInvoiceItem(Invoice $invoice, array $row, float $jumlahRetur): void
    {
        if (! empty($row['racikan_id'])) {
            // Racikan diretur sebagai satu kesatuan -- hapus baris invoice_item-nya.
            InvoiceItem::where('billing_id', $invoice->id)
                ->where('jenis', 'racikan')
                ->where('ref_id', $row['racikan_id'])
                ->delete();
            return;
        }

        $invoiceItem = InvoiceItem::where('billing_id', $invoice->id)
            ->where('jenis', 'obat')
            ->where('ref_id', $row['item_resep_id'])
            ->first();

        if (! $invoiceItem) return;

        $qtyBaru = (float) $invoiceItem->qty - $jumlahRetur;

        if ($qtyBaru <= 0) {
            $invoiceItem->delete();
            return;
        }

        $invoiceItem->update([
            'qty'      => $qtyBaru,
            'subtotal' => max(0, $qtyBaru * (float) $invoiceItem->harga_satuan - (float) $invoiceItem->diskon_item),
        ]);
    }

    private function prosesPengembalianDana(ReturResep $retur, Invoice $invoice, string $metode, $sesiKas, int $userId): void
    {
        if ($metode === 'deposit') {
            $pasien = $retur->kunjungan->pasien;

            $deposit = DepositPasien::firstOrCreate(
                ['pasien_id' => $pasien->id],
                ['saldo' => 0, 'total_topup' => 0, 'total_terpakai' => 0]
            );

            $saldoSebelum = (float) $deposit->saldo;
            $deposit->increment('saldo', (float) $retur->total_nilai_retur);

            TransaksiDeposit::create([
                'pasien_id'       => $pasien->id,
                'user_id'         => $userId,
                'nomor_transaksi' => 'TD-' . now()->format('Ymd') . '-' . str_pad((string) (TransaksiDeposit::count() + 1), 4, '0', STR_PAD_LEFT),
                'tipe'            => 'koreksi',
                'jumlah'          => $retur->total_nilai_retur,
                'saldo_sebelum'   => $saldoSebelum,
                'saldo_sesudah'   => $saldoSebelum + (float) $retur->total_nilai_retur,
                'referensi_tipe'  => 'retur_resep',
                'referensi_id'    => $retur->id,
                'keterangan'      => "Konversi retur resep {$retur->nomor_retur} ke saldo deposit",
            ]);

            return;
        }

        // Tunai / Bank — baris pembayaran_split negatif, otomatis ikut rekap sesi kas.
        PembayaranSplit::create([
            'billing_id'    => $invoice->id,
            'sesi_kas_id'   => $sesiKas?->id,
            'user_id'       => $userId,
            'metode'        => $metode === 'bank' ? 'transfer' : 'tunai',
            'jumlah'        => -1 * (float) $retur->total_nilai_retur,
            'referensi'     => "retur_resep:{$retur->id}",
            'tanggal_bayar' => now(),
        ]);
    }
}
