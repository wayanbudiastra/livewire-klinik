<?php

namespace App\Livewire\Laporan\Kasir;

use App\Exports\Laporan\TransaksiKasirExport;
use App\Livewire\Laporan\BaseLaporanComponent;
use App\Services\Laporan\KasirLaporanService;
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Facades\Excel;

class TransaksiKasirReport extends BaseLaporanComponent
{
    public function mount(): void
    {
        $this->mountPeriode();
    }

    public function generate(): void
    {
        [$mulai, $akhir] = $this->periodeRange;

        $userId = auth()->user()->hasPermissionTo('laporan.kasir.view_all')
            ? null
            : auth()->id();

        $this->hasil = app(KasirLaporanService::class)
            ->transaksiKasir($mulai, $akhir, $userId);
    }

    public function exportPdf()
    {
        [$mulai, $akhir] = $this->periodeRange;
        $userId = auth()->user()->hasPermissionTo('laporan.kasir.view_all') ? null : auth()->id();
        $data = app(KasirLaporanService::class)->transaksiKasir($mulai, $akhir, $userId);

        $pdf = Pdf::loadView('laporan.pdf.transaksi-kasir', [
            'data'  => $data,
            'label' => $this->periodeLabel,
        ])->setPaper('a4', 'landscape');

        return response()->streamDownload(
            fn () => print($pdf->output()),
            "Laporan-Transaksi-Kasir-{$this->periodeLabel}.pdf"
        );
    }

    public function exportExcel()
    {
        [$mulai, $akhir] = $this->periodeRange;
        $userId = auth()->user()->hasPermissionTo('laporan.kasir.view_all') ? null : auth()->id();
        return Excel::download(
            new TransaksiKasirExport($mulai, $akhir, $userId),
            "Laporan-Transaksi-Kasir-{$this->periodeLabel}.xlsx"
        );
    }

    public function render()
    {
        return view('livewire.laporan.kasir.transaksi-kasir-report');
    }
}
