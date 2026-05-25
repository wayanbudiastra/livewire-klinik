<?php

namespace App\Livewire\Kasir;

use App\Models\Invoice;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
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

    public function updatedFilterTanggal(): void { $this->resetPage(); }
    public function updatedSearchNama(): void    { $this->resetPage(); }

    public function batalkan(int $billingId): void
    {
        $this->dispatch('openBatalkanModal',
            billingId:  $billingId,
            redirectTo: route('kasir.billing.index') . '?tab=riwayat',
        );
    }

    #[On('billingDibatalkan')]
    public function refreshList(): void
    {
        unset($this->invoices, $this->totalHariIni);
        $this->resetPage();
    }

    #[Computed]
    public function invoices()
    {
        return Invoice::with([
                'kunjungan.pasien',
                'kunjungan.poli',
                'kunjungan.dokter.user',
                'pembayaranSplit',
            ])
            ->whereIn('status', ['lunas', 'dibatalkan'])
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
        $invoices = Invoice::with('pembayaranSplit')
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
            foreach ($inv->pembayaranSplit as $p) {
                match ($p->metode) {
                    'tunai'                              => $totalTunai    += $p->jumlah,
                    'debit', 'kredit', 'transfer', 'qris',
                    'deposit'                            => $totalNonTunai += $p->jumlah,
                    'bpjs', 'asuransi'                   => $totalAsuransi += $p->jumlah,
                    default                              => $totalNonTunai += $p->jumlah,
                };
            }
        }

        return [
            'count'     => $invoices->count(),
            'tunai'     => $totalTunai,
            'non_tunai' => $totalNonTunai,
            'asuransi'  => $totalAsuransi,
            'grand'     => $totalTunai + $totalNonTunai + $totalAsuransi,
        ];
    }

    public function render()
    {
        return view('livewire.kasir.riwayat-pembayaran');
    }
}
