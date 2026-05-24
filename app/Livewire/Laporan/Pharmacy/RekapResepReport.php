<?php

namespace App\Livewire\Laporan\Pharmacy;

use App\Exports\Laporan\RekapResepExport;
use App\Livewire\Laporan\BaseLaporanComponent;
use App\Services\Laporan\PharmacyLaporanService;
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Facades\Excel;

class RekapResepReport extends BaseLaporanComponent
{
    public function mount(): void
    {
        $this->mountPeriode();
    }

    public function generate(): void
    {
        [$mulai, $akhir] = $this->periodeRange;
        $this->hasil = app(PharmacyLaporanService::class)
            ->rekapResep($mulai, $akhir);
    }

    public function exportPdf()
    {
        [$mulai, $akhir] = $this->periodeRange;
        $data = app(PharmacyLaporanService::class)->rekapResep($mulai, $akhir);

        $pdf = Pdf::loadView('laporan.pdf.rekap-resep', [
            'data'  => $data,
            'label' => $this->periodeLabel,
        ])->setPaper('a4', 'portrait');

        return response()->streamDownload(
            fn () => print($pdf->output()),
            "Laporan-Resep-{$this->periodeLabel}.pdf"
        );
    }

    public function exportExcel()
    {
        [$mulai, $akhir] = $this->periodeRange;
        return Excel::download(
            new RekapResepExport($mulai, $akhir),
            "Laporan-Resep-{$this->periodeLabel}.xlsx"
        );
    }

    public function render()
    {
        return view('livewire.laporan.pharmacy.rekap-resep-report');
    }
}
