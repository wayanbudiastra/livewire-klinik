<?php

namespace App\Livewire;

use App\Models\Invoice;
use App\Models\Kunjungan;
use App\Models\Pasien;
use Carbon\Carbon;
use Livewire\Component;

class Dashboard extends Component
{
    public function mount(): void
    {
        $this->dispatchChartData();
    }

    public function refreshData(): void
    {
        $this->dispatchChartData();
    }

    // ── Stats ────────────────────────────────────────────────

    public function getKunjunganHariIniProperty(): int
    {
        return Kunjungan::whereDate('tanggal', today())
            ->where('status', '!=', 'dibatalkan')
            ->count();
    }

    public function getPasienBaruBulanIniProperty(): int
    {
        return Pasien::whereYear('created_at', now()->year)
            ->whereMonth('created_at', now()->month)
            ->count();
    }

    public function getMenungguPemeriksaanProperty(): int
    {
        return Kunjungan::whereDate('tanggal', today())
            ->where('status', 'menunggu')
            ->count();
    }

    public function getPendapatanHariIniProperty(): float
    {
        return (float) Invoice::whereDate('updated_at', today())
            ->where('status', 'lunas')
            ->sum('total_bayar');
    }

    // ── Chart Data (15 hari terakhir) ────────────────────────

    private function buildChartData(): array
    {
        $mulai = now()->subDays(14)->startOfDay();
        $akhir = now()->endOfDay();

        $kunjungan = Kunjungan::whereBetween('tanggal', [$mulai, $akhir])
            ->where('status', '!=', 'dibatalkan')
            ->with('pasien:id,created_at')
            ->get();

        $byDay = $kunjungan->groupBy(fn ($k) => Carbon::parse($k->tanggal)->format('Y-m-d'));

        $labels = [];
        $total  = [];
        $baru   = [];
        $lama   = [];

        for ($i = 14; $i >= 0; $i--) {
            $day     = now()->subDays($i)->startOfDay();
            $dateStr = $day->format('Y-m-d');
            $dayData = $byDay->get($dateStr, collect());

            $labels[] = $day->format('d/m');

            $jumlahTotal = $dayData->count();
            $jumlahBaru  = $dayData->filter(fn ($k) =>
                $k->pasien && Carbon::parse($k->pasien->created_at)->format('Y-m-d') === $dateStr
            )->count();

            $total[]  = $jumlahTotal;
            $baru[]   = $jumlahBaru;
            $lama[]   = $jumlahTotal - $jumlahBaru;
        }

        return compact('labels', 'total', 'baru', 'lama');
    }

    private function dispatchChartData(): void
    {
        $chart = $this->buildChartData();
        $this->dispatch('dashboard-chart-update',
            labels: $chart['labels'],
            total:  $chart['total'],
            baru:   $chart['baru'],
            lama:   $chart['lama'],
        );
    }

    public function render()
    {
        return view('livewire.dashboard', [
            'kunjunganHariIni'     => $this->kunjunganHariIni,
            'pasienBaruBulanIni'   => $this->pasienBaruBulanIni,
            'menungguPemeriksaan'  => $this->menungguPemeriksaan,
            'pendapatanHariIni'    => $this->pendapatanHariIni,
        ]);
    }
}
