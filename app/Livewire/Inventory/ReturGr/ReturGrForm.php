<?php

namespace App\Livewire\Inventory\ReturGr;

use App\Models\GoodsReceipt;
use App\Services\Inventory\ReturGrService;
use Livewire\Component;

class ReturGrForm extends Component
{
    public string $search        = '';
    public array  $grTersedia    = [];
    public int    $goodsReceiptId = 0;
    public array  $items         = [];
    public string $alasan        = '';
    public string $catatan       = '';

    public function updatedSearch(): void
    {
        if (strlen($this->search) < 2) {
            $this->grTersedia = [];
            return;
        }

        $this->grTersedia = GoodsReceipt::where('status', 'diverifikasi')
            ->where(fn ($q) => $q->where('nomor_gr', 'like', "%{$this->search}%")
                ->orWhereHas('supplier', fn ($sq) => $sq->where('nama', 'like', "%{$this->search}%")))
            ->with('supplier:id,nama')
            ->orderByDesc('tanggal_terima')
            ->limit(8)
            ->get(['id', 'nomor_gr', 'supplier_id', 'tanggal_terima'])
            ->map(fn ($gr) => [
                'id'       => $gr->id,
                'nomor_gr' => $gr->nomor_gr,
                'supplier' => $gr->supplier->nama,
                'tanggal'  => $gr->tanggal_terima->format('d/m/Y'),
            ])
            ->toArray();
    }

    public function pilihGr(int $id, ReturGrService $service): void
    {
        $this->goodsReceiptId = $id;
        $this->search         = '';
        $this->grTersedia     = [];

        $gr = GoodsReceipt::with('items.barang')->findOrFail($id);

        $this->items = $gr->items
            ->map(function ($i) use ($service) {
                $sisa = $service->hitungSisaBisaDiretur($i);
                return [
                    'gr_item_id'        => $i->id,
                    'barang_id'         => $i->barang_id,
                    'nama_barang'       => $i->barang->nama,
                    'satuan'            => $i->barang->satuan,
                    'stok_tersedia'     => $i->barang->stok,
                    'sisa_bisa_diretur' => $sisa,
                    'jumlah_retur'      => 0,
                    'harga_satuan'      => (string) $i->harga_satuan,
                    'diskon_persen'     => (string) $i->diskon_persen,
                ];
            })
            ->filter(fn ($i) => $i['sisa_bisa_diretur'] > 0)
            ->values()
            ->toArray();
    }

    public function getTotalNilaiProperty(): float
    {
        return collect($this->items)->sum(fn ($i) =>
            ($i['jumlah_retur'] ?? 0) * ($i['harga_satuan'] ?? 0) * (1 - ($i['diskon_persen'] ?? 0) / 100)
        );
    }

    private function itemDipilih(): array
    {
        return collect($this->items)->filter(fn ($i) => (int) ($i['jumlah_retur'] ?? 0) > 0)->values()->toArray();
    }

    public function simpan(ReturGrService $service): void
    {
        $this->validasiUmum();

        try {
            $retur = $service->buatDraft([
                'goods_receipt_id' => $this->goodsReceiptId,
                'alasan'           => $this->alasan,
                'catatan'          => $this->catatan ?: null,
                'items'            => $this->itemDipilih(),
            ], auth()->id());

            $this->dispatch('notify', type: 'success', message: "Retur {$retur->nomor_retur} berhasil disimpan (draft).");
            $this->redirect(route('inventory.retur-gr.index'));
        } catch (\DomainException $e) {
            $this->addError('items', $e->getMessage());
        }
    }

    public function simpanDanVerifikasi(ReturGrService $service): void
    {
        $this->validasiUmum();

        try {
            $retur = $service->buatDraft([
                'goods_receipt_id' => $this->goodsReceiptId,
                'alasan'           => $this->alasan,
                'catatan'          => $this->catatan ?: null,
                'items'            => $this->itemDipilih(),
            ], auth()->id());

            $service->verifikasi($retur, auth()->id());

            $this->dispatch('notify', type: 'success', message: "Retur {$retur->nomor_retur} diverifikasi. Stok & hutang dagang dikoreksi.");
            $this->redirect(route('inventory.retur-gr.index'));
        } catch (\DomainException $e) {
            $this->addError('items', $e->getMessage());
        }
    }

    private function validasiUmum(): void
    {
        $this->validate([
            'goodsReceiptId' => 'required|exists:goods_receipt,id',
            'alasan'         => 'required|string|max:100',
        ]);

        if (empty($this->itemDipilih())) {
            $this->addError('items', 'Isi jumlah retur minimal untuk satu item.');
            throw new \DomainException('Isi jumlah retur minimal untuk satu item.');
        }
    }

    public function render()
    {
        return view('livewire.inventory.retur-gr.retur-gr-form', [
            'totalNilai' => $this->totalNilai,
        ]);
    }
}
