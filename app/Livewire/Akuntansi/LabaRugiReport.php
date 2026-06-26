<?php

namespace App\Livewire\Akuntansi;

use App\Services\Laporan\AkuntansiLaporanService;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

class LabaRugiReport extends Component
{
    #[Url]
    public string $dari = '';
    #[Url]
    public string $sampai = '';

    public function mount(): void
    {
        $this->dari   = $this->dari ?: now()->startOfMonth()->toDateString();
        $this->sampai = $this->sampai ?: now()->toDateString();
    }

    #[Computed]
    public function hasil(): array
    {
        return app(AkuntansiLaporanService::class)->labaRugi($this->dari, $this->sampai);
    }

    public function render()
    {
        return view('livewire.akuntansi.laba-rugi-report');
    }
}
