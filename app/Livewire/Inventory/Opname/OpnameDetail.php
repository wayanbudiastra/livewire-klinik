<?php

namespace App\Livewire\Inventory\Opname;

use App\Models\{StokOpname, StokOpnameItem};
use App\Services\Inventory\StokOpnameService;
use Livewire\Component;
use Livewire\WithPagination;

class OpnameDetail extends Component
{
    use WithPagination;

    public StokOpname $opname;

    public string $filterTampil = 'semua'; // semua | belum_diisi | ada_selisih
    public string $search       = '';

    public function mount(StokOpname $opname): void
    {
        $this->opname = $opname->load(['items.barang', 'pembuat', 'verifikator']);
    }

    public function inputStokFisik(int $itemId, string $nilai, StokOpnameService $service): void
    {
        if ($this->opname->status !== 'draft') return;

        $item = StokOpnameItem::findOrFail($itemId);
        $stokFisik = (float) $nilai;

        if ($stokFisik < 0) {
            $this->addError("stok_fisik_{$itemId}", 'Stok fisik tidak boleh negatif.');
            return;
        }

        $service->inputStokFisik($item, $stokFisik);
        $this->opname = $this->opname->fresh(['items.barang']);
    }

    public function submitVerifikasi(StokOpnameService $service): void
    {
        try {
            $service->submitUntukVerifikasi($this->opname);
            $this->opname = $this->opname->fresh(['items.barang', 'pembuat', 'verifikator']);
            session()->flash('success', 'Opname disubmit untuk verifikasi.');
        } catch (\DomainException $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function verifikasi(StokOpnameService $service): void
    {
        try {
            $service->verifikasi($this->opname, auth()->id());
            $this->opname = $this->opname->fresh(['items.barang', 'pembuat', 'verifikator']);
            session()->flash('success', 'Opname berhasil diverifikasi. Stok sistem telah diperbarui.');
        } catch (\DomainException $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function batalkan(StokOpnameService $service): void
    {
        try {
            $service->batalkan($this->opname);
            $this->opname = $this->opname->fresh();
            session()->flash('success', 'Opname dibatalkan.');
        } catch (\DomainException $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function getRingkasanProperty(): array
    {
        $items = $this->opname->items;
        return [
            'total_item'    => $items->count(),
            'sudah_diisi'   => $items->whereNotNull('stok_fisik')->count(),
            'sesuai'        => $items->where('tipe_selisih', 'sesuai')->count(),
            'lebih'         => $items->where('tipe_selisih', 'lebih')->count(),
            'kurang'        => $items->where('tipe_selisih', 'kurang')->count(),
            'nilai_selisih' => (float) $items->sum('nilai_selisih'),
        ];
    }

    public function render()
    {
        $query = $this->opname->items()->with('barang')
            ->when($this->search, fn ($q) => $q->whereHas('barang', fn ($bq) => $bq->where('nama', 'like', "%{$this->search}%")))
            ->when($this->filterTampil === 'belum_diisi', fn ($q) => $q->whereNull('stok_fisik'))
            ->when($this->filterTampil === 'ada_selisih', fn ($q) => $q->whereIn('tipe_selisih', ['lebih', 'kurang']))
            ->orderBy('id');

        return view('livewire.inventory.opname.opname-detail', [
            'items'     => $query->paginate(30),
            'ringkasan' => $this->ringkasan,
        ]);
    }
}
