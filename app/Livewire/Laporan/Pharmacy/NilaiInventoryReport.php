<?php

namespace App\Livewire\Laporan\Pharmacy;

use App\Exports\Laporan\NilaiInventoryExport;
use App\Livewire\Laporan\BaseLaporanComponent;
use App\Services\Laporan\PharmacyLaporanService;
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Facades\Excel;

class NilaiInventoryReport extends BaseLaporanComponent
{
    public function mount(): void
    {
        $this->mountPeriode();
    }

    public function generate(): void
    {
        $this->hasil = app(PharmacyLaporanService::class)->nilaiInventory();
    }

    public function exportPdf()
    {
        $data = app(PharmacyLaporanService::class)->nilaiInventory();

        $pdf = Pdf::loadView('laporan.pdf.nilai-inventory', [
            'data'  => $data,
            'label' => 'Snapshot ' . now()->format('d/m/Y H:i'),
        ])->setPaper('a4', 'portrait');

        return response()->streamDownload(
            fn () => print($pdf->output()),
            'Laporan-Nilai-Inventory-' . now()->format('Ymd-His') . '.pdf'
        );
    }

    public function exportExcel()
    {
        return Excel::download(
            new NilaiInventoryExport(),
            'Laporan-Nilai-Inventory-' . now()->format('Ymd-His') . '.xlsx'
        );
    }

    public function render()
    {
        return view('livewire.laporan.pharmacy.nilai-inventory-report');
    }
}
