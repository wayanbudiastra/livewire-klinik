<?php

namespace App\Livewire\Inventory\ReturGr;

use App\Models\ReturGr;
use App\Services\Inventory\ReturGrService;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class ReturGrTable extends Component
{
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';
    #[Url]
    public string $filterStatus = '';

    public function updatedSearch(): void       { $this->resetPage(); }
    public function updatedFilterStatus(): void { $this->resetPage(); }

    #[Computed]
    public function rows()
    {
        return ReturGr::with('supplier:id,nama', 'goodsReceipt:id,nomor_gr', 'dibuatOleh:id,nama')
            ->when($this->search, fn ($q, $s) => $q->where('nomor_retur', 'like', "%{$s}%"))
            ->when($this->filterStatus, fn ($q, $st) => $q->where('status', $st))
            ->orderByDesc('tanggal_retur')
            ->orderByDesc('id')
            ->paginate(10);
    }

    public function verifikasi(int $id, ReturGrService $service): void
    {
        try {
            $service->verifikasi(ReturGr::with('items.barang', 'items.grItem')->findOrFail($id), auth()->id());
            unset($this->rows);
            $this->dispatch('notify', type: 'success', message: 'Retur diverifikasi. Stok & hutang dagang dikoreksi.');
        } catch (\Exception $e) {
            $this->dispatch('notify', type: 'error', message: $e->getMessage());
        }
    }

    public function batalkan(int $id, ReturGrService $service): void
    {
        try {
            $service->batalkanDraft(ReturGr::findOrFail($id));
            unset($this->rows);
            $this->dispatch('notify', type: 'success', message: 'Retur dibatalkan.');
        } catch (\Exception $e) {
            $this->dispatch('notify', type: 'error', message: $e->getMessage());
        }
    }

    public function render()
    {
        return view('livewire.inventory.retur-gr.retur-gr-table');
    }
}
