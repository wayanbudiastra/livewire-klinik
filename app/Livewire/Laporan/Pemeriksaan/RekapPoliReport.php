<?php

namespace App\Livewire\Laporan\Pemeriksaan;

use App\Livewire\Laporan\BaseLaporanComponent;
use App\Services\Laporan\PemeriksaanLaporanService;
use Barryvdh\DomPDF\Facade\Pdf;

class RekapPoliReport extends BaseLaporanComponent
{
    public function mount(): void
    {
        $this->mountPeriode();
    }

    public function generate(): void
    {
        [$mulai, $akhir] = $this->periodeRange;
        $this->hasil = app(PemeriksaanLaporanService::class)
            ->rekapPerPoli($mulai, $akhir);
    }

    public function exportPdf()
    {
        [$mulai, $akhir] = $this->periodeRange;
        $data = app(PemeriksaanLaporanService::class)->rekapPerPoli($mulai, $akhir);

        $pdf = Pdf::loadView('laporan.pdf.rekap-poli', [
            'data'  => $data,
            'label' => $this->periodeLabel,
        ])->setPaper('a4', 'portrait');

        return response()->streamDownload(
            fn () => print($pdf->output()),
            "Laporan-Per-Poli-{$this->periodeLabel}.pdf"
        );
    }

    public function exportExcel()
    {
        return null;
    }

    public function render()
    {
        return view('livewire.laporan.pemeriksaan.rekap-poli-report');
    }
}
