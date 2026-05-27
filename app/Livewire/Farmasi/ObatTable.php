<?php

namespace App\Livewire\Farmasi;

use App\Models\Barang;
use App\Services\FarmasiService;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class ObatTable extends Component
{
    use WithPagination;

    #[Url(as: 'q')]
    public string $search        = '';

    #[Url]
    public string $filterJenis   = '';

    #[Url]
    public string $filterStatus  = 'aktif';

    #[Url]
    public bool   $filterReorder = false;

    public int $perPage = 10;

    public function updatingSearch(): void      { $this->resetPage(); }
    public function updatingFilterJenis(): void  { $this->resetPage(); }
    public function updatingFilterStatus(): void { $this->resetPage(); }

    #[Computed]
    public function obat()
    {
        return Barang::whereIn('jenis', ['obat', 'alkes'])
            ->when($this->search,       fn ($q, $s) => $q->search($s))
            ->when($this->filterJenis,  fn ($q, $j) => $q->where('jenis', $j))
            ->when($this->filterStatus === 'aktif',    fn ($q) => $q->where('is_active', true))
            ->when($this->filterStatus === 'nonaktif', fn ($q) => $q->where('is_active', false))
            ->when($this->filterReorder, fn ($q) => $q->whereRaw('stok <= stok_minimum'))
            ->orderBy('nama')
            ->paginate($this->perPage);
    }

    public function toggleAktif(int $id): void
    {
        $this->authorize('obat.edit');
        app(FarmasiService::class)->toggleAktif($id);
        unset($this->obat);
        $this->dispatch('notify', type: 'success', message: 'Status obat diupdate.');
    }

    #[On('obat-saved')]
    public function refresh(): void { unset($this->obat); }

    public function render()
    {
        return view('livewire.farmasi.obat-table');
    }
}
