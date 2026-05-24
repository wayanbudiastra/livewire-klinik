<?php

namespace App\Livewire\Kasir\Billing;

use Livewire\Component;
use App\Models\Invoice;
use App\Services\Kasir\{CetakInvoiceService, SesiKasService};

class BillingDetail extends Component
{
    public Invoice $billing;

    public function mount(Invoice $billing): void
    {
        $this->billing = $billing->load([
            'kunjungan.pasien',
            'kunjungan.dokter',
            'kunjungan.poli',
            'items',
            'pembayaranSplit',
            'cetakLogs',
        ]);
    }

    public function cetakInvoice(CetakInvoiceService $cetakService, SesiKasService $sesiKasService)
    {
        $sesiKas = $sesiKasService->getSesiAktif(auth()->id());
        if (!$sesiKas) {
            session()->flash('error', 'Kas belum dibuka.');
            return null;
        }

        try {
            return $cetakService->cetak($this->billing, auth()->id());
        } catch (\Exception $e) {
            session()->flash('error', $e->getMessage());
            return null;
        }
    }

    public function batalkan(): void
    {
        $this->dispatch('openBatalkanModal', billingId: $this->billing->id);
    }

    public function render()
    {
        $this->billing->refresh()->load([
            'kunjungan.pasien',
            'kunjungan.dokter',
            'kunjungan.poli',
            'items',
            'pembayaranSplit',
            'cetakLogs',
        ]);

        return view('livewire.kasir.billing.billing-detail');
    }
}
