<?php

namespace App\Livewire\Laporan\Pharmacy;

use App\Exports\Laporan\ObatFastMovingExport;
use App\Livewire\Laporan\BaseLaporanComponent;
use App\Services\Laporan\PharmacyLaporanService;
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Facades\Excel;

class ObatFastMovingReport extends BaseLaporanComponent
{
    public int $limit = 20;

    public function mount(): void
    {
        $this->mountPeriode();
    }

    public function generate(): void
    {
        [$mulai, $akhir] = $this->periodeRange;
        $this->hasil = app(PharmacyLaporanService::class)
            ->obatFastMoving($mulai, $akhir, $this->limit);
    }

    public function exportPdf()
    {
        [$mulai, $akhir] = $this->periodeRange;
        $data = app(PharmacyLaporanService::class)->obatFastMoving($mulai, $akhir, $this->limit);

        $pdf = Pdf::loadView('laporan.pdf.obat-fast-moving', [
            'data'  => $data,
            'label' => $this->periodeLabel,
        ])->setPaper('a4', 'portrait');

        return response()->streamDownload(
            fn () => print($pdf->output()),
            "Laporan-Fast-Moving-{$this->periodeLabel}.pdf"
        );
    }

    public function exportExcel()
    {
        [$mulai, $akhir] = $this->periodeRange;
        return Excel::download(
            new ObatFastMovingExport($mulai, $akhir, $this->limit),
            "Laporan-Fast-Moving-{$this->periodeLabel}.xlsx"
        );
    }

    public function render()
    {
        return view('livewire.laporan.pharmacy.obat-fast-moving-report');
    }
}
