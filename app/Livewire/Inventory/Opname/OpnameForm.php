<?php

namespace App\Livewire\Inventory\Opname;

use App\Services\Inventory\StokOpnameService;
use Livewire\Component;

class OpnameForm extends Component
{
    public string $tanggalOpname      = '';
    public string $keteranganPeriode  = '';
    public string $filterJenis        = '';
    public string $catatan            = '';

    public function mount(): void
    {
        $this->tanggalOpname = now()->format('Y-m-d');
    }

    public function buat(StokOpnameService $service): void
    {
        $this->validate([
            'tanggalOpname' => 'required|date',
        ]);

        try {
            $opname = $service->buatOpname([
                'tanggal_opname'     => $this->tanggalOpname,
                'keterangan_periode' => $this->keteranganPeriode ?: null,
                'catatan'            => $this->catatan ?: null,
            ], auth()->id(), $this->filterJenis ?: null);

            session()->flash('success', "Opname {$opname->nomor_opname} berhasil dibuat dengan {$opname->items->count()} item.");
            $this->redirect(route('inventory.opname.show', $opname->id));
        } catch (\Exception $e) {
            $this->addError('tanggalOpname', $e->getMessage());
        }
    }

    public function render()
    {
        return view('livewire.inventory.opname.opname-form');
    }
}
