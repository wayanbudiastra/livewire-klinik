<?php

namespace App\Livewire\Akuntansi;

use App\Models\Akuntansi\JurnalManual;
use App\Services\Akuntansi\JurnalManualService;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

class JurnalManualTable extends Component
{
    use WithPagination;

    public string $filterDari   = '';
    public string $filterSampai = '';
    public string $filterKategori = '';

    public function mount(): void
    {
        $this->filterDari   = now()->startOfMonth()->format('Y-m-d');
        $this->filterSampai = now()->endOfMonth()->format('Y-m-d');
    }

    public function updatedFilterDari(): void     { $this->resetPage(); }
    public function updatedFilterSampai(): void   { $this->resetPage(); }
    public function updatedFilterKategori(): void { $this->resetPage(); }

    #[Computed]
    public function rows()
    {
        return JurnalManual::with(['akunDebit', 'akunKredit', 'dibuatOleh', 'jurnalPending', 'reversalPending'])
            ->when($this->filterDari, fn ($q) => $q->whereDate('tanggal', '>=', $this->filterDari))
            ->when($this->filterSampai, fn ($q) => $q->whereDate('tanggal', '<=', $this->filterSampai))
            ->when($this->filterKategori, fn ($q) => $q->where('kategori', $this->filterKategori))
            ->orderByDesc('tanggal')
            ->orderByDesc('id')
            ->paginate(10);
    }

    public function batalkan(int $id, JurnalManualService $service): void
    {
        // Audit Priority 3 (Sedang): route /akuntansi/jurnal-manual cuma
        // digate permission:akuntansi.jurnal.view (lebih longgar dari
        // akuntansi.jurnal_manual.create yg seharusnya utk aksi tulis
        // spt ini) -- authorize() di sini jadi lapis kedua (defense in
        // depth). Saat ini tidak ada privilege escalation nyata (ketiga
        // role dgn akuntansi.jurnal.view jg py akuntansi.jurnal_manual.create),
        // tapi konsisten dgn pola yang sudah dibenahi di komponen lain.
        $this->authorize('akuntansi.jurnal_manual.create');

        try {
            $jm = JurnalManual::findOrFail($id);
            $service->batalkan($jm, auth()->id());
            $this->dispatch('notify', type: 'success', message: 'Jurnal manual dibatalkan.');
        } catch (\Exception $e) {
            $this->dispatch('notify', type: 'error', message: $e->getMessage());
        }
    }

    public function render()
    {
        return view('livewire.akuntansi.jurnal-manual-table');
    }
}
