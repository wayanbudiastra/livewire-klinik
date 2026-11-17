<?php

namespace App\Livewire\Laporan\Kasir;

use App\Livewire\Laporan\BaseLaporanComponent;
use App\Services\Laporan\BhpKasirLaporanService;

class BhpKasirReport extends BaseLaporanComponent
{
    public function mount(): void
    {
        $this->authorize('laporan.kasir.view');
        $this->mountPeriode();
    }

    public function generate(): void
    {
        $this->authorize('laporan.kasir.view');

        [$mulai, $akhir] = $this->periodeRange;
        $this->hasil = app(BhpKasirLaporanService::class)
            ->laporan($mulai, $akhir, $this->userIdScope());
    }

    // Tombol PDF/Excel pada filter periode muncul untuk pemegang laporan.export;
    // laporan ini belum punya export, jadi cukup beri tahu pengguna.
    public function exportPdf(): void
    {
        $this->dispatch('notify', type: 'info', message: 'Export PDF belum tersedia untuk laporan ini.');
    }

    public function exportExcel(): void
    {
        $this->dispatch('notify', type: 'info', message: 'Export Excel belum tersedia untuk laporan ini.');
    }

    /** Tanpa laporan.kasir.view_all, hanya BHP yang dicatat user sendiri (sama dengan tab Cancel Bill). */
    private function userIdScope(): ?int
    {
        return auth()->user()->hasPermissionTo('laporan.kasir.view_all') ? null : auth()->id();
    }

    public function render()
    {
        return view('livewire.laporan.kasir.bhp-kasir-report');
    }
}
