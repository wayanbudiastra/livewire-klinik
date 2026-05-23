<?php

namespace App\Livewire\Pemeriksaan;

use App\Models\Kunjungan;
use App\Services\KunjunganService;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class WaitingArea extends Component
{
    use WithPagination;

    #[Url]
    public string $tanggal = '';

    #[Url(as: 'q')]
    public string $search = '';

    #[Url]
    public string $filterStatus = 'aktif';

    public function mount(): void
    {
        $this->tanggal = now()->toDateString();
    }

    public function updatingTanggal(): void    { $this->resetPage(); }
    public function updatingSearch(): void      { $this->resetPage(); }
    public function updatingFilterStatus(): void { $this->resetPage(); }

    #[Computed]
    public function kunjungan()
    {
        return Kunjungan::with([
            'pasien:id,nama,nomor_rm,tipe_pasien,alergi,jenis_kelamin,tanggal_lahir',
            'dokter.user:id,nama',
            'poli:id,nama',
        ])
        ->whereDate('tanggal', $this->tanggal)
        ->when($this->search, fn ($q, $s) =>
            $q->whereHas('pasien', fn ($pq) =>
                $pq->where('nama', 'like', "%{$s}%")
                   ->orWhere('nomor_rm', 'like', "%{$s}%")))
        ->when($this->filterStatus, fn ($q) => match ($this->filterStatus) {
            'aktif'  => $q->whereIn('status', ['menunggu', 'dalam_pemeriksaan']),
            default  => $q->where('status', $this->filterStatus),
        })
        ->orderByRaw("CASE status
            WHEN 'dalam_pemeriksaan' THEN 1
            WHEN 'menunggu'          THEN 2
            WHEN 'selesai'           THEN 3
            ELSE 4 END")
        ->orderBy('nomor_antrean')
        ->paginate(10);
    }

    public function panggil(int $id, KunjunganService $service): void
    {
        $service->panggilPasien($id);
        unset($this->kunjungan);
        $this->dispatch('notify', type: 'success', message: 'Pasien dipanggil ke ruang pemeriksaan.');
    }

    public function selesai(int $id, KunjunganService $service): void
    {
        $service->selesaiPemeriksaan($id);
        unset($this->kunjungan);
        $this->dispatch('notify', type: 'success', message: 'Pemeriksaan selesai.');
    }

    public function render()
    {
        return view('livewire.pemeriksaan.waiting-area');
    }
}
