<?php

namespace App\Livewire\Kasir\Billing;

use Livewire\Component;
use App\Models\Invoice;
use App\Services\Kasir\BillingService;

class BatalkanBillingModal extends Component
{
    public bool   $show       = false;
    public ?int   $billingId  = null;
    public string $alasan     = '';
    public string $password   = '';
    public bool   $processing = false;
    public string $errorMsg   = '';

    protected $listeners = ['openBatalkanModal' => 'open'];

    public function open(int $billingId): void
    {
        $this->billingId = $billingId;
        $this->alasan    = '';
        $this->password  = '';
        $this->errorMsg  = '';
        $this->show      = true;
    }

    public function batalkan(BillingService $service): void
    {
        $this->validate([
            'alasan'   => ['required', 'string', 'min:10', 'max:500'],
            'password' => ['required', 'string'],
        ]);

        $this->processing = true;
        $this->errorMsg   = '';

        try {
            $billing = Invoice::findOrFail($this->billingId);

            $service->batalkanBilling(
                billing:            $billing,
                passwordSuperAdmin: $this->password,
                alasan:             $this->alasan,
                requestUserId:      auth()->id(),
            );

            $this->show = false;
            $this->dispatch('billingDibatalkan');
            session()->flash('success', "Invoice {$billing->nomor_invoice} berhasil dibatalkan.");
            $this->redirectRoute('kasir.billing.index');
        } catch (\Exception $e) {
            $this->errorMsg   = $e->getMessage();
            $this->processing = false;
        }
    }

    public function render()
    {
        return view('livewire.kasir.billing.batalkan-billing-modal');
    }
}
