<?php

namespace App\Livewire\Inventory\Opname;

use App\Models\StokOpname;
use Livewire\Component;
use Livewire\WithPagination;

class OpnameTable extends Component
{
    use WithPagination;

    public string $filterStatus = '';
    public string $filterDari   = '';
    public string $filterSampai = '';

    public function updatedFilterStatus(): void { $this->resetPage(); }

    public function render()
    {
        $query = StokOpname::with(['pembuat', 'items'])
            ->when($this->filterStatus, fn ($q) => $q->where('status', $this->filterStatus))
            ->when($this->filterDari,   fn ($q) => $q->whereDate('tanggal_opname', '>=', $this->filterDari))
            ->when($this->filterSampai, fn ($q) => $q->whereDate('tanggal_opname', '<=', $this->filterSampai))
            ->orderByDesc('tanggal_opname')
            ->orderByDesc('id');

        return view('livewire.inventory.opname.opname-table', [
            'opnameList' => $query->paginate(20),
        ]);
    }
}
