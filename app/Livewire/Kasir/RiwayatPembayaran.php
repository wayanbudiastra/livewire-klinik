<?php

namespace App\Livewire\Kasir;

use App\Models\Invoice;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

class RiwayatPembayaran extends Component
{
    use WithPagination;

    public string $filterTanggal = '';
    public string $searchNama    = '';

    public function mount(): void
    {
        $this->filterTanggal = today()->format('Y-m-d');
    }

    public function updatedFilterTanggal(): void
    {
        $this->resetPage();
    }

    public function updatedSearchNama(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function invoices()
    {
        return Invoice::with([
                'kunjungan.pasien',
                'kunjungan.poli',
                'kunjungan.dokter',
                'pembayaran',
            ])
            ->where('status', 'lunas')
            ->when(
                $this->filterTanggal,
                fn ($q) => $q->whereDate('updated_at', $this->filterTanggal)
            )
            ->when(
                $this->searchNama,
                fn ($q) => $q->whereHas(
                    'kunjungan.pasien',
                    fn ($p) => $p->where('nama', 'like', "%{$this->searchNama}%")
                                 ->orWhere('nomor_rm', 'like', "%{$this->searchNama}%")
                )
            )
            ->orderByDesc('updated_at')
            ->paginate(15);
    }

    #[Computed]
    public function totalHariIni(): array
    {
        $invoices = Invoice::with('pembayaran')
            ->where('status', 'lunas')
            ->when(
                $this->filterTanggal,
                fn ($q) => $q->whereDate('updated_at', $this->filterTanggal)
            )
            ->get();

        $totalTunai    = 0;
        $totalNonTunai = 0;
        $totalAsuransi = 0;

        foreach ($invoices as $inv) {
            foreach ($inv->pembayaran as $p) {
                if ($p->metode === 'tunai')         $totalTunai    += $p->jumlah;
                elseif ($p->metode === 'non_tunai') $totalNonTunai += $p->jumlah;
                else                                $totalAsuransi += $p->jumlah;
            }
        }

        return [
            'count'      => $invoices->count(),
            'tunai'      => $totalTunai,
            'non_tunai'  => $totalNonTunai,
            'asuransi'   => $totalAsuransi,
            'grand'      => $totalTunai + $totalNonTunai + $totalAsuransi,
        ];
    }

    public function render()
    {
        return view('livewire.kasir.riwayat-pembayaran');
    }
}
