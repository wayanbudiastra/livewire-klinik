<?php

namespace App\Livewire\Kasir;

use App\Models\ShiftKasir;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class KelolaShift extends Component
{
    public ?ShiftKasir $shift = null;

    // Open shift form
    public string $modalAwal = '';

    // Close shift form
    public string $uangFisikAkhir = '';
    public string $catatanClose   = '';

    public bool $showOpenForm  = false;
    public bool $showCloseForm = false;

    public function mount(): void
    {
        $this->loadShift();
    }

    public function loadShift(): void
    {
        $this->shift = ShiftKasir::where('user_id', Auth::id())
            ->open()
            ->latest()
            ->first();
    }

    public function openShift(): void
    {
        $this->validate([
            'modalAwal' => 'required|numeric|min:0',
        ], [
            'modalAwal.required' => 'Modal awal wajib diisi.',
            'modalAwal.numeric'  => 'Modal awal harus berupa angka.',
        ]);

        ShiftKasir::create([
            'user_id'    => Auth::id(),
            'modal_awal' => $this->modalAwal,
            'opened_at'  => now(),
            'status'     => 'open',
        ]);

        $this->reset(['modalAwal', 'showOpenForm']);
        $this->loadShift();
        $this->dispatch('shift-opened');
        session()->flash('success', 'Shift berhasil dibuka.');
    }

    public function closeShift(): void
    {
        $this->validate([
            'uangFisikAkhir' => 'required|numeric|min:0',
        ], [
            'uangFisikAkhir.required' => 'Uang fisik akhir wajib diisi.',
            'uangFisikAkhir.numeric'  => 'Uang fisik akhir harus berupa angka.',
        ]);

        if (! $this->shift) return;

        $uangSistem = $this->shift->modal_awal + $this->shift->total_tunai;
        $selisih    = (float) $this->uangFisikAkhir - $uangSistem;

        $this->shift->update([
            'uang_fisik_akhir' => $this->uangFisikAkhir,
            'selisih'          => $selisih,
            'status'           => 'closed',
            'closed_at'        => now(),
            'catatan'          => $this->catatanClose ?: null,
        ]);

        $this->reset(['uangFisikAkhir', 'catatanClose', 'showCloseForm']);
        $this->dispatch('shift-closed', shiftId: $this->shift->id);
        $this->shift = null;
        session()->flash('success', 'Shift berhasil ditutup.');
    }

    public function render()
    {
        return view('livewire.kasir.kelola-shift');
    }
}
