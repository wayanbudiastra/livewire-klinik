<?php

namespace App\Livewire\Farmasi\ReturResep;

use App\Models\ReturResep;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

class ReturResepTable extends Component
{
    use WithPagination;

    public string $filterDari   = '';
    public string $filterSampai = '';

    public function mount(): void
    {
        $this->filterDari   = now()->startOfMonth()->format('Y-m-d');
        $this->filterSampai = now()->endOfMonth()->format('Y-m-d');
    }

    public function updatedFilterDari(): void   { $this->resetPage(); }
    public function updatedFilterSampai(): void { $this->resetPage(); }

    #[Computed]
    public function rows()
    {
        return ReturResep::with('kunjungan.pasien:id,nama,nomor_rm', 'diprosesOleh:id,nama', 'items.barang')
            ->when($this->filterDari, fn ($q) => $q->whereDate('tanggal_retur', '>=', $this->filterDari))
            ->when($this->filterSampai, fn ($q) => $q->whereDate('tanggal_retur', '<=', $this->filterSampai))
            ->orderByDesc('tanggal_retur')
            ->orderByDesc('id')
            ->paginate(10);
    }

    public function render()
    {
        return view('livewire.farmasi.retur-resep.retur-resep-table');
    }
}
