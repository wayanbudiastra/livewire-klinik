<?php

namespace App\Livewire\Laporan\Registrasi;

use App\Livewire\Laporan\BaseLaporanComponent;
use App\Services\Laporan\RegistrasiLaporanService;

class SumberInformasiReport extends BaseLaporanComponent
{
    public function mount(): void
    {
        $this->mountPeriode();
    }

    public function generate(): void
    {
        [$mulai, $akhir] = $this->periodeRange;
        $this->hasil = app(RegistrasiLaporanService::class)
            ->sumberInformasi($mulai, $akhir);
    }

    public function exportPdf()
    {
        return null;
    }

    public function exportExcel()
    {
        return null;
    }

    public function render()
    {
        return view('livewire.laporan.registrasi.sumber-informasi-report');
    }
}
