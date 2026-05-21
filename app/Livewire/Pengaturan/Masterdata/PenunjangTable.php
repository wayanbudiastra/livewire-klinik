<?php

namespace App\Livewire\Pengaturan\Masterdata;

use App\Models\ItemPenunjang;
use App\Services\MasterdataService;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class PenunjangTable extends Component
{
    use WithPagination;

    public string $kategori = 'lab'; // 'lab' | 'radiologi'

    #[Url(as: 'q')]
    public string $search = '';

    public function updatingSearch(): void { $this->resetPage(); }

    #[Computed]
    public function items()
    {
        return ItemPenunjang::where('kategori', $this->kategori)
            ->when($this->search, fn ($q, $s) =>
                $q->where('nama', 'like', "%{$s}%")
                  ->orWhere('kode', 'like', "%{$s}%"))
            ->orderBy('nama')
            ->paginate(10);
    }

    public function toggleAktif(int $id): void
    {
        $this->authorize('masterdata.edit');
        app(MasterdataService::class)->toggleAktifPenunjang($id);
        unset($this->items);
        $this->dispatch('notify', type: 'success', message: 'Status diupdate.');
    }

    #[On('penunjang-saved')]
    public function refresh(): void { unset($this->items); }

    public function render()
    {
        return view('livewire.pengaturan.masterdata.penunjang-table');
    }
}
