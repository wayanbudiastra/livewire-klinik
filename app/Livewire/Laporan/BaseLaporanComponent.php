<?php

namespace App\Livewire\Laporan;

use Livewire\Component;
use App\Support\PeriodeHelper;

abstract class BaseLaporanComponent extends Component
{
    public string $tipePeriode  = 'bulanan';
    public int    $tahun        = 0;
    public int    $bulan        = 0;
    public int    $triwulan     = 1;
    public int    $semester     = 1;
    public string $tanggalMulai = '';
    public string $tanggalAkhir = '';

    public ?array $hasil = null;

    public function mountPeriode(): void
    {
        $this->tahun = now()->year;
        $this->bulan = now()->month;
    }

    public function getPeriodeRangeProperty(): array
    {
        return PeriodeHelper::resolve($this->tipePeriode, [
            'tahun'         => $this->tahun,
            'bulan'         => $this->bulan,
            'triwulan'      => $this->triwulan,
            'semester'      => $this->semester,
            'tanggal_mulai' => $this->tanggalMulai ?: now()->startOfMonth()->format('Y-m-d'),
            'tanggal_akhir' => $this->tanggalAkhir ?: now()->endOfMonth()->format('Y-m-d'),
        ]);
    }

    public function getPeriodeLabelProperty(): string
    {
        return PeriodeHelper::label($this->tipePeriode, [
            'tahun'         => $this->tahun,
            'bulan'         => $this->bulan,
            'triwulan'      => $this->triwulan,
            'semester'      => $this->semester,
            'tanggal_mulai' => $this->tanggalMulai,
            'tanggal_akhir' => $this->tanggalAkhir,
        ]);
    }

    abstract public function generate(): void;
}
