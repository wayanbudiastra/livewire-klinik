<?php

namespace App\Livewire\Pengaturan\Dokter;

use App\Models\Dokter;
use App\Models\User;
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
        // Query dari User role 'dokter' agar semua dokter muncul
        // meski belum punya record di tabel dokter
        return User::role('dokter')
            ->with(['dokter.poli:id,nama,kode'])
            ->where('is_active', true)
            ->when($this->search, fn ($q, $s) =>
                $q->where('nama', 'like', "%{$s}%")
                  ->orWhere('email', 'like', "%{$s}%"))
            ->when($this->filterSip === 'expired',
                fn ($q) => $q->whereHas('dokter', fn ($dq) =>
                    $dq->whereNotNull('tgl_expired_sip')
                       ->where('tgl_expired_sip', '<', now())))
            ->when($this->filterSip === 'segera_expired',
                fn ($q) => $q->whereHas('dokter', fn ($dq) =>
                    $dq->whereNotNull('tgl_expired_sip')
                       ->whereBetween('tgl_expired_sip', [now(), now()->addDays(30)])))
            ->when($this->filterSip === 'aktif',
                fn ($q) => $q->whereHas('dokter', fn ($dq) =>
                    $dq->where('tgl_expired_sip', '>=', now())))
            ->when($this->filterSip === 'belum_setup',
                fn ($q) => $q->whereDoesntHave('dokter'))
            ->orderBy('nama')
            ->paginate(10);
    }

    public function setupProfil(int $userId): void
    {
        $this->authorize('masterdata.edit');

        Dokter::firstOrCreate(
            ['user_id' => $userId],
            ['poli_id' => null]
        );

        unset($this->dokter);
        $this->dispatch('notify', type: 'success',
            message: 'Profil dokter dibuat. Silakan lengkapi data di halaman detail.');
    }

    #[On('dokter-saved')]
    public function refresh(): void { unset($this->dokter); }

    public function render()
    {
        return view('livewire.pengaturan.dokter.dokter-table');
    }
}
