<?php

namespace App\Livewire\Harga;

use App\Models\ProposalHarga;
use App\Services\Harga\ProposalHargaService;
use Livewire\Component;
use Livewire\WithPagination;

class ProposalHargaTable extends Component
{
    use WithPagination;

    public string $filterStatus = '';
    public string $filterTahun  = '';
    public string $search       = '';

    public function mount(): void
    {
        $this->filterTahun = (string) now()->year;
    }

    public function updatedSearch(): void       { $this->resetPage(); }
    public function updatedFilterStatus(): void { $this->resetPage(); }
    public function updatedFilterTahun(): void  { $this->resetPage(); }

    public function batalkan(int $id, ProposalHargaService $service): void
    {
        $proposal = ProposalHarga::findOrFail($id);
        try {
            $service->batalkan($proposal, auth()->user());
            session()->flash('success', "Proposal \"{$proposal->judul}\" dibatalkan.");
        } catch (\DomainException $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function render()
    {
        $tahunList = ProposalHarga::distinct()
            ->orderByDesc('tahun')
            ->pluck('tahun');

        $query = ProposalHarga::with(['dibuatOleh:id,nama'])
            ->when($this->filterStatus, fn ($q) => $q->where('status', $this->filterStatus))
            ->when($this->filterTahun,  fn ($q) => $q->where('tahun',  $this->filterTahun))
            ->when($this->search, fn ($q) => $q->where('judul', 'like', "%{$this->search}%"))
            ->orderByDesc('tanggal_efektif')
            ->orderByDesc('id');

        return view('livewire.harga.proposal-harga-table', [
            'proposals' => $query->paginate(10),
            'tahunList' => $tahunList,
        ]);
    }
}
