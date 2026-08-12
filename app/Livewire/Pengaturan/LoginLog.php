<?php

namespace App\Livewire\Pengaturan;

use Carbon\Carbon;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Spatie\Activitylog\Models\Activity;

class LoginLog extends Component
{
    use WithPagination;

    /** Rentang filter maksimal (hari) — dibatasi sesuai kebutuhan. */
    private const MAKS_RENTANG_HARI = 30;

    #[Url]
    public string $tanggalMulai = '';

    #[Url]
    public string $tanggalSelesai = '';

    public function mount(): void
    {
        // Halaman ini hanya untuk super_admin (route juga sudah di-gate,
        // ini lapis kedua kalau komponen dipakai/di-embed di tempat lain).
        abort_unless(auth()->user()?->hasRole('super_admin'), 403);

        $this->tanggalSelesai = now()->format('Y-m-d');
        $this->tanggalMulai   = now()->subDays(6)->format('Y-m-d'); // default: 7 hari terakhir
    }

    public function updatingTanggalMulai(): void   { $this->resetPage(); }
    public function updatingTanggalSelesai(): void { $this->resetPage(); }

    public function updatedTanggalMulai(): void    { $this->normalisasiRentang(); }
    public function updatedTanggalSelesai(): void  { $this->normalisasiRentang(); }

    /**
     * Pastikan rentang tanggal valid & tidak lebih dari MAKS_RENTANG_HARI.
     * Kalau user pilih rentang lebih lebar, tanggalMulai otomatis
     * dimundurkan supaya rentang pas di batas maksimal.
     */
    private function normalisasiRentang(): void
    {
        if (! $this->tanggalMulai || ! $this->tanggalSelesai) return;

        $mulai   = Carbon::parse($this->tanggalMulai)->startOfDay();
        $selesai = Carbon::parse($this->tanggalSelesai)->startOfDay();

        if ($selesai->lt($mulai)) {
            $this->tanggalSelesai = $this->tanggalMulai;
            return;
        }

        if ($mulai->diffInDays($selesai) > self::MAKS_RENTANG_HARI) {
            $this->tanggalMulai = $selesai->copy()->subDays(self::MAKS_RENTANG_HARI)->format('Y-m-d');
            $this->dispatch('notify', type: 'error',
                message: 'Rentang tanggal maksimal '.self::MAKS_RENTANG_HARI.' hari.');
        }
    }

    #[Computed]
    public function logs()
    {
        return Activity::query()
            ->where('log_name', 'login')
            ->with('causer:id,nama,email')
            ->whereBetween('created_at', [
                Carbon::parse($this->tanggalMulai)->startOfDay(),
                Carbon::parse($this->tanggalSelesai)->endOfDay(),
            ])
            ->orderByDesc('created_at')
            ->paginate(10);
    }

    public function render()
    {
        return view('livewire.pengaturan.login-log');
    }
}
