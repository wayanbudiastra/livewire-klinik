<?php

namespace App\Livewire\Harga;

use App\Models\ProposalHarga;
use App\Models\ProposalHargaItem;
use App\Services\Harga\ProposalHargaService;
use Livewire\Component;
use Livewire\WithPagination;

class ProposalHargaDetail extends Component
{
    use WithPagination;

    public int    $proposalId;
    public string $search          = '';
    public string $filterKategori  = '';
    public string $filterReview    = '';   // '' | 'naik' | 'skip' | 'dikoreksi'

    // Inline edit state
    public int    $editingId       = 0;
    public string $editHarga       = '';
    public string $editHargaBpjs   = '';

    // Tolak modal
    public bool   $showTolakModal  = false;
    public string $alasanTolak     = '';

    public function mount(int $id): void
    {
        $this->proposalId = $id;
        ProposalHarga::findOrFail($id); // 404 kalau tidak ada
    }

    public function updatedSearch(): void         { $this->resetPage(); }
    public function updatedFilterKategori(): void { $this->resetPage(); }
    public function updatedFilterReview(): void   { $this->resetPage(); }

    // ── Inline edit ────────────────────────────────────────

    public function startEdit(int $itemId): void
    {
        $item            = ProposalHargaItem::findOrFail($itemId);
        $this->editingId      = $itemId;
        $this->editHarga      = (string) $item->harga_baru;
        $this->editHargaBpjs  = (string) ($item->harga_bpjs_baru ?? '');
    }

    public function cancelEdit(): void
    {
        $this->editingId     = 0;
        $this->editHarga     = '';
        $this->editHargaBpjs = '';
    }

    public function saveEdit(ProposalHargaService $service): void
    {
        $this->validate([
            'editHarga' => 'required|numeric|min:0',
        ], ['editHarga.required' => 'Harga baru wajib diisi.']);

        $item = ProposalHargaItem::findOrFail($this->editingId);

        try {
            $service->koreksiItem(
                $item,
                (float) $this->editHarga,
                $this->editHargaBpjs !== '' ? (float) $this->editHargaBpjs : null,
                auth()->user()
            );
            $this->cancelEdit();
            $this->dispatch('notify', type: 'success', message: 'Harga berhasil dikoreksi.');
        } catch (\DomainException $e) {
            $this->addError('editHarga', $e->getMessage());
        }
    }

    public function toggleSkip(int $itemId, ProposalHargaService $service): void
    {
        $item = ProposalHargaItem::findOrFail($itemId);
        try {
            $service->toggleSkip($item, !$item->is_skip, auth()->user());
        } catch (\DomainException $e) {
            $this->dispatch('notify', type: 'error', message: $e->getMessage());
        }
    }

    // ── Workflow actions ───────────────────────────────────

    public function submitReview(ProposalHargaService $service): void
    {
        $proposal = ProposalHarga::findOrFail($this->proposalId);
        try {
            $service->submitReview($proposal);
            $this->dispatch('notify', type: 'success', message: 'Proposal berhasil disubmit untuk persetujuan.');
        } catch (\DomainException $e) {
            $this->dispatch('notify', type: 'error', message: $e->getMessage());
        }
    }

    public function setujui(ProposalHargaService $service): void
    {
        $proposal = ProposalHarga::findOrFail($this->proposalId);
        try {
            $service->setujui($proposal, auth()->user());
            $this->dispatch('notify', type: 'success', message: 'Proposal disetujui.');
        } catch (\DomainException $e) {
            $this->dispatch('notify', type: 'error', message: $e->getMessage());
        }
    }

    public function openTolak(): void
    {
        $this->alasanTolak    = '';
        $this->showTolakModal = true;
    }

    public function tolak(ProposalHargaService $service): void
    {
        $this->validate(['alasanTolak' => 'required|string|min:5'], [
            'alasanTolak.required' => 'Alasan penolakan wajib diisi.',
            'alasanTolak.min'      => 'Alasan minimal 5 karakter.',
        ]);

        $proposal = ProposalHarga::findOrFail($this->proposalId);
        try {
            $service->tolak($proposal, $this->alasanTolak, auth()->user());
            $this->showTolakModal = false;
            $this->dispatch('notify', type: 'success', message: 'Proposal dikembalikan ke draft.');
        } catch (\DomainException $e) {
            $this->dispatch('notify', type: 'error', message: $e->getMessage());
        }
    }

    public function batalkan(ProposalHargaService $service): void
    {
        $proposal = ProposalHarga::findOrFail($this->proposalId);
        try {
            $service->batalkan($proposal, auth()->user());
            $this->dispatch('notify', type: 'success', message: 'Proposal dibatalkan.');
            $this->redirect(route('harga.proposal.index'));
        } catch (\DomainException $e) {
            $this->dispatch('notify', type: 'error', message: $e->getMessage());
        }
    }

    public function terapkan(ProposalHargaService $service): void
    {
        $proposal = ProposalHarga::findOrFail($this->proposalId);
        try {
            $service->terapkan($proposal, auth()->user());
            $this->dispatch('notify', type: 'success', message: 'Harga berhasil diterapkan ke seluruh master data!');
        } catch (\DomainException $e) {
            $this->dispatch('notify', type: 'error', message: $e->getMessage());
        }
    }

    // ── Render ─────────────────────────────────────────────

    public function render()
    {
        $proposal = ProposalHarga::with([
                'dibuatOleh:id,nama',
                'disetujuiOleh:id,nama',
                'diterapkanOleh:id,nama',
            ])->findOrFail($this->proposalId);

        $kategoriList = ProposalHargaItem::where('proposal_harga_id', $this->proposalId)
            ->distinct()->orderBy('item_kategori')->pluck('item_kategori')->filter();

        $query = ProposalHargaItem::where('proposal_harga_id', $this->proposalId)
            ->when($this->search, fn ($q) =>
                $q->where('item_nama', 'like', "%{$this->search}%")
            )
            ->when($this->filterKategori, fn ($q) =>
                $q->where('item_kategori', $this->filterKategori)
            )
            ->when($this->filterReview === 'skip', fn ($q) =>
                $q->where('is_skip', true)
            )
            ->when($this->filterReview === 'naik', fn ($q) =>
                $q->where('is_skip', false)->whereColumn('harga_baru', '>', 'harga_lama')
            )
            ->when($this->filterReview === 'dikoreksi', fn ($q) =>
                $q->where('is_dikoreksi_manual', true)
            )
            ->orderBy('item_type')
            ->orderBy('item_kategori')
            ->orderBy('item_nama');

        $totalItem    = ProposalHargaItem::where('proposal_harga_id', $this->proposalId)->count();
        $totalNaik    = ProposalHargaItem::where('proposal_harga_id', $this->proposalId)
            ->where('is_skip', false)->whereColumn('harga_baru', '>', 'harga_lama')->count();
        $totalSkip    = ProposalHargaItem::where('proposal_harga_id', $this->proposalId)
            ->where('is_skip', true)->count();

        return view('livewire.harga.proposal-harga-detail', [
            'proposal'     => $proposal,
            'items'        => $query->paginate(20),
            'kategoriList' => $kategoriList,
            'totalItem'    => $totalItem,
            'totalNaik'    => $totalNaik,
            'totalSkip'    => $totalSkip,
        ]);
    }
}
