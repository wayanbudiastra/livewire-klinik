<?php

namespace App\Livewire\Kunjungan;

use App\Models\Kunjungan;
use App\Services\KunjunganService;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class ListPendaftaran extends Component
{
    use WithPagination;

    /** Maksimal rentang tanggal yang boleh dipilih sekaligus (hari) -- sama polanya dgn WaitingArea. */
    private const MAKS_RENTANG_HARI = 30;

    #[Url]
    public string $tanggalMulai = '';

    #[Url]
    public string $tanggalAkhir = '';

    #[Url(as: 'q')]
    public string $search  = '';

    #[Url]
    public string $filterStatus = '';

    public function mount(): void
    {
        if (! $this->tanggalMulai) $this->tanggalMulai = now()->toDateString();
        if (! $this->tanggalAkhir) $this->tanggalAkhir = now()->toDateString();
        $this->clampRentang();
    }

    /**
     * Jaga rentang tetap valid & tidak lebih dari MAKS_RENTANG_HARI -- sama
     * pola & alasannya dgn WaitingArea::clampRentang() ("Sampai" mengikuti
     * "Dari", bukan sebaliknya, supaya user yang baru pilih tanggal mulai
     * tidak kaget tanggal itu ikut berubah sendiri).
     */
    private function clampRentang(): void
    {
        $mulai = \Carbon\Carbon::parse($this->tanggalMulai);
        $akhir = \Carbon\Carbon::parse($this->tanggalAkhir);

        if ($akhir->lt($mulai)) {
            $akhir = $mulai->copy();
        }

        if ($mulai->diffInDays($akhir) > self::MAKS_RENTANG_HARI - 1) {
            $akhir = $mulai->copy()->addDays(self::MAKS_RENTANG_HARI - 1);
        }

        $this->tanggalAkhir = $akhir->toDateString();
    }

    public function updatedTanggalMulai(): void { $this->clampRentang(); $this->resetPage(); }
    public function updatedTanggalAkhir(): void  { $this->clampRentang(); $this->resetPage(); }
    public function updatingSearch(): void      { $this->resetPage(); }
    public function updatingFilterStatus(): void{ $this->resetPage(); }

    #[Computed]
    public function kunjungan()
    {
        return Kunjungan::with([
            'pasien:id,nama,nomor_rm,tipe_pasien',
            'dokter.user:id,nama',
            'poli:id,nama',
            'appointment:id,kode_booking',
            // Tanpa batasan kolom -- hasOne(...)->latestOfMany() menghasilkan
            // self-join, dan daftar kolom eksplisit di sini bikin
            // "kunjungan_id" ambigu di query tersebut.
            'invoice',
        ])
        ->whereDate('tanggal', '>=', $this->tanggalMulai)
        ->whereDate('tanggal', '<=', $this->tanggalAkhir)
        ->when($this->search, fn ($q, $s) =>
            $q->whereHas('pasien', fn ($pq) =>
                $pq->where('nama', 'like', "%{$s}%")
                   ->orWhere('nomor_rm', 'like', "%{$s}%")))
        ->when($this->filterStatus, fn ($q, $st) => $q->where('status', $st))
        // Prioritas: A- (Appointment) dulu, baru W- (Walk-in), lalu urut nomor
        ->orderByRaw("CASE WHEN nomor_antrean LIKE 'A-%' THEN 0 ELSE 1 END")
        ->orderBy('nomor_antrean')
        ->paginate(15);
    }

    public function cancel(int $id, KunjunganService $service): void
    {
        $this->authorize('kunjungan.edit');

        try {
            $service->cancelKunjungan($id);
            unset($this->kunjungan);
            $this->dispatch('notify', type: 'success', message: 'Kunjungan berhasil dibatalkan.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            // cancelKunjungan() menolak (mis. sudah ada tagihan aktif) --
            // sebelumnya exception ini tidak ditangkap di sini sama sekali,
            // jadi pesannya tidak pernah terlihat oleh user.
            $this->dispatch('notify', type: 'error', message: $e->errors()[array_key_first($e->errors())][0]);
        }
    }

    #[On('kunjungan-created')]
    public function refresh(): void { unset($this->kunjungan); }

    public function render()
    {
        return view('livewire.kunjungan.list-pendaftaran');
    }
}
