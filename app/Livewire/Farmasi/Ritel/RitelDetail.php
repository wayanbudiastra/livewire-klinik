<?php

namespace App\Livewire\Farmasi\Ritel;

use App\Models\TransaksiRitel;
use App\Services\Farmasi\ObatRitelService;
use Livewire\Component;

class RitelDetail extends Component
{
    public TransaksiRitel $transaksi;

    // Payment form fields
    public string $metodeBayar  = 'tunai';
    public string $totalBayarInput = '';
    public bool   $showPayForm  = false;

    public function mount(TransaksiRitel $transaksi): void
    {
        $this->transaksi = $transaksi->load(['items.barang', 'apoteker', 'kasir', 'pasien']);
    }

    public function batalkan(ObatRitelService $service): void
    {
        try {
            $service->batalkan($this->transaksi);
            $this->transaksi->refresh();
            session()->flash('success', 'Transaksi berhasil dibatalkan.');
        } catch (\DomainException $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function prosesBayar(ObatRitelService $service): void
    {
        $this->validate([
            'metodeBayar'     => 'required|in:tunai,transfer,kartu,split',
            'totalBayarInput' => 'required|numeric|min:0',
        ], [
            'totalBayarInput.required' => 'Nominal bayar wajib diisi.',
            'totalBayarInput.min'      => 'Nominal bayar tidak valid.',
        ]);

        try {
            $service->prosesBayar($this->transaksi, [
                'metode_bayar' => $this->metodeBayar,
                'total_bayar'  => (float) $this->totalBayarInput,
            ], auth()->id());

            $this->transaksi->refresh();
            $this->transaksi->load(['items.barang', 'apoteker', 'kasir', 'pasien']);
            $this->showPayForm = false;
            session()->flash('success', 'Pembayaran berhasil diproses.');
        } catch (\DomainException $e) {
            $this->addError('totalBayarInput', $e->getMessage());
        }
    }

    public function serahkanObat(ObatRitelService $service): void
    {
        try {
            $service->serahkanObat($this->transaksi, auth()->id());
            $this->transaksi->refresh();
            $this->transaksi->load(['items.barang', 'apoteker', 'kasir', 'pasien']);
            session()->flash('success', 'Obat berhasil diserahkan. Stok telah terpotong.');
        } catch (\DomainException $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function render()
    {
        return view('livewire.farmasi.ritel.ritel-detail');
    }
}
