<?php

namespace App\Livewire\Pengaturan\Dokter;

use App\Models\ConfigSatuSehat;
use App\Models\Dokter;
use App\Models\User;
use App\Services\SatuSehat\SatuSehatIhsService;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class DokterTable extends Component
{
    use WithPagination;

    #[Url(as: 'q')]
    public string $search      = '';

    #[Url]
    public string $filterSip   = '';

    public function updatingSearch(): void   { $this->resetPage(); }
    public function updatingFilterSip(): void { $this->resetPage(); }

    // ── IHS bulk fetch state ─────────────────────────────────
    public bool $ihsRunning = false;
    public int  $ihsTotal   = 0;
    public int  $ihsDone    = 0;
    public int  $ihsOk      = 0;
    public int  $ihsGagal   = 0;
    public bool $ihsSelesai = false;

    #[Computed]
    public function dokter()
    {
        return User::role('dokter')
            ->with(['dokter.poli:id,nama,kode'])
            ->where('is_active', true)
            ->when($this->search, fn ($q, $s) =>
                $q->where('nama', 'like', "%{$s}%")
                  ->orWhere('email', 'like', "%{$s}%"))
            ->when($this->filterSip === 'expired',
                fn ($q) => $q->whereHas('dokter', fn ($dq) =>
                    $dq->whereNotNull('tgl_expired_sip')
                       ->where('tgl_expired_sip', '<', now())))
            ->when($this->filterSip === 'segera_expired',
                fn ($q) => $q->whereHas('dokter', fn ($dq) =>
                    $dq->whereNotNull('tgl_expired_sip')
                       ->whereBetween('tgl_expired_sip', [now(), now()->addDays(30)])))
            ->when($this->filterSip === 'aktif',
                fn ($q) => $q->whereHas('dokter', fn ($dq) =>
                    $dq->where('tgl_expired_sip', '>=', now())))
            ->when($this->filterSip === 'belum_setup',
                fn ($q) => $q->whereDoesntHave('dokter'))
            ->orderBy('nama')
            ->paginate(10);
    }

    public function setupProfil(int $userId): void
    {
        $this->authorize('masterdata.edit');

        Dokter::firstOrCreate(
            ['user_id' => $userId],
            ['poli_id' => null]
        );

        unset($this->dokter);
        $this->dispatch('notify', type: 'success',
            message: 'Profil dokter dibuat. Silakan lengkapi data di halaman detail.');
    }

    // ── IHS Methods ──────────────────────────────────────────

    public function fetchIhsSatu(int $dokterId): void
    {
        if (! ConfigSatuSehat::aktif()) {
            $this->dispatch('notify', type: 'error', message: 'Integrasi SatuSehat belum diaktifkan.');
            return;
        }

        $dokter = Dokter::findOrFail($dokterId);

        try {
            $hasil = app(SatuSehatIhsService::class)->fetchDokter($dokter);
            unset($this->dokter);

            if ($hasil['status'] === 'ditemukan') {
                $this->dispatch('notify', type: 'success', message: "IHS ditemukan: {$hasil['ihs_id']}");
            } else {
                $this->dispatch('notify', type: 'error', message: $hasil['pesan']);
            }
        } catch (\RuntimeException $e) {
            $this->dispatch('notify', type: 'error', message: $e->getMessage());
        }
    }

    public function fetchIhsSemua(): void
    {
        if (! ConfigSatuSehat::aktif()) {
            $this->dispatch('notify', type: 'error', message: 'Integrasi SatuSehat belum diaktifkan.');
            return;
        }

        set_time_limit(0);

        $service = app(SatuSehatIhsService::class);

        $ids = Dokter::whereNull('ihs_status')
            ->orWhere('ihs_status', 'error')
            ->pluck('id');

        $this->ihsRunning = true;
        $this->ihsSelesai = false;
        $this->ihsTotal   = $ids->count();
        $this->ihsDone    = 0;
        $this->ihsOk      = 0;
        $this->ihsGagal   = 0;

        if ($this->ihsTotal === 0) {
            $this->ihsRunning = false;
            $this->ihsSelesai = true;
            $this->dispatch('notify', type: 'success', message: 'Semua dokter sudah memiliki IHS ID.');
            return;
        }

        foreach ($ids as $id) {
            $dokter = Dokter::find($id);
            if (! $dokter) continue;

            try {
                $hasil = $service->fetchDokter($dokter);
                $hasil['status'] === 'ditemukan' ? $this->ihsOk++ : $this->ihsGagal++;
            } catch (\RuntimeException $e) {
                $this->ihsGagal++;
                $dokter->update([
                    'ihs_status'    => 'error',
                    'ihs_synced_at' => now(),
                    'ihs_error_msg' => $e->getMessage(),
                ]);
            }

            $this->ihsDone++;
            usleep(200_000);
        }

        $this->ihsRunning = false;
        $this->ihsSelesai = true;
        unset($this->dokter);

        $this->dispatch('notify', type: 'success',
            message: "Selesai: {$this->ihsOk} berhasil, {$this->ihsGagal} gagal dari {$this->ihsTotal} dokter.");
    }

    public function resetIhsBulk(): void
    {
        $this->ihsRunning = false;
        $this->ihsSelesai = false;
        $this->ihsTotal   = $this->ihsDone = $this->ihsOk = $this->ihsGagal = 0;
    }

    #[On('dokter-saved')]
    public function refresh(): void { unset($this->dokter); }

    public function render()
    {
        return view('livewire.pengaturan.dokter.dokter-table', [
            'satusehatAktif' => ConfigSatuSehat::aktif(),
        ]);
    }
}
