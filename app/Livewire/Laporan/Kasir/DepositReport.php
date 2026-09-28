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
        $userId = $this->userIdScope();
        $this->hasil = app(KasirLaporanService::class)
            ->deposit($mulai, $akhir, $userId);
    }

    public function exportPdf()
    {
        [$mulai, $akhir] = $this->periodeRange;
        $userId = $this->userIdScope();
        $data = app(KasirLaporanService::class)->deposit($mulai, $akhir, $userId);

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
        $userId = $this->userIdScope();
        return Excel::download(
            new DepositExport($mulai, $akhir, $userId),
            "Laporan-Deposit-{$this->periodeLabel}.xlsx"
        );
    }

    /**
     * Audit Priority 4 (Tinggi): sebelumnya laporan ini SELALU menampilkan
     * transaksi deposit SEMUA kasir tanpa terkecuali -- lihat catatan yang
     * sama di CancelBillReport::userIdScope().
     */
    private function userIdScope(): ?int
    {
        return auth()->user()->hasPermissionTo('laporan.kasir.view_all') ? null : auth()->id();
    }

    public function render()
    {
        return view('livewire.laporan.kasir.deposit-report');
    }
}
