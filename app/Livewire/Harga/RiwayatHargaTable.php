<?php

namespace App\Livewire\Harga;

use App\Models\ProposalHarga;
use App\Models\ProposalHargaItem;
use App\Services\Harga\RiwayatHargaService;
use Livewire\Component;
use Livewire\WithPagination;

class RiwayatHargaTable extends Component
{
    use WithPagination;

    public string $search          = '';
    public string $filterTipe      = '';
    public string $filterKategori  = '';
    public string $filterProposal  = '';
    public string $filterTahun     = '';

    // Detail modal
    public bool   $showTimeline    = false;
    public string $timelineType    = '';
    public int    $timelineItemId  = 0;
    public string $timelineNama    = '';

    public function mount(): void
    {
        $this->filterTahun = (string) now()->year;
    }

    public function updatedSearch(): void         { $this->resetPage(); }
    public function updatedFilterTipe(): void     { $this->filterKategori = ''; $this->resetPage(); }
    public function updatedFilterKategori(): void { $this->resetPage(); }
    public function updatedFilterProposal(): void { $this->resetPage(); }
    public function updatedFilterTahun(): void    { $this->resetPage(); }

    public function openTimeline(string $type, int $itemId, string $nama): void
    {
        $this->timelineType   = $type;
        $this->timelineItemId = $itemId;
        $this->timelineNama   = $nama;
        $this->showTimeline   = true;
    }

    public function render(RiwayatHargaService $service)
    {
        $filter = [
            'search'           => $this->search,
            'item_type'        => $this->filterTipe,
            'item_kategori'    => $this->filterKategori,
            'proposal_harga_id'=> $this->filterProposal ? (int) $this->filterProposal : null,
            'tahun'            => $this->filterTahun ?: null,
        ];

        $items = $service->query($filter)->paginate(20);

        $proposalList = ProposalHarga::efektif()
            ->orderByDesc('tanggal_efektif')
            ->get(['id', 'judul', 'tahun']);

        $kategoriList = ProposalHargaItem::query()
            ->whereHas('proposal', fn ($q) => $q->where('status', 'efektif'))
            ->where('is_skip', false)
            ->when($this->filterTipe, fn ($q) => $q->where('item_type', $this->filterTipe))
            ->distinct()
            ->orderBy('item_kategori')
            ->pluck('item_kategori')
            ->filter();

        $tahunList = ProposalHarga::efektif()
            ->distinct()
            ->orderByDesc('tahun')
            ->pluck('tahun');

        $timeline = [];
        if ($this->showTimeline && $this->timelineItemId) {
            $timeline = $service->timelineItem($this->timelineType, $this->timelineItemId)->all();
        }

        return view('livewire.harga.riwayat-harga-table', compact(
            'items', 'proposalList', 'kategoriList', 'tahunList', 'timeline'
        ));
    }
}
