<?php

namespace App\Livewire\Laporan\Pemeriksaan;

use App\Exports\Laporan\RekapTindakanExport;
use App\Livewire\Laporan\BaseLaporanComponent;
use App\Services\Laporan\PemeriksaanLaporanService;
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Facades\Excel;

class RekapTindakanReport extends BaseLaporanComponent
{
    public function mount(): void
    {
        $this->mountPeriode();
    }

    public function generate(): void
    {
        [$mulai, $akhir] = $this->periodeRange;
        $this->hasil = app(PemeriksaanLaporanService::class)
            ->rekapTindakan($mulai, $akhir);
    }

    public function exportPdf()
    {
        [$mulai, $akhir] = $this->periodeRange;
        $data = app(PemeriksaanLaporanService::class)->rekapTindakan($mulai, $akhir);

        $pdf = Pdf::loadView('laporan.pdf.rekap-tindakan', [
            'data'  => $data,
            'label' => $this->periodeLabel,
        ])->setPaper('a4', 'portrait');

        return response()->streamDownload(
            fn () => print($pdf->output()),
            "Laporan-Tindakan-{$this->periodeLabel}.pdf"
        );
    }

    public function exportExcel()
    {
        [$mulai, $akhir] = $this->periodeRange;
        return Excel::download(
            new RekapTindakanExport($mulai, $akhir),
            "Laporan-Tindakan-{$this->periodeLabel}.xlsx"
        );
    }

    public function render()
    {
        return view('livewire.laporan.pemeriksaan.rekap-tindakan-report');
    }
}
