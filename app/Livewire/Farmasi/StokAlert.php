<?php

namespace App\Livewire\Farmasi;

use App\Services\FarmasiService;
use Livewire\Attributes\Computed;
use Livewire\Component;

class StokAlert extends Component
{
    public int $hariExpired = 90;

    #[Computed]
    public function reorderList()
    {
        return app(FarmasiService::class)->getReorderAlert();
    }

    #[Computed]
    public function akanExpiredList()
    {
        return app(FarmasiService::class)->getBatchAkanExpired($this->hariExpired);
    }

    #[Computed]
    public function sudahExpiredList()
    {
        return app(FarmasiService::class)->getBatchExpired();
    }

    public function render()
    {
        return view('livewire.farmasi.stok-alert');
    }
}
