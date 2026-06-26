<?php

namespace App\Livewire\Akuntansi;

use App\Services\Laporan\AkuntansiLaporanService;
use Livewire\Attributes\Computed;
use Livewire\Component;

class ArusKasReport extends Component
{
    public string $dari   = '';
    public string $sampai = '';

    public function mount(): void
    {
        $this->dari   = now()->startOfMonth()->format('Y-m-d');
        $this->sampai = now()->endOfMonth()->format('Y-m-d');
    }

    #[Computed]
    public function hasil(): array
    {
        return app(AkuntansiLaporanService::class)->arusKas($this->dari, $this->sampai);
    }

    public function render()
    {
        return view('livewire.akuntansi.arus-kas-report');
    }
}
