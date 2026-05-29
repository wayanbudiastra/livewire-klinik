<?php

namespace App\Livewire\Farmasi\Ritel;

use App\Models\TransaksiRitel;
use App\Services\Farmasi\ObatRitelService;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class RitelTable extends Component
{
    use WithPagination;

    #[Url]
    public string $filterStatus = '';

    #[Url]
    public string $search = '';

    public string $filterDari    = '';
    public string $filterSampai  = '';

    public function mount(): void
    {
        $this->filterDari   = now()->format('Y-m-d');
        $this->filterSampai = now()->format('Y-m-d');
    }

    public function updatedSearch(): void    { $this->resetPage(); }
    public function updatedFilterStatus(): void { $this->resetPage(); }
    public function updatedFilterDari(): void   { $this->resetPage(); }
    public function updatedFilterSampai(): void { $this->resetPage(); }

    #[Computed]
    public function transaksis()
    {
        return TransaksiRitel::with(['apoteker', 'kasir'])
            ->when($this->filterStatus, fn ($q) => $q->where('status', $this->filterStatus))
            ->when($this->search, fn ($q) => $q->where(function ($q2) {
                $q2->where('nama_pembeli', 'like', "%{$this->search}%")
                   ->orWhere('nomor_ritel', 'like', "%{$this->search}%");
            }))
            ->when($this->filterDari, fn ($q) => $q->whereDate('created_at', '>=', $this->filterDari))
            ->when($this->filterSampai, fn ($q) => $q->whereDate('created_at', '<=', $this->filterSampai))
            ->orderByDesc('created_at')
            ->paginate(20);
    }

    public function batalkan(int $id, ObatRitelService $service): void
    {
        try {
            $tr = TransaksiRitel::findOrFail($id);
            $service->batalkan($tr);
            session()->flash('success', "Transaksi {$tr->nomor_ritel} dibatalkan.");
        } catch (\DomainException $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function serahkanObat(int $id, ObatRitelService $service): void
    {
        try {
            $tr = TransaksiRitel::findOrFail($id);
            $service->serahkanObat($tr, auth()->id());
            session()->flash('success', "Obat untuk {$tr->nama_pembeli} berhasil diserahkan. Stok terpotong.");
        } catch (\DomainException $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function render()
    {
        return view('livewire.farmasi.ritel.ritel-table');
    }
}
