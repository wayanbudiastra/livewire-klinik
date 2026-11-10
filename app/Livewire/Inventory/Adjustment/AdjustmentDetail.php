<?php

namespace App\Livewire\Inventory\Adjustment;

use App\Models\AdjustmentStok;
use App\Services\Inventory\AdjustmentStokService;
use Livewire\Component;

class AdjustmentDetail extends Component
{
    public AdjustmentStok $adjustment;

    public string $alasanBatal = '';

    public function mount(AdjustmentStok $adjustment): void
    {
        $this->adjustment = $adjustment->load(['barang', 'user', 'mutasiStok']);
    }

    /**
     * Batalkan adjustment: revert stok + catat mutasi pembalik di kartu stok,
     * lalu soft-delete record adjustment (jejak audit tetap ada).
     */
    public function batalkan(AdjustmentStokService $service): void
    {
        abort_unless(auth()->user()?->can('obat.delete'), 403);

        $this->validate(
            ['alasanBatal' => 'required|string|min:10|max:500'],
            [
                'alasanBatal.required' => 'Alasan pembatalan wajib diisi.',
                'alasanBatal.min' => 'Alasan pembatalan minimal 10 karakter.',
            ]
        );

        try {
            $service->delete($this->adjustment, auth()->id(), trim($this->alasanBatal));
        } catch (\DomainException $e) {
            $this->addError('alasanBatal', $e->getMessage());
            return;
        }

        session()->flash('success', "Adjustment {$this->adjustment->nomor_adjustment} berhasil dibatalkan dan stok sudah dikembalikan.");
        $this->redirectRoute('inventory.adjustment.index');
    }

    public function render()
    {
        return view('livewire.inventory.adjustment.adjustment-detail');
    }
}
