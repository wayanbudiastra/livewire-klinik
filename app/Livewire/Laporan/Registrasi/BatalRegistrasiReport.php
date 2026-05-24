<?php

namespace App\Livewire\Laporan\Registrasi;

use App\Exports\Laporan\BatalRegistrasiExport;
use App\Livewire\Laporan\BaseLaporanComponent;
use App\Services\Laporan\RegistrasiLaporanService;
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Facades\Excel;

class BatalRegistrasiReport extends BaseLaporanComponent
{
    public function mount(): void
    {
        $this->mountPeriode();
    }

    public function generate(): void
    {
        [$mulai, $akhir] = $this->periodeRange;
        $this->hasil = app(RegistrasiLaporanService::class)
            ->batalRegistrasi($mulai, $akhir);
    }

    public function exportPdf()
    {
        [$mulai, $akhir] = $this->periodeRange;
        $data = app(RegistrasiLaporanService::class)->batalRegistrasi($mulai, $akhir);

        $pdf = Pdf::loadView('laporan.pdf.batal-registrasi', [
            'data'  => $data,
            'label' => $this->periodeLabel,
        ])->setPaper('a4', 'landscape');

        return response()->streamDownload(
            fn () => print($pdf->output()),
            "Laporan-Batal-Registrasi-{$this->periodeLabel}.pdf"
        );
    }

    public function exportExcel()
    {
        [$mulai, $akhir] = $this->periodeRange;
        return Excel::download(
            new BatalRegistrasiExport($mulai, $akhir),
            "Laporan-Batal-Registrasi-{$this->periodeLabel}.xlsx"
        );
    }

    public function render()
    {
        return view('livewire.laporan.registrasi.batal-registrasi-report');
    }
}
