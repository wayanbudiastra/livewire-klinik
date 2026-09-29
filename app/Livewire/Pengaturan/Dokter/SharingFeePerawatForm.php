<?php

namespace App\Livewire\Pengaturan\Dokter;

use App\Models\KonfigurasiSharingFeePerawat;
use Livewire\Component;

/**
 * Beda dari SharingFeeForm (per-dokter individu): ini 1 form utk 1
 * persentase GLOBAL yang berlaku sama ke semua perawat (kategori
 * 'tindakan', dihitung dari Tindakan::pelaksana_id -- lihat
 * SharingFeeService::catatSharingFeePerawat()). Ditaruh di halaman yang
 * sama dgn Data Dokter krn topiknya sama-sama "sharing fee".
 */
class SharingFeePerawatForm extends Component
{
    public string $fee_tindakan = '0';

    public function mount(): void
    {
        $this->fee_tindakan = (string) KonfigurasiSharingFeePerawat::persentase('tindakan');
    }

    public function save(): void
    {
        $this->authorize('masterdata.edit');
        $this->validate([
            'fee_tindakan' => 'required|numeric|min:0|max:100',
        ], [
            'fee_tindakan.min' => 'Persentase minimal 0%.',
            'fee_tindakan.max' => 'Persentase maksimal 100%.',
        ]);

        KonfigurasiSharingFeePerawat::updateOrCreate(
            ['kategori' => 'tindakan'],
            ['persentase' => (float) $this->fee_tindakan, 'updated_by' => auth()->id()]
        );
        KonfigurasiSharingFeePerawat::clearCache('tindakan');

        $this->dispatch('notify', type: 'success', message: 'Sharing fee perawat berhasil disimpan.');
    }

    public function render()
    {
        return view('livewire.pengaturan.dokter.sharing-fee-perawat-form');
    }
}
