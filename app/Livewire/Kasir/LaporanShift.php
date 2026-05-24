<?php

namespace App\Livewire\Kasir;

use App\Models\Pembayaran;
use App\Models\ShiftKasir;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

class LaporanShift extends Component
{
    public ?int $shiftId = null;

    public function mount(): void
    {
        // Auto-select the most recently closed shift of this user
        $closed = ShiftKasir::where('user_id', Auth::id())
            ->where('status', 'closed')
            ->latest('closed_at')
            ->first();
        $this->shiftId = $closed?->id;
    }

    #[Computed]
    public function shift(): ?ShiftKasir
    {
        if (! $this->shiftId) return null;
        return ShiftKasir::find($this->shiftId);
    }

    #[Computed]
    public function rincianNonTunai(): array
    {
        if (! $this->shiftId) return [];

        return Pembayaran::where('shift_id', $this->shiftId)
            ->where('metode', 'non_tunai')
            ->selectRaw('bank_nama, SUM(jumlah) as total')
            ->groupBy('bank_nama')
            ->get()
            ->toArray();
    }

    #[Computed]
    public function rincianAsuransi(): array
    {
        if (! $this->shiftId) return [];

        return Pembayaran::where('shift_id', $this->shiftId)
            ->where('metode', 'asuransi')
            ->selectRaw('nama_asuransi, SUM(jumlah) as total')
            ->groupBy('nama_asuransi')
            ->get()
            ->toArray();
    }

    #[Computed]
    public function riwayatShift(): array
    {
        return ShiftKasir::where('user_id', Auth::id())
            ->latest('opened_at')
            ->limit(10)
            ->get()
            ->toArray();
    }

    public function selectShift(int $id): void
    {
        $this->shiftId = $id;
        unset($this->shift, $this->rincianNonTunai, $this->rincianAsuransi);
    }

    public function render()
    {
        return view('livewire.kasir.laporan-shift');
    }
}
