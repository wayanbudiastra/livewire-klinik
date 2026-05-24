<?php

namespace App\Livewire\Laporan\Registrasi;

use App\Exports\Laporan\KunjunganPasienExport;
use App\Livewire\Laporan\BaseLaporanComponent;
use App\Services\Laporan\RegistrasiLaporanService;
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Facades\Excel;

class KunjunganPasienReport extends BaseLaporanComponent
{
    public function mount(): void
    {
        $this->mountPeriode();
    }

    public function generate(): void
    {
        [$mulai, $akhir] = $this->periodeRange;
        $this->hasil = app(RegistrasiLaporanService::class)
            ->kunjunganPasien($mulai, $akhir);
    }

    public function exportPdf()
    {
        [$mulai, $akhir] = $this->periodeRange;
        $data = app(RegistrasiLaporanService::class)->kunjunganPasien($mulai, $akhir);

        $pdf = Pdf::loadView('laporan.pdf.kunjungan-pasien', [
            'data'  => $data,
            'label' => $this->periodeLabel,
        ])->setPaper('a4', 'landscape');

        return response()->streamDownload(
            fn () => print($pdf->output()),
            "Laporan-Kunjungan-{$this->periodeLabel}.pdf"
        );
    }

    public function exportExcel()
    {
        [$mulai, $akhir] = $this->periodeRange;
        return Excel::download(
            new KunjunganPasienExport($mulai, $akhir),
            "Laporan-Kunjungan-{$this->periodeLabel}.xlsx"
        );
    }

    public function render()
    {
        return view('livewire.laporan.registrasi.kunjungan-pasien-report');
    }
}
