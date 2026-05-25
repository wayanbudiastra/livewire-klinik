<?php

namespace App\Livewire\Laporan\Pemeriksaan;

use App\Exports\Laporan\RekapDiagnosaExport;
use App\Livewire\Laporan\BaseLaporanComponent;
use App\Services\Laporan\PemeriksaanLaporanService;
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Facades\Excel;

class RekapDiagnosaReport extends BaseLaporanComponent
{
    public int $topN = 10;

    public function mount(): void
    {
        $this->mountPeriode();
    }

    public function generate(): void
    {
        [$mulai, $akhir] = $this->periodeRange;
        $this->hasil = app(PemeriksaanLaporanService::class)
            ->rekapDiagnosa($mulai, $akhir, $this->topN);

        $this->dispatch('diagnosa-chart-update',
            labels: array_keys($this->hasil['n_besar']),
            values: array_values($this->hasil['n_besar']),
        );
    }

    public function updatedTopN(): void
    {
        if ($this->hasil !== null) {
            $this->generate();
        }
    }

    public function exportPdf()
    {
        [$mulai, $akhir] = $this->periodeRange;
        $data = app(PemeriksaanLaporanService::class)
            ->rekapDiagnosa($mulai, $akhir, $this->topN);

        $pdf = Pdf::loadView('laporan.pdf.rekap-diagnosa', [
            'data'  => $data,
            'topN'  => $this->topN,
            'label' => $this->periodeLabel,
        ])->setPaper('a4', 'portrait');

        return response()->streamDownload(
            fn () => print($pdf->output()),
            "Laporan-Diagnosa-{$this->periodeLabel}.pdf"
        );
    }

    public function exportExcel()
    {
        [$mulai, $akhir] = $this->periodeRange;
        return Excel::download(
            new RekapDiagnosaExport($mulai, $akhir),
            "Laporan-Diagnosa-{$this->periodeLabel}.xlsx"
        );
    }

    public function render()
    {
        return view('livewire.laporan.pemeriksaan.rekap-diagnosa-report');
    }
}
