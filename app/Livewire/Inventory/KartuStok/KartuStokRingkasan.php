<?php

namespace App\Livewire\Inventory\KartuStok;

use App\Services\Inventory\KartuStokService;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

class KartuStokRingkasan extends Component
{
    #[Url]
    public string $tanggalMulai = '';
    #[Url]
    public string $tanggalAkhir = '';
    #[Url]
    public string $search = '';

    public function mount(): void
    {
        $this->tanggalMulai = now()->startOfMonth()->format('Y-m-d');
        $this->tanggalAkhir = now()->format('Y-m-d');
    }

    #[Computed]
    public function ringkasan()
    {
        return app(KartuStokService::class)
            ->getRingkasanMutasi($this->tanggalMulai, $this->tanggalAkhir)
            ->when($this->search, fn ($c) =>
                $c->filter(fn ($r) =>
                    str_contains(strtolower($r->barang?->nama ?? ''), strtolower($this->search))
                    || str_contains(strtolower($r->barang?->kode ?? ''), strtolower($this->search))
                )
            );
    }

    public function render()
    {
        return view('livewire.inventory.kartu-stok.kartu-stok-ringkasan');
    }
}
