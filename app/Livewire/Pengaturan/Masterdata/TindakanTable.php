<?php

namespace App\Livewire\Pengaturan\Masterdata;

use App\Models\MasterTindakan;
use App\Services\MasterdataService;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class TindakanTable extends Component
{
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url]
    public string $filterPoli = '';

    public function updatingSearch(): void    { $this->resetPage(); }
    public function updatingFilterPoli(): void { $this->resetPage(); }

    #[Computed]
    public function tindakan()
    {
        return MasterTindakan::with('poli:id,nama,kode')
            ->where('kategori', 'tindakan')
            ->when($this->search, fn ($q, $s) => $q->where('nama', 'like', "%{$s}%")
                ->orWhere('kode', 'like', "%{$s}%"))
            ->when($this->filterPoli, fn ($q, $p) =>
                $q->whereHas('poli', fn ($sq) => $sq->where('poli.id', $p))
            )
            ->orderBy('nama')
            ->paginate(10);
    }

    public function toggleAktif(int $id): void
    {
        $this->authorize('masterdata.edit');
        app(MasterdataService::class)->toggleAktifTindakan($id);
        unset($this->tindakan);
        $this->dispatch('notify', type: 'success', message: 'Status tindakan diupdate.');
    }

    #[On('tindakan-saved')]
    public function refresh(): void { unset($this->tindakan); }

    public function render()
    {
        return view('livewire.pengaturan.masterdata.tindakan-table');
    }
}
