<?php

namespace App\Livewire\Inventory\Bhp;

use App\Models\PemakaianBhp;
use App\Services\Inventory\PemakaianBhpService;
use Livewire\Component;
use Livewire\WithPagination;

class BhpTable extends Component
{
    use WithPagination;

    public string $search      = '';
    public string $filterStatus = '';
    public string $filterDari   = '';
    public string $filterSampai = '';

    public function updatedSearch(): void    { $this->resetPage(); }
    public function updatedFilterStatus(): void { $this->resetPage(); }

    public function batalkan(int $id, PemakaianBhpService $service): void
    {
        $bhp = PemakaianBhp::findOrFail($id);

        try {
            $service->batalkan($bhp);
            session()->flash('success', "Dokumen {$bhp->nomor_bhp} dibatalkan.");
        } catch (\DomainException $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function render()
    {
        $query = PemakaianBhp::with(['pencatat', 'items'])
            ->when($this->filterStatus, fn ($q) => $q->where('status', $this->filterStatus))
            ->when($this->filterDari,   fn ($q) => $q->whereDate('tanggal_pemakaian', '>=', $this->filterDari))
            ->when($this->filterSampai, fn ($q) => $q->whereDate('tanggal_pemakaian', '<=', $this->filterSampai))
            ->when($this->search, fn ($q) => $q->where('nomor_bhp', 'like', "%{$this->search}%"))
            ->orderByDesc('tanggal_pemakaian')
            ->orderByDesc('id');

        return view('livewire.inventory.bhp.bhp-table', [
            'dokumenBhp' => $query->paginate(20),
        ]);
    }
}
