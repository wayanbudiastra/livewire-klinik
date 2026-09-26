<?php

namespace App\Livewire\Pasien;

use App\Models\ConfigSatuSehat;
use App\Models\Pasien;
use App\Services\PasienService;
use App\Services\SatuSehat\SatuSehatIhsService;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class PasienTable extends Component
{
    use WithPagination;

    #[Url(as: 'q')]
    public string $search      = '';

    #[Url]
    public string $filterTipe  = '';

    #[Url]
    public string $sortBy      = 'created_at';

    #[Url]
    public string $sortDir     = 'desc';

    public int $perPage = 10;

    // ── IHS bulk fetch state ─────────────────────────────────
    public bool   $ihsRunning  = false;
    public int    $ihsTotal    = 0;
    public int    $ihsDone     = 0;
    public int    $ihsOk       = 0;
    public int    $ihsGagal    = 0;
    public bool   $ihsSelesai  = false;

    public function updatingSearch(): void    { $this->resetPage(); }
    public function updatingFilterTipe(): void { $this->resetPage(); }

    public function sort(string $col): void
    {
        $this->sortDir = ($this->sortBy === $col && $this->sortDir === 'asc') ? 'desc' : 'asc';
        $this->sortBy  = $col;
        $this->resetPage();
    }

    #[Computed]
    public function pasien()
    {
        return app(PasienService::class)->paginate([
            'search'      => $this->search     ?: null,
            'tipe_pasien' => $this->filterTipe ?: null,
            'sort_by'     => $this->sortBy,
            'sort_dir'    => $this->sortDir,
        ], $this->perPage);
    }

    public function toggleActive(int $id, bool $state): void
    {
        $this->authorize('pasien.edit');
        app(PasienService::class)->toggleActive($id, $state);
        unset($this->pasien);
        $msg = $state ? 'Pasien berhasil diaktifkan.' : 'Pasien berhasil dinonaktifkan.';
        $this->dispatch('notify', type: 'success', message: $msg);
    }

    // ── IHS Methods ──────────────────────────────────────────

    public function fetchIhsSatu(int $pasienId): void
    {
        if (! ConfigSatuSehat::aktif()) {
            $this->dispatch('notify', type: 'error', message: 'Integrasi SatuSehat belum diaktifkan.');
            return;
        }

        $pasien = Pasien::findOrFail($pasienId);

        try {
            $ihs = app(SatuSehatIhsService::class)->fetchPasien($pasien);
            unset($this->pasien);

            if ($ihs['status'] === 'ditemukan') {
                $this->dispatch('notify', type: 'success', message: "IHS ditemukan: {$ihs['ihs_id']}");
            } else {
                $this->dispatch('notify', type: 'error', message: $ihs['pesan']);
            }
        } catch (\RuntimeException $e) {
            $this->dispatch('notify', type: 'error', message: $e->getMessage());
        }
    }

    /**
     * Batas jumlah pasien diproses per klik "Fetch Semua" -- sebelumnya
     * TIDAK ADA batas sama sekali, jadi klinik dengan banyak pasien belum
     * ber-IHS bisa gampang melewati timeout PHP-FPM/Nginx (set_time_limit(0)
     * cuma matikan timeout PHP sendiri, tidak menyentuh timeout web
     * server/reverse proxy di depannya) karena ini jalan sinkron dalam satu
     * request HTTP, bukan lewat queue job. Dibatasi per-batch + tombol bisa
     * diklik lagi utk lanjutkan sisanya, supaya request selalu selesai
     * dalam waktu wajar walau jumlah pasiennya ribuan.
     */
    private const IHS_BATCH_SIZE = 20;

    public function fetchIhsSemua(): void
    {
        if (! ConfigSatuSehat::aktif()) {
            $this->dispatch('notify', type: 'error', message: 'Integrasi SatuSehat belum diaktifkan.');
            return;
        }

        set_time_limit(0);

        $service = app(SatuSehatIhsService::class);

        $query = Pasien::whereNull('ihs_status')->orWhere('ihs_status', 'error');
        $sisaSebelum = (clone $query)->count();
        $ids = (clone $query)->orderBy('id')->limit(self::IHS_BATCH_SIZE)->pluck('id');

        $this->ihsRunning = true;
        $this->ihsSelesai = false;
        $this->ihsTotal   = $ids->count();
        $this->ihsDone    = 0;
        $this->ihsOk      = 0;
        $this->ihsGagal   = 0;

        if ($sisaSebelum === 0) {
            $this->ihsRunning = false;
            $this->ihsSelesai = true;
            $this->dispatch('notify', type: 'success', message: 'Semua pasien sudah memiliki IHS ID.');
            return;
        }

        foreach ($ids as $id) {
            $pasien = Pasien::find($id);
            if (! $pasien) continue;

            try {
                $hasil = $service->fetchPasien($pasien);
                if ($hasil['status'] === 'ditemukan') {
                    $this->ihsOk++;
                } else {
                    $this->ihsGagal++;
                }
            } catch (\RuntimeException $e) {
                $this->ihsGagal++;
                $pasien->update([
                    'ihs_status'    => 'error',
                    'ihs_synced_at' => now(),
                    'ihs_error_msg' => $e->getMessage(),
                ]);
            }

            $this->ihsDone++;
            usleep(200_000); // 200ms rate-limit protection
        }

        $this->ihsRunning = false;
        $this->ihsSelesai = true;
        unset($this->pasien);

        $sisaSesudah = $sisaSebelum - $this->ihsTotal;
        $ringkasan   = "Batch ini: {$this->ihsOk} berhasil, {$this->ihsGagal} gagal dari {$this->ihsTotal} pasien.";

        if ($sisaSesudah > 0) {
            $ringkasan .= " Masih ada {$sisaSesudah} pasien lagi -- klik \"Fetch Semua\" sekali lagi untuk lanjutkan.";
        }

        $this->dispatch('notify', type: 'success', message: $ringkasan);
    }

    public function resetIhsBulk(): void
    {
        $this->ihsRunning = false;
        $this->ihsSelesai = false;
        $this->ihsTotal   = 0;
        $this->ihsDone    = 0;
        $this->ihsOk      = 0;
        $this->ihsGagal   = 0;
    }

    #[On('pasien-saved')]
    public function refresh(): void { unset($this->pasien); }

    public function render()
    {
        return view('livewire.pasien.pasien-table', [
            'satusehatAktif' => ConfigSatuSehat::aktif(),
        ]);
    }
}
