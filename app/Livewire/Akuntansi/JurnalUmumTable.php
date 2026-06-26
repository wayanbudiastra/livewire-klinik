<?php

namespace App\Livewire\Akuntansi;

use App\Models\Akuntansi\JurnalUmum;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class JurnalUmumTable extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';
    #[Url]
    public string $filterDari = '';
    #[Url]
    public string $filterSampai = '';

    #[Computed]
    public function rows()
    {
        return JurnalUmum::with(['akunDebit', 'akunKredit', 'petugas'])
            ->when($this->search, fn ($q) => $q
                ->where('nomor_jurnal', 'like', "%{$this->search}%")
                ->orWhere('keterangan', 'like', "%{$this->search}%")
            )
            ->when($this->filterDari, fn ($q) => $q->whereDate('tanggal', '>=', $this->filterDari))
            ->when($this->filterSampai, fn ($q) => $q->whereDate('tanggal', '<=', $this->filterSampai))
            ->orderByDesc('tanggal')
            ->orderByDesc('id')
            ->paginate(25);
    }

    public function updatedSearch(): void { $this->resetPage(); }
    public function updatedFilterDari(): void { $this->resetPage(); }
    public function updatedFilterSampai(): void { $this->resetPage(); }

    public function render()
    {
        return view('livewire.akuntansi.jurnal-umum-table');
    }
}
