<?php

namespace App\Livewire\Inventory\PurchaseOrder;

use App\Models\{Barang, PurchaseOrder, Supplier, SupplierBarang};
use App\Services\Inventory\PembelianService;
use Livewire\Component;

class PoForm extends Component
{
    public int    $supplierId             = 0;
    public string $tanggalPo              = '';
    public string $tanggalKirimEstimasi   = '';
    public string $catatan                = '';
    public array  $items                  = [];
    public array  $hasilSearch            = [];
    public string $searchBarang           = '';

    // Mapping grid state
    public array $barangMapping   = [];
    public array $selectedMapping = [];
    public bool  $showMappingGrid = false;

    public function mount(): void
    {
        $this->tanggalPo = now()->format('Y-m-d');
    }

    public function updatedSupplierId($value): void
    {
        $this->items          = [];
        $this->searchBarang   = '';
        $this->selectedMapping = [];
        $this->showMappingGrid = false;

        if (!$value) {
            $this->barangMapping = [];
            return;
        }

        $this->barangMapping = SupplierBarang::with('barang:id,kode,nama,satuan,stok,stok_minimum')
            ->where('supplier_id', $value)
            ->get()
            ->map(fn ($sb) => [
                'barang_id'            => $sb->barang_id,
                'kode'                 => $sb->barang->kode,
                'nama'                 => $sb->barang->nama,
                'satuan'               => $sb->barang->satuan,
                'stok_saat_ini'        => $sb->barang->stok,
                'stok_minimum'         => $sb->barang->stok_minimum,
                'kode_barang_supplier' => $sb->kode_barang_supplier,
                'harga_terakhir'       => $sb->harga_terakhir ?? 0,
            ])
            ->values()
            ->toArray();

        $this->showMappingGrid = !empty($this->barangMapping);
    }

    public function toggleMappingItem(int $barangId): void
    {
        if (isset($this->selectedMapping[$barangId])) {
            unset($this->selectedMapping[$barangId]);
        } else {
            $item = collect($this->barangMapping)->firstWhere('barang_id', $barangId);
            $this->selectedMapping[$barangId] = [
                'jumlah' => 1,
                'harga'  => $item['harga_terakhir'],
                'diskon' => 0,
            ];
        }
    }

    public function tambahDariMapping(): void
    {
        foreach ($this->selectedMapping as $barangId => $detail) {
            if ((float) ($detail['jumlah'] ?? 0) <= 0) continue;

            $exists = collect($this->items)->firstWhere('barang_id', $barangId);
            if ($exists) continue;

            $barang = Barang::find($barangId);
            if (!$barang) continue;

            $jumlah = (float) $detail['jumlah'];
            $harga  = (float) $detail['harga'];
            $diskon = (float) $detail['diskon'];

            $this->items[] = [
                'barang_id'     => $barangId,
                'nama_barang'   => $barang->nama,
                'satuan'        => $barang->satuan,
                'jumlah_pesan'  => $jumlah,
                'harga_satuan'  => $harga,
                'diskon_persen' => $diskon,
                'subtotal'      => $jumlah * $harga * (1 - $diskon / 100),
            ];
        }

        $this->selectedMapping = [];
        $this->showMappingGrid = false;
    }

    public function updatedSearchBarang(): void
    {
        if (strlen($this->searchBarang) < 2) {
            $this->hasilSearch = [];
            return;
        }

        $this->hasilSearch = Barang::where('is_active', true)
            ->when($this->supplierId, fn ($q) => $q->whereHas('suppliers', fn ($sq) => $sq->where('supplier_id', $this->supplierId)))
            ->where(fn ($q) => $q->where('nama', 'like', "%{$this->searchBarang}%")->orWhere('kode', 'like', "%{$this->searchBarang}%"))
            ->with(['supplierBarang' => fn ($q) => $q->when($this->supplierId, fn ($sq) => $sq->where('supplier_id', $this->supplierId))])
            ->limit(8)
            ->get()
            ->map(fn ($b) => [
                'id'            => $b->id,
                'kode'          => $b->kode,
                'nama'          => $b->nama,
                'satuan'        => $b->satuan,
                'stok'          => $b->stok,
                'stok_minimum'  => $b->stok_minimum,
                'harga_terakhir'=> optional($b->supplierBarang->first())->harga_terakhir ?? 0,
            ])
            ->toArray();
    }

    public function addItem(int $barangId): void
    {
        if (collect($this->items)->pluck('barang_id')->contains($barangId)) return;
        $found = collect($this->hasilSearch)->firstWhere('id', $barangId);
        if (!$found) return;

        $this->items[] = [
            'barang_id'     => $found['id'],
            'nama_barang'   => $found['nama'],
            'satuan'        => $found['satuan'],
            'jumlah_pesan'  => 1,
            'harga_satuan'  => $found['harga_terakhir'],
            'diskon_persen' => 0,
            'subtotal'      => $found['harga_terakhir'],
        ];
        $this->searchBarang = '';
        $this->hasilSearch  = [];
    }

    public function removeItem(int $i): void
    {
        array_splice($this->items, $i, 1);
    }

    public function updatedItems(): void
    {
        foreach ($this->items as $i => $item) {
            $this->items[$i]['subtotal'] = (float) ($item['harga_satuan'] ?? 0)
                * (float) ($item['jumlah_pesan'] ?? 0)
                * (1 - (float) ($item['diskon_persen'] ?? 0) / 100);
        }
    }

    public function getTotalNilaiProperty(): float
    {
        return collect($this->items)->sum('subtotal');
    }

    public function save(PembelianService $service): void
    {
        $this->validate([
            'supplierId'              => 'required|exists:supplier,id',
            'tanggalPo'               => 'required|date',
            'items'                   => 'required|array|min:1',
            'items.*.jumlah_pesan'    => 'required|numeric|min:0.01',
            'items.*.harga_satuan'    => 'required|numeric|min:0',
        ]);

        $po = $service->buatPo([
            'supplier_id'           => $this->supplierId,
            'tanggal_po'            => $this->tanggalPo,
            'tanggal_kirim_estimasi'=> $this->tanggalKirimEstimasi ?: null,
            'catatan'               => $this->catatan ?: null,
            'dibuat_oleh'           => auth()->id(),
            'total_nilai'           => $this->totalNilai,
            'items'                 => $this->items,
        ]);

        $this->dispatch('notify', type: 'success', message: "PO {$po->nomor_po} berhasil dibuat.");
        $this->redirect(route('inventory.po.index'));
    }

    public function render()
    {
        return view('livewire.inventory.purchase-order.po-form', [
            'suppliers'  => Supplier::active()->orderBy('nama')->get(),
            'totalNilai' => $this->totalNilai,
        ]);
    }
}
