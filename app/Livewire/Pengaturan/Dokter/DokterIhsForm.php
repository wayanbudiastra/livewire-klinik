<?php

namespace App\Livewire\Pengaturan\Dokter;

use App\Models\ConfigSatuSehat;
use App\Models\Dokter;
use App\Services\SatuSehat\SatuSehatIhsService;
use Livewire\Component;

class DokterIhsForm extends Component
{
    public int    $dokterId;
    public string $nik = '';

    public function mount(int $dokterId): void
    {
        $this->dokterId = $dokterId;
        $dokter         = Dokter::find($dokterId);
        $this->nik      = $dokter?->nik ?? '';
    }

    public function simpanNik(): void
    {
        $this->validate(['nik' => 'nullable|string|max:16|regex:/^[0-9]*$/']);

        Dokter::where('id', $this->dokterId)
            ->update(['nik' => $this->nik ?: null]);

        $this->dispatch('notify', type: 'success', message: 'NIK berhasil disimpan.');
    }

    public function fetchIhs(): void
    {
        if (! ConfigSatuSehat::aktif()) {
            $this->dispatch('notify', type: 'error', message: 'Integrasi SatuSehat belum diaktifkan.');
            return;
        }

        $dokter = Dokter::findOrFail($this->dokterId);

        try {
            $hasil = app(SatuSehatIhsService::class)->fetchDokter($dokter);

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
        $dokter = Dokter::find($this->dokterId);

        return view('livewire.pengaturan.dokter.dokter-ihs-form', [
            'dokter'         => $dokter,
            'satusehatAktif' => ConfigSatuSehat::aktif(),
            'environment'    => ConfigSatuSehat::config()->environment ?? 'sandbox',
            'baseUrl'        => ConfigSatuSehat::config()->getBaseUrl(),
        ]);
    }
}
