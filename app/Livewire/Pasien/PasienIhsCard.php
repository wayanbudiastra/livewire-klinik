<?php

namespace App\Livewire\Pasien;

use App\Models\ConfigSatuSehat;
use App\Models\Pasien;
use App\Services\SatuSehat\SatuSehatIhsService;
use Livewire\Component;

class PasienIhsCard extends Component
{
    public int $pasienId;

    public function mount(int $pasienId): void
    {
        $this->pasienId = $pasienId;
    }

    public function fetchIhs(): void
    {
        if (! ConfigSatuSehat::aktif()) {
            $this->dispatch('notify', type: 'error', message: 'Integrasi SatuSehat belum diaktifkan.');
            return;
        }

        $pasien = Pasien::findOrFail($this->pasienId);

        try {
            $hasil = app(SatuSehatIhsService::class)->fetchPasien($pasien);

            if ($hasil['status'] === 'ditemukan') {
                $this->dispatch('notify', type: 'success', message: "IHS ditemukan: {$hasil['ihs_id']}");
            } else {
                $this->dispatch('notify', type: 'error', message: $hasil['pesan']);
            }
        } catch (\RuntimeException $e) {
            $this->dispatch('notify', type: 'error', message: $e->getMessage());
        }
    }

    public function render()
    {
        $pasien = Pasien::find($this->pasienId);

        return view('livewire.pasien.pasien-ihs-card', [
            'pasien'         => $pasien,
            'satusehatAktif' => ConfigSatuSehat::aktif(),
            'environment'    => ConfigSatuSehat::config()->environment ?? 'sandbox',
        ]);
    }
}
