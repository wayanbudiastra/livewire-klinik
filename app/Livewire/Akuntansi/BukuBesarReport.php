<?php

namespace App\Livewire\Akuntansi;

use App\Models\Akuntansi\ChartOfAccount;
use App\Services\Laporan\AkuntansiLaporanService;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

class BukuBesarReport extends Component
{
    #[Url]
    public string $kodeAkun = '';
    #[Url]
    public string $dari = '';
    #[Url]
    public string $sampai = '';

    public function mount(): void
    {
        $this->dari   = $this->dari ?: now()->startOfMonth()->toDateString();
        $this->sampai = $this->sampai ?: now()->toDateString();
        $this->kodeAkun = $this->kodeAkun ?: (ChartOfAccount::aktif()->orderBy('kode')->value('kode') ?? '');
    }

    #[Computed]
    public function akunOptions()
    {
        return ChartOfAccount::aktif()->orderBy('kode')->get();
    }

    #[Computed]
    public function hasil(): ?array
    {
        if (!$this->kodeAkun) return null;

        return app(AkuntansiLaporanService::class)->bukuBesar($this->kodeAkun, $this->dari, $this->sampai);
    }

    public function render()
    {
        return view('livewire.akuntansi.buku-besar-report');
    }
}
