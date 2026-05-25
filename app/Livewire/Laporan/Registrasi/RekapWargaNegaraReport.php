<?php

namespace App\Livewire\Laporan\Registrasi;

use App\Livewire\Laporan\BaseLaporanComponent;
use App\Services\Laporan\RegistrasiLaporanService;
use Barryvdh\DomPDF\Facade\Pdf;

class RekapWargaNegaraReport extends BaseLaporanComponent
{
    public function mount(): void
    {
        $this->mountPeriode();
    }

    public function generate(): void
    {
        [$mulai, $akhir] = $this->periodeRange;
        $this->hasil = app(RegistrasiLaporanService::class)
            ->rekapWargaNegara($mulai, $akhir);

        $this->dispatch('wna-chart-update',
            kunjungan_wni: $this->hasil['kunjungan_wni'],
            kunjungan_wna: $this->hasil['kunjungan_wna'],
            total_wni:     $this->hasil['total_wni'],
            total_wna:     $this->hasil['total_wna'],
        );
    }

    public function exportPdf()
    {
        [$mulai, $akhir] = $this->periodeRange;
        $data = app(RegistrasiLaporanService::class)->rekapWargaNegara($mulai, $akhir);

        $pdf = Pdf::loadView('laporan.pdf.rekap-warga-negara', [
            'data'  => $data,
            'label' => $this->periodeLabel,
        ])->setPaper('a4', 'portrait');

        return response()->streamDownload(
            fn () => print($pdf->output()),
            "Laporan-WNA-{$this->periodeLabel}.pdf"
        );
    }

    public function exportExcel()
    {
        return null;
    }

    public function render()
    {
        return view('livewire.laporan.registrasi.rekap-warga-negara-report');
    }
}
