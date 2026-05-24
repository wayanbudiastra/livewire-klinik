<?php

namespace App\Livewire\Laporan\Registrasi;

use App\Livewire\Laporan\BaseLaporanComponent;
use App\Services\Laporan\RegistrasiLaporanService;
use Barryvdh\DomPDF\Facade\Pdf;

class AppointmentReport extends BaseLaporanComponent
{
    public function mount(): void
    {
        $this->mountPeriode();
    }

    public function generate(): void
    {
        [$mulai, $akhir] = $this->periodeRange;
        $this->hasil = app(RegistrasiLaporanService::class)
            ->appointment($mulai, $akhir);
    }

    public function exportPdf()
    {
        [$mulai, $akhir] = $this->periodeRange;
        $data = app(RegistrasiLaporanService::class)->appointment($mulai, $akhir);

        $pdf = Pdf::loadView('laporan.pdf.appointment', [
            'data'  => $data,
            'label' => $this->periodeLabel,
        ])->setPaper('a4', 'landscape');

        return response()->streamDownload(
            fn () => print($pdf->output()),
            "Laporan-Appointment-{$this->periodeLabel}.pdf"
        );
    }

    public function exportExcel()
    {
        return null;
    }

    public function render()
    {
        return view('livewire.laporan.registrasi.appointment-report');
    }
}
