<?php

namespace App\Livewire\Akuntansi;

use App\Services\Laporan\AkuntansiLaporanService;
use Livewire\Attributes\Computed;
use Livewire\Component;

class NeracaReport extends Component
{
    public string $tanggal           = '';
    public bool   $bandingkan        = false;
    public string $tanggalPembanding = '';

    public function mount(): void
    {
        $this->tanggal = now()->format('Y-m-d');
    }

    #[Computed]
    public function hasil(): array
    {
        return app(AkuntansiLaporanService::class)->neraca(
            $this->tanggal,
            $this->bandingkan && $this->tanggalPembanding ? $this->tanggalPembanding : null
        );
    }

    public function render()
    {
        return view('livewire.akuntansi.neraca-report');
    }
}
