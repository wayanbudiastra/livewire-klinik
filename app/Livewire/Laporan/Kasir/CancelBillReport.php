<?php

namespace App\Livewire\Laporan\Kasir;

use App\Exports\Laporan\CancelBillExport;
use App\Livewire\Laporan\BaseLaporanComponent;
use App\Services\Laporan\KasirLaporanService;
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Facades\Excel;

class CancelBillReport extends BaseLaporanComponent
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
            ->cancelBill($mulai, $akhir, $userId);
    }

    public function exportPdf()
    {
        [$mulai, $akhir] = $this->periodeRange;
        $userId = $this->userIdScope();
        $data = app(KasirLaporanService::class)->cancelBill($mulai, $akhir, $userId);

        $pdf = Pdf::loadView('laporan.pdf.cancel-bill', [
            'data'  => $data,
            'label' => $this->periodeLabel,
        ])->setPaper('a4', 'landscape');

        return response()->streamDownload(
            fn () => print($pdf->output()),
            "Laporan-Cancel-Bill-{$this->periodeLabel}.pdf"
        );
    }

    public function exportExcel()
    {
        [$mulai, $akhir] = $this->periodeRange;
        $userId = $this->userIdScope();
        return Excel::download(
            new CancelBillExport($mulai, $akhir, $userId),
            "Laporan-Cancel-Bill-{$this->periodeLabel}.xlsx"
        );
    }

    /**
     * Audit Priority 4 (Tinggi): sebelumnya laporan ini SELALU menampilkan
     * data SEMUA kasir tanpa terkecuali, padahal 1 halaman dgn tab
     * "Transaksi Kasir" sudah benar membatasi ke transaksi milik sendiri
     * kalau user tidak punya laporan.kasir.view_all -- tab "Cancel Bill"
     * ini (dan "Deposit") jadi kebocoran data krn tidak ikut membatasi.
     */
    private function userIdScope(): ?int
    {
        return auth()->user()->hasPermissionTo('laporan.kasir.view_all') ? null : auth()->id();
    }

    public function render()
    {
        return view('livewire.laporan.kasir.cancel-bill-report');
    }
}
