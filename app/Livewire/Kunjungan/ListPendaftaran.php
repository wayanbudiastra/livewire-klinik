<?php

namespace App\Livewire\Kunjungan;

use App\Models\Kunjungan;
use App\Services\KunjunganService;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class ListPendaftaran extends Component
{
    use WithPagination;

    #[Url]
    public string $tanggal = '';

    #[Url(as: 'q')]
    public string $search  = '';

    #[Url]
    public string $filterStatus = '';

    public function mount(): void
    {
        $this->tanggal = now()->toDateString();
    }

    public function updatingTanggal(): void    { $this->resetPage(); }
    public function updatingSearch(): void      { $this->resetPage(); }
    public function updatingFilterStatus(): void{ $this->resetPage(); }

    #[Computed]
    public function kunjungan()
    {
        return Kunjungan::with([
            'pasien:id,nama,nomor_rm,tipe_pasien',
            'dokter.user:id,nama',
            'poli:id,nama',
            'appointment:id,kode_booking',
        ])
        ->whereDate('tanggal', $this->tanggal)
        ->when($this->search, fn ($q, $s) =>
            $q->whereHas('pasien', fn ($pq) =>
                $pq->where('nama', 'like', "%{$s}%")
                   ->orWhere('nomor_rm', 'like', "%{$s}%")))
        ->when($this->filterStatus, fn ($q, $st) => $q->where('status', $st))
        ->orderBy('nomor_antrean')
        ->paginate(15);
    }

    public function cancel(int $id, KunjunganService $service): void
    {
        $this->authorize('kunjungan.edit');
        $service->cancelKunjungan($id);
        unset($this->kunjungan);
        $this->dispatch('notify', type: 'success', message: 'Kunjungan berhasil dibatalkan.');
    }

    #[On('kunjungan-created')]
    public function refresh(): void { unset($this->kunjungan); }

    public function render()
    {
        return view('livewire.kunjungan.list-pendaftaran');
    }
}
