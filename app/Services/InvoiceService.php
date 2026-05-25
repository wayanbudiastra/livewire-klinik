<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Kunjungan;
use App\Models\SesiKas;

class InvoiceService
{
    /**
     * Build invoice item rows from all clinical modules for a kunjungan.
     */
    public function buildItems(Kunjungan $kunjungan): array
    {
        $isBpjs = $kunjungan->tipe_pembayaran === 'bpjs';
        $items  = [];

        // 1. Tindakan (procedures)
        foreach ($kunjungan->tindakan()->with('masterTindakan')->get() as $t) {
            $tarif = $isBpjs
                ? $t->masterTindakan->tarif_bpjs
                : $t->masterTindakan->tarif;
            $subtotal = $tarif * $t->jumlah;
            $items[] = [
                'jenis'        => 'tindakan',
                'ref_id'       => $t->id,
                'nama_item'    => $t->masterTindakan->nama,
                'qty'          => $t->jumlah,
                'satuan'       => 'tindakan',
                'harga_satuan' => $tarif,
                'diskon_item'  => 0,
                'subtotal'     => $subtotal,
            ];
        }

        // 2. BMHP / Alkes consumables
        foreach ($kunjungan->pemakaianAlkes()->with('obat')->get() as $a) {
            $harga    = $a->obat->harga;
            $subtotal = $harga * $a->jumlah;
            $items[] = [
                'jenis'        => 'alkes',
                'ref_id'       => $a->id,
                'nama_item'    => $a->obat->nama,
                'qty'          => $a->jumlah,
                'satuan'       => $a->obat->satuan,
                'harga_satuan' => $harga,
                'diskon_item'  => 0,
                'subtotal'     => $subtotal,
            ];
        }

        // 3. Penunjang (lab / radiology) — only selesai orders
        foreach (
            $kunjungan->permintaanPenunjang()
                ->with('itemPenunjang')
                ->where('status', 'selesai')
                ->get() as $p
        ) {
            $tarif    = $isBpjs ? $p->itemPenunjang->tarif_bpjs : $p->itemPenunjang->tarif;
            $subtotal = $tarif * $p->jumlah;
            $items[] = [
                'jenis'        => 'penunjang',
                'ref_id'       => $p->id,
                'nama_item'    => $p->itemPenunjang->nama,
                'qty'          => $p->jumlah,
                'satuan'       => 'pemeriksaan',
                'harga_satuan' => $tarif,
                'diskon_item'  => 0,
                'subtotal'     => $subtotal,
            ];
        }

        // 4. Obat dari resep yang sudah dikonfirmasi apoteker (is_locked = true)
        foreach (
            $kunjungan->resep()
                ->where('is_locked', true)
                ->with('itemResep.obat', 'racikan.bahanRacikan.obat')
                ->get() as $resep
        ) {
            // Non-racikan items
            foreach ($resep->itemResep as $ir) {
                $harga    = $isBpjs ? $ir->obat->harga_bpjs : $ir->obat->harga;
                $subtotal = $harga * $ir->jumlah;
                $items[] = [
                    'jenis'        => 'obat',
                    'ref_id'       => $ir->id,
                    'nama_item'    => $ir->obat->nama,
                    'qty'          => $ir->jumlah,
                    'satuan'       => $ir->obat->satuan,
                    'harga_satuan' => $harga,
                    'diskon_item'  => 0,
                    'subtotal'     => $subtotal,
                ];
            }

            // Racikan — price = total cost of all bahan
            foreach ($resep->racikan as $racikan) {
                $totalBahan = $racikan->bahanRacikan->sum(
                    fn ($b) => $b->obat->harga * $b->jumlah
                );
                $items[] = [
                    'jenis'        => 'racikan',
                    'ref_id'       => $racikan->id,
                    'nama_item'    => $racikan->nama_racikan . ' (racikan)',
                    'qty'          => 1,
                    'satuan'       => 'racikan',
                    'harga_satuan' => $totalBahan,
                    'diskon_item'  => 0,
                    'subtotal'     => $totalBahan,
                ];
            }
        }

        return $items;
    }

    /**
     * Create a new invoice or refresh an existing unpaid one.
     * Manual items added by the cashier are preserved on refresh.
     */
    public function createOrRefresh(Kunjungan $kunjungan, SesiKas $sesiKas): Invoice
    {
        $invoice = Invoice::firstOrNew(['kunjungan_id' => $kunjungan->id]);

        // Never touch a paid invoice
        if ($invoice->exists && $invoice->status === 'lunas') {
            return $invoice->load('items');
        }

        // Reactivate a cancelled invoice: clear old payment splits and reset to fresh state.
        // A new invoice cannot be created (kunjungan_id is unique), so the same row is reused.
        if ($invoice->exists && $invoice->status === 'dibatalkan') {
            $invoice->pembayaranSplit()->delete();
            $invoice->forceFill([
                'nomor_invoice'        => $this->generateNomor($kunjungan->id),
                'sesi_kas_id'          => $sesiKas->id,
                'diskon_global'        => 0,
                'total_bayar'          => 0,
                'total_deposit_dipakai'=> 0,
                'status'               => 'belum_bayar',
                'cancel_reason'        => null,
                'cancelled_by'         => null,
                'dibatalkan_pada'      => null,
            ]);
        }

        $clinicalItems = $this->buildItems($kunjungan);
        $totalTagihan  = collect($clinicalItems)->sum('subtotal');

        if (! $invoice->exists) {
            $invoice->nomor_invoice = $this->generateNomor($kunjungan->id);
            $invoice->sesi_kas_id   = $sesiKas->id;
            $invoice->diskon_global = 0;
            $invoice->status        = 'belum_bayar';
            $invoice->total_tagihan = $totalTagihan;
            $invoice->total_bayar   = 0;
            $invoice->sisa          = $totalTagihan;
        }

        $invoice->save();

        // Replace all non-manual items
        $invoice->items()->where('jenis', '!=', 'manual')->delete();
        foreach ($clinicalItems as $itemData) {
            $invoice->items()->create($itemData);
        }

        $this->recalcTotal($invoice);

        return $invoice->fresh(['items']);
    }

    /**
     * Recalculate total_tagihan, total_bayar, sisa after item/discount changes.
     */
    public function recalcTotal(Invoice $invoice): void
    {
        $invoice->load('items', 'pembayaran', 'pembayaranSplit');

        $subtotalItems  = $invoice->items->sum(fn ($i) => $i->subtotal - $i->diskon_item);
        $totalTagihan   = max(0, $subtotalItems - $invoice->diskon_global);
        $totalBayar     = $invoice->pembayaran->sum('jumlah')
                        + $invoice->pembayaranSplit->sum('jumlah');
        $sisa           = max(0, $totalTagihan - $totalBayar);

        $invoice->total_tagihan = $totalTagihan;
        $invoice->total_bayar   = $totalBayar;
        $invoice->sisa          = $sisa;
        $invoice->save();
    }

    private function generateNomor(int $kunjunganId): string
    {
        $tanggal = now()->format('Ymd');
        $seq     = str_pad($kunjunganId, 5, '0', STR_PAD_LEFT);
        return "INV-{$tanggal}-{$seq}";
    }
}
