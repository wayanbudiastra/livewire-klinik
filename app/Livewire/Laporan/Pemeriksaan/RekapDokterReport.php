<?php

namespace App\Livewire\Laporan\Pemeriksaan;

use App\Livewire\Laporan\BaseLaporanComponent;
use App\Services\Laporan\PemeriksaanLaporanService;
use Barryvdh\DomPDF\Facade\Pdf;

class RekapDokterReport extends BaseLaporanComponent
{
    public function mount(): void
    {
        $this->mountPeriode();
    }

    public function generate(): void
    {
        [$mulai, $akhir] = $this->periodeRange;
        $this->hasil = app(PemeriksaanLaporanService::class)
            ->rekapPerDokter($mulai, $akhir);
    }

    public function exportPdf()
    {
        [$mulai, $akhir] = $this->periodeRange;
        $data = app(PemeriksaanLaporanService::class)->rekapPerDokter($mulai, $akhir);

        $pdf = Pdf::loadView('laporan.pdf.rekap-dokter', [
            'data'  => $data,
            'label' => $this->periodeLabel,
        ])->setPaper('a4', 'portrait');

        return response()->streamDownload(
            fn () => print($pdf->output()),
            "Laporan-Per-Dokter-{$this->periodeLabel}.pdf"
        );
    }

    public function exportExcel()
    {
        return null;
    }

    public function render()
    {
        return view('livewire.laporan.pemeriksaan.rekap-dokter-report');
    }
}
