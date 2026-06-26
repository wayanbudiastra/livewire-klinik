<?php

namespace App\Livewire\Akuntansi;

use App\Models\Akuntansi\JurnalPending;
use App\Services\Akuntansi\JurnalService;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class JurnalPendingTable extends Component
{
    use WithPagination;

    #[Url]
    public string $filterTipe = '';
    #[Url]
    public string $filterDari = '';
    #[Url]
    public string $filterSampai = '';

    public array $selected = [];
    public bool  $selectAll = false;

    public ?int   $abaikanId   = null;
    public string $alasanAbaikan = '';

    #[Computed]
    public function tipeList()
    {
        return JurnalPending::pending()->distinct()->pluck('tipe_transaksi');
    }

    #[Computed]
    public function rows()
    {
        return JurnalPending::pending()
            ->with(['akunDebit', 'akunKredit'])
            ->when($this->filterTipe, fn ($q) => $q->where('tipe_transaksi', $this->filterTipe))
            ->when($this->filterDari, fn ($q) => $q->whereDate('tanggal_transaksi', '>=', $this->filterDari))
            ->when($this->filterSampai, fn ($q) => $q->whereDate('tanggal_transaksi', '<=', $this->filterSampai))
            ->orderBy('tanggal_transaksi')
            ->orderBy('id')
            ->paginate(25);
    }

    #[Computed]
    public function totalNominalTerpilih(): float
    {
        if (empty($this->selected)) return 0;
        return (float) JurnalPending::whereIn('id', $this->selected)->sum('nominal');
    }

    public function updatedFilterTipe(): void { $this->resetPage(); }
    public function updatedFilterDari(): void { $this->resetPage(); }
    public function updatedFilterSampai(): void { $this->resetPage(); }

    public function updatedSelectAll($value): void
    {
        $this->selected = $value ? $this->rows->pluck('id')->map(fn ($id) => (string) $id)->toArray() : [];
    }

    public function postingTerpilih(): void
    {
        if (empty($this->selected)) {
            $this->dispatch('notify', type: 'error', message: 'Pilih minimal satu baris jurnal.');
            return;
        }

        $ids = array_map('intval', $this->selected);

        try {
            app(JurnalService::class)->posting($ids, auth()->id());
        } catch (\DomainException $e) {
            $this->dispatch('notify', type: 'error', message: $e->getMessage());
            return;
        }

        $this->selected  = [];
        $this->selectAll = false;
        $this->dispatch('notify', type: 'success', message: count($ids) . ' baris jurnal berhasil diposting.');
    }

    public function konfirmasiAbaikan(int $id): void
    {
        $this->abaikanId     = $id;
        $this->alasanAbaikan = '';
    }

    public function abaikan(): void
    {
        if (!$this->abaikanId) return;

        app(JurnalService::class)->abaikan($this->abaikanId, $this->alasanAbaikan ?: null);

        $this->abaikanId = null;
        $this->dispatch('notify', type: 'success', message: 'Baris jurnal ditandai diabaikan.');
    }

    public function render()
    {
        return view('livewire.akuntansi.jurnal-pending-table');
    }
}
