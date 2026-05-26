<?php

namespace App\Livewire\Pengaturan\Asuransi;

use App\Models\ConfigBpjs;
use Livewire\Component;

class ConfigBpjsForm extends Component
{
    public bool   $kerjasama        = false;
    public bool   $isActive         = false;
    public string $kodeFaskes       = '';
    public string $namaFaskes       = '';
    public string $tanggalKerjasama = '';
    public string $tanggalBerakhir  = '';
    public string $catatan          = '';

    public function mount(): void
    {
        $config = ConfigBpjs::firstOrCreate(['id' => 1], [
            'kerjasama' => false, 'is_active' => false,
        ]);
        $this->kerjasama        = $config->kerjasama;
        $this->isActive         = $config->is_active;
        $this->kodeFaskes       = $config->kode_faskes ?? '';
        $this->namaFaskes       = $config->nama_faskes ?? '';
        $this->tanggalKerjasama = $config->tanggal_kerjasama?->format('Y-m-d') ?? '';
        $this->tanggalBerakhir  = $config->tanggal_berakhir?->format('Y-m-d') ?? '';
        $this->catatan          = $config->catatan ?? '';
    }

    public function updatedKerjasama($value): void
    {
        if (!$value) {
            $this->isActive = false;
        }
    }

    public function simpan(): void
    {
        $this->validate([
            'kodeFaskes'       => ['nullable', 'string', 'max:30'],
            'namaFaskes'       => ['nullable', 'string', 'max:150'],
            'tanggalKerjasama' => ['nullable', 'date'],
            'tanggalBerakhir'  => ['nullable', 'date', 'after_or_equal:tanggalKerjasama'],
        ]);

        $isActive = $this->kerjasama ? $this->isActive : false;

        ConfigBpjs::updateOrCreate(['id' => 1], [
            'kerjasama'         => $this->kerjasama,
            'is_active'         => $isActive,
            'kode_faskes'       => $this->kodeFaskes ?: null,
            'nama_faskes'       => $this->namaFaskes ?: null,
            'tanggal_kerjasama' => $this->tanggalKerjasama ?: null,
            'tanggal_berakhir'  => $this->tanggalBerakhir ?: null,
            'catatan'           => $this->catatan ?: null,
        ]);

        $this->isActive = $isActive;
        session()->flash('success', 'Konfigurasi BPJS berhasil disimpan.');
    }

    public function render()
    {
        return view('livewire.pengaturan.asuransi.config-bpjs-form');
    }
}
