<?php

namespace App\Livewire\Pengaturan\Asuransi;

use App\Models\Asuransi;
use App\Services\Asuransi\AsuransiService;
use Livewire\Component;
use Livewire\WithPagination;

class AsuransiTable extends Component
{
    use WithPagination;

    public string $search = '';

    public function getAsuransiProperty()
    {
        return Asuransi::query()
            ->when($this->search, fn($q) => $q->where('nama', 'like', "%{$this->search}%")
                ->orWhere('kode', 'like', "%{$this->search}%"))
            ->orderBy('nama')
            ->paginate(15);
    }

    public function toggleActive(int $id, AsuransiService $service): void
    {
        $service->toggleActive(Asuransi::findOrFail($id));
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        return view('livewire.pengaturan.asuransi.asuransi-table', [
            'asuransi' => $this->asuransi,
        ]);
    }
}
