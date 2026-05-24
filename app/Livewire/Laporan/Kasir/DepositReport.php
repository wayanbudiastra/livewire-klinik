<?php

namespace App\Livewire\Laporan\Kasir;

use App\Exports\Laporan\DepositExport;
use App\Livewire\Laporan\BaseLaporanComponent;
use App\Services\Laporan\KasirLaporanService;
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Facades\Excel;

class DepositReport extends BaseLaporanComponent
{
    public function mount(): void
    {
        $this->mountPeriode();
    }

    public function generate(): void
    {
        [$mulai, $akhir] = $this->periodeRange;
        $this->hasil = app(KasirLaporanService::class)
            ->deposit($mulai, $akhir);
    }

    public function exportPdf()
    {
        [$mulai, $akhir] = $this->periodeRange;
        $data = app(KasirLaporanService::class)->deposit($mulai, $akhir);

        $pdf = Pdf::loadView('laporan.pdf.deposit', [
            'data'  => $data,
            'label' => $this->periodeLabel,
        ])->setPaper('a4', 'landscape');

        return response()->streamDownload(
            fn () => print($pdf->output()),
            "Laporan-Deposit-{$this->periodeLabel}.pdf"
        );
    }

    public function exportExcel()
    {
        [$mulai, $akhir] = $this->periodeRange;
        return Excel::download(
            new DepositExport($mulai, $akhir),
            "Laporan-Deposit-{$this->periodeLabel}.xlsx"
        );
    }

    public function render()
    {
        return view('livewire.laporan.kasir.deposit-report');
    }
}
