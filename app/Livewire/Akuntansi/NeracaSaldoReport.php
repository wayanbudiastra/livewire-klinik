<?php

namespace App\Livewire\Akuntansi;

use App\Services\Laporan\AkuntansiLaporanService;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

class NeracaSaldoReport extends Component
{
    #[Url]
    public string $sampai = '';

    public function mount(): void
    {
        $this->sampai = $this->sampai ?: now()->toDateString();
    }

    #[Computed]
    public function hasil(): array
    {
        return app(AkuntansiLaporanService::class)->neracaSaldo($this->sampai);
    }

    public function render()
    {
        return view('livewire.akuntansi.neraca-saldo-report');
    }
}
