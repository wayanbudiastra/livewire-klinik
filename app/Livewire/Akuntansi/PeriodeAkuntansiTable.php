<?php

namespace App\Livewire\Akuntansi;

use App\Models\Akuntansi\PeriodeAkuntansi;
use App\Services\Akuntansi\PeriodeAkuntansiService;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Computed;
use Livewire\Component;

class PeriodeAkuntansiTable extends Component
{
    public bool   $showBukaKembali       = false;
    public ?int   $periodeIdBukaKembali  = null;
    public string $passwordBukaKembali   = '';
    public string $alasanBukaKembali     = '';
    public string $errorMsg              = '';

    #[Computed]
    public function periodeList(): array
    {
        $service = app(PeriodeAkuntansiService::class);
        $rows    = [];

        // Bulan berjalan + 12 bulan ke belakang, terbaru di atas.
        $cursor       = now()->startOfMonth();
        $bulanIniAwal = now()->startOfMonth();

        for ($i = 0; $i <= 12; $i++) {
            $tahun = $cursor->year;
            $bulan = $cursor->month;

            // Tenggat closing yang dianjurkan: tanggal 5 bulan berikutnya.
            $tenggat       = $cursor->copy()->addMonth()->startOfMonth()->addDays(4);
            $bukanBulanIni = ! $cursor->isSameMonth($bulanIniAwal);

            $periode = $service->getAtauBuat($tahun, $bulan);
            $rows[]  = [
                'periode'       => $periode,
                'sisa_pending'  => $periode->status === 'terbuka' ? $service->sisaPending($tahun, $bulan) : 0,
                'lewat_tenggat' => $periode->status === 'terbuka' && $bukanBulanIni && now()->gt($tenggat),
            ];

            $cursor->subMonth();
        }

        return $rows;
    }

    public function tutup(int $tahun, int $bulan, PeriodeAkuntansiService $service): void
    {
        try {
            $service->tutup($tahun, $bulan, auth()->id());
            unset($this->periodeList);
            $this->dispatch('notify', type: 'success', message: 'Periode berhasil ditutup.');
        } catch (\DomainException $e) {
            $this->dispatch('notify', type: 'error', message: $e->getMessage());
        }
    }

    public function konfirmasiBukaKembali(int $periodeId): void
    {
        $this->periodeIdBukaKembali = $periodeId;
        $this->passwordBukaKembali  = '';
        $this->alasanBukaKembali    = '';
        $this->errorMsg             = '';
        $this->showBukaKembali      = true;
    }

    public function bukaKembali(PeriodeAkuntansiService $service): void
    {
        $this->validate([
            'passwordBukaKembali' => ['required', 'string'],
            'alasanBukaKembali'   => ['required', 'string', 'min:10'],
        ]);

        try {
            $periode = PeriodeAkuntansi::findOrFail($this->periodeIdBukaKembali);
            $service->bukaKembali(
                $periode->tahun,
                $periode->bulan,
                $this->passwordBukaKembali,
                $this->alasanBukaKembali,
                auth()->id()
            );

            $this->showBukaKembali      = false;
            $this->periodeIdBukaKembali = null;
            $this->passwordBukaKembali  = '';
            $this->alasanBukaKembali    = '';
            unset($this->periodeList);
            $this->dispatch('notify', type: 'success', message: 'Periode berhasil dibuka kembali.');
        } catch (\Exception $e) {
            $this->errorMsg = $e->getMessage();
        }
    }

    public function render()
    {
        return view('livewire.akuntansi.periode-akuntansi-table');
    }
}
