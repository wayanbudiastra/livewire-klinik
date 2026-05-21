<?php

namespace App\Livewire\Pengaturan\Dokter;

use App\Models\Dokter;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class DokterTable extends Component
{
    use WithPagination;

    #[Url(as: 'q')]
    public string $search      = '';

    #[Url]
    public string $filterSip   = '';

    public function updatingSearch(): void   { $this->resetPage(); }
    public function updatingFilterSip(): void { $this->resetPage(); }

    #[Computed]
    public function dokter()
    {
        return Dokter::with(['user:id,nama,email,is_active', 'poli:id,nama,kode'])
            ->whereHas('user', function ($q) {
                $q->where('is_active', true);
                if ($this->search) {
                    $q->where('nama', 'like', "%{$this->search}%")
                      ->orWhere('email', 'like', "%{$this->search}%");
                }
            })
            ->when($this->filterSip === 'expired',
                fn ($q) => $q->whereNotNull('tgl_expired_sip')
                             ->where('tgl_expired_sip', '<', now()))
            ->when($this->filterSip === 'segera_expired',
                fn ($q) => $q->whereNotNull('tgl_expired_sip')
                             ->whereBetween('tgl_expired_sip', [now(), now()->addDays(30)]))
            ->when($this->filterSip === 'aktif',
                fn ($q) => $q->where('tgl_expired_sip', '>=', now()))
            ->orderBy('id')
            ->paginate(10);
    }

    #[On('dokter-saved')]
    public function refresh(): void { unset($this->dokter); }

    public function render()
    {
        return view('livewire.pengaturan.dokter.dokter-table');
    }
}
