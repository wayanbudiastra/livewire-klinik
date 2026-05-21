<?php

namespace App\Livewire\Pengaturan\Dokter;

use App\Models\Dokter;
use App\Models\Poli;
use App\Services\DokterService;
use Livewire\Attributes\Computed;
use Livewire\Component;

class DokterPoliMapping extends Component
{
    public int    $dokterId  = 0;
    public string $namaUser  = '';
    public int    $addPoliId = 0;

    public function mount(int $dokterId): void
    {
        $this->dokterId = $dokterId;
        $dokter = Dokter::with('user')->findOrFail($dokterId);
        $this->namaUser = $dokter->user->nama;
    }

    #[Computed]
    public function mappedPoli()
    {
        return Dokter::findOrFail($this->dokterId)
            ->dokterPoli()
            ->with('poli:id,nama,kode')
            ->get();
    }

    #[Computed]
    public function availablePoli()
    {
        $mappedIds = collect($this->mappedPoli)
            ->where('is_aktif', true)
            ->pluck('poli_id');

        return Poli::aktif()
            ->whereNotIn('id', $mappedIds)
            ->orderBy('nama')
            ->get(['id', 'nama', 'kode']);
    }

    public function addMapping(DokterService $service): void
    {
        $this->authorize('masterdata.edit');
        $this->validate(['addPoliId' => 'required|integer|exists:poli,id']);

        $service->addPoliMapping($this->dokterId, $this->addPoliId);
        $this->addPoliId = 0;
        unset($this->mappedPoli, $this->availablePoli);
        $this->dispatch('notify', type: 'success', message: 'Poli berhasil ditambahkan.');
    }

    public function removeMapping(int $poliId, DokterService $service): void
    {
        $this->authorize('masterdata.edit');
        $service->removePoliMapping($this->dokterId, $poliId);
        unset($this->mappedPoli, $this->availablePoli);
        $this->dispatch('notify', type: 'success', message: 'Mapping poli dinonaktifkan.');
    }

    public function render()
    {
        return view('livewire.pengaturan.dokter.dokter-poli-mapping');
    }
}
