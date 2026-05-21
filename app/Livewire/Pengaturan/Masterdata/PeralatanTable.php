<?php

namespace App\Livewire\Pengaturan\Masterdata;

use App\Models\PeralatanMedis;
use App\Services\MasterdataService;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class PeralatanTable extends Component
{
    use WithPagination;

    #[Url(as: 'q')]
    public string $search        = '';

    #[Url]
    public string $filterStatus  = '';

    #[Url]
    public string $filterAktif   = '';   // '' | '1' | '0'

    public function updatingSearch(): void       { $this->resetPage(); }
    public function updatingFilterStatus(): void  { $this->resetPage(); }
    public function updatingFilterAktif(): void   { $this->resetPage(); }

    #[Computed]
    public function peralatan()
    {
        return PeralatanMedis::with('poliTerakhir:id,nama')
            ->when($this->search, fn ($q, $s) =>
                $q->where('nama', 'like', "%{$s}%")
                  ->orWhere('kode', 'like', "%{$s}%"))
            ->when($this->filterStatus, fn ($q, $s) => $q->where('status', $s))
            ->when($this->filterAktif !== '',
                fn ($q) => $q->where('is_active', $this->filterAktif === '1'))
            ->orderBy('nama')
            ->paginate(10);
    }

    public function updateStatus(int $id, string $status): void
    {
        $this->authorize('masterdata.edit');
        PeralatanMedis::findOrFail($id)->update(['status' => $status]);
        unset($this->peralatan);
        $this->dispatch('notify', type: 'success', message: 'Status peralatan diupdate.');
    }

    public function toggleAktif(int $id): void
    {
        $this->authorize('masterdata.edit');
        app(MasterdataService::class)->toggleAktifPeralatan($id);
        unset($this->peralatan);
        $this->dispatch('notify', type: 'success', message: 'Status aktif peralatan diupdate.');
    }

    #[On('peralatan-saved')]
    public function refresh(): void { unset($this->peralatan); }

    public function render()
    {
        return view('livewire.pengaturan.masterdata.peralatan-table');
    }
}
