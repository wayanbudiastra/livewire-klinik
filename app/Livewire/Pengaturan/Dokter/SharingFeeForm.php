<?php

namespace App\Livewire\Pengaturan\Dokter;

use App\Models\Dokter;
use App\Services\DokterService;
use Livewire\Component;

class SharingFeeForm extends Component
{
    public int    $dokterId = 0;
    public string $namaUser = '';

    public string $fee_tindakan  = '0';
    public string $fee_lab       = '0';
    public string $fee_radiologi = '0';
    public string $fee_peralatan = '0';

    public function mount(int $dokterId): void
    {
        $this->dokterId = $dokterId;
        $dokter = Dokter::with(['user', 'sharingFee'])->findOrFail($dokterId);
        $this->namaUser = $dokter->user->nama;

        $feeMap = $dokter->sharingFee->pluck('persentase', 'kategori')->toArray();
        $this->fee_tindakan  = (string) ($feeMap['tindakan']  ?? 0);
        $this->fee_lab       = (string) ($feeMap['lab']       ?? 0);
        $this->fee_radiologi = (string) ($feeMap['radiologi'] ?? 0);
        $this->fee_peralatan = (string) ($feeMap['peralatan'] ?? 0);
    }

    public function save(DokterService $service): void
    {
        $this->authorize('masterdata.edit');
        $this->validate([
            'fee_tindakan'  => 'required|numeric|min:0|max:100',
            'fee_lab'       => 'required|numeric|min:0|max:100',
            'fee_radiologi' => 'required|numeric|min:0|max:100',
            'fee_peralatan' => 'required|numeric|min:0|max:100',
        ], [
            'fee_*.min' => 'Persentase minimal 0%.',
            'fee_*.max' => 'Persentase maksimal 100%.',
        ]);

        $service->saveSharingFee($this->dokterId, [
            'tindakan'  => (float) $this->fee_tindakan,
            'lab'       => (float) $this->fee_lab,
            'radiologi' => (float) $this->fee_radiologi,
            'peralatan' => (float) $this->fee_peralatan,
        ]);

        $this->dispatch('notify', type: 'success', message: 'Sharing fee berhasil disimpan.');
    }

    public function render()
    {
        return view('livewire.pengaturan.dokter.sharing-fee-form');
    }
}
