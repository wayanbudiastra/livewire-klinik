<?php

namespace App\Livewire\Pemeriksaan;

use App\Models\Kunjungan;
use App\Services\KunjunganService;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class WaitingArea extends Component
{
    use WithPagination;

    /** Maksimal rentang tanggal yang boleh dipilih sekaligus (hari). */
    private const MAKS_RENTANG_HARI = 31;

    #[Url]
    public string $tanggalMulai = '';

    #[Url]
    public string $tanggalAkhir = '';

    #[Url(as: 'q')]
    public string $search = '';

    #[Url]
    public string $filterStatus = 'aktif';

    public ?int $viewKunjunganId = null;

    public function mount(): void
    {
        if (! $this->tanggalMulai) $this->tanggalMulai = now()->toDateString();
        if (! $this->tanggalAkhir) $this->tanggalAkhir = now()->toDateString();
        $this->clampRentang();
    }

    /**
     * Jaga rentang tetap valid & tidak lebih dari MAKS_RENTANG_HARI --
     * dipanggil tiap salah satu tanggal berubah. "Sampai" yang disesuaikan
     * mengikuti "Dari" (bukan sebaliknya), supaya user yang baru pilih
     * tanggal mulai tidak kaget tanggal itu ikut berubah sendiri.
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
    public function updatingFilterStatus(): void { $this->resetPage(); }

    #[Computed]
    public function kunjungan()
    {
        return Kunjungan::with([
            'pasien:id,nama,nomor_rm,tipe_pasien,alergi,jenis_kelamin,tanggal_lahir',
            'dokter.user:id,nama',
            'poli:id,nama',
        ])
        ->whereDate('tanggal', '>=', $this->tanggalMulai)
        ->whereDate('tanggal', '<=', $this->tanggalAkhir)
        ->when($this->search, fn ($q, $s) =>
            $q->whereHas('pasien', fn ($pq) =>
                $pq->where('nama', 'like', "%{$s}%")
                   ->orWhere('nomor_rm', 'like', "%{$s}%")))
        ->when($this->filterStatus, fn ($q) => match ($this->filterStatus) {
            'aktif'  => $q->whereIn('status', ['menunggu', 'dalam_pemeriksaan']),
            default  => $q->where('status', $this->filterStatus),
        })
        ->orderByRaw("CASE status
            WHEN 'dalam_pemeriksaan' THEN 1
            WHEN 'menunggu'          THEN 2
            WHEN 'selesai'           THEN 3
            ELSE 4 END")
        ->orderBy('nomor_antrean')
        ->paginate(10);
    }

    #[Computed]
    public function viewKunjungan(): ?Kunjungan
    {
        if (! $this->viewKunjunganId) return null;

        return Kunjungan::with([
            'pasien',
            'dokter.user:id,nama',
            'poli:id,nama',
            'asesmenPerawat',
            'soapNote',
            'resep.itemResep.barang:id,nama,satuan',
            'resep.racikan.bahanRacikan.barang:id,nama,satuan',
            'tindakan.masterTindakan:id,nama,tarif',
        ])->find($this->viewKunjunganId);
    }

    public function openView(int $id): void
    {
        $this->viewKunjunganId = $id;
        unset($this->viewKunjungan);
    }

    public function closeView(): void
    {
        $this->viewKunjunganId = null;
        unset($this->viewKunjungan);
    }

    public function panggil(int $id, KunjunganService $service): void
    {
        $service->panggilPasien($id);
        unset($this->kunjungan);
        $this->dispatch('notify', type: 'success', message: 'Pasien dipanggil ke ruang pemeriksaan.');
    }

    public function selesai(int $id, KunjunganService $service): void
    {
        $service->selesaiPemeriksaan($id);
        unset($this->kunjungan);
        $this->dispatch('notify', type: 'success', message: 'Pemeriksaan selesai.');
    }

    public function render()
    {
        return view('livewire.pemeriksaan.waiting-area');
    }
}
