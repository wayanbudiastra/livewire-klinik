<?php

namespace App\Livewire\Pengaturan\User;

use App\Models\ConfigSatuSehat;
use App\Models\Perawat;
use App\Models\User;
use App\Services\SatuSehat\SatuSehatIhsService;
use App\Services\UserService;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class UserTable extends Component
{
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url]
    public string $filterRole = '';

    #[Url]
    public string $filterStatus = '';

    #[Url]
    public string $sortBy = 'created_at';

    #[Url]
    public string $sortDir = 'desc';

    public int $perPage = 10;

    public function updatingSearch(): void      { $this->resetPage(); }
    public function updatingFilterRole(): void  { $this->resetPage(); }
    public function updatingFilterStatus(): void { $this->resetPage(); }

    public function sort(string $column): void
    {
        if ($this->sortBy === $column) {
            $this->sortDir = $this->sortDir === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy  = $column;
            $this->sortDir = 'asc';
        }
        $this->resetPage();
    }

    // ── IHS bulk fetch state ─────────────────────────────────
    public bool $ihsRunning = false;
    public int  $ihsTotal   = 0;
    public int  $ihsDone    = 0;
    public int  $ihsOk      = 0;
    public int  $ihsGagal   = 0;
    public bool $ihsSelesai = false;

    #[Computed]
    public function users()
    {
        $isActive = null;
        if ($this->filterStatus === '1') $isActive = true;
        if ($this->filterStatus === '0') $isActive = false;

        return app(UserService::class)->paginate([
            'search'    => $this->search     ?: null,
            'role'      => $this->filterRole  ?: null,
            'is_active' => $isActive,
            'sort_by'   => $this->sortBy,
            'sort_dir'  => $this->sortDir,
        ], $this->perPage);
    }

    public function toggleActive(int $userId, bool $state): void
    {
        $this->authorize('update', User::findOrFail($userId));
        app(UserService::class)->toggleActive($userId, $state);

        $msg = $state ? 'User berhasil diaktifkan.' : 'User berhasil dinonaktifkan.';
        $this->dispatch('notify', type: 'success', message: $msg);
    }

    public function deleteUser(int $userId): void
    {
        $this->authorize('delete', User::findOrFail($userId));
        app(UserService::class)->delete($userId);

        $this->dispatch('notify', type: 'success', message: 'User berhasil dihapus.');
    }

    // ── IHS Methods ──────────────────────────────────────────

    public function fetchIhsSatu(int $userId): void
    {
        if (! ConfigSatuSehat::aktif()) {
            $this->dispatch('notify', type: 'error', message: 'Integrasi SatuSehat belum diaktifkan.');
            return;
        }

        $user = User::with('perawat')->findOrFail($userId);

        if (! $user->perawat) {
            $this->dispatch('notify', type: 'error', message: 'Profil perawat belum dibuat. Isi NIK terlebih dahulu.');
            return;
        }

        if (! $user->perawat->nik) {
            $this->dispatch('notify', type: 'error', message: 'NIK perawat belum diisi. Edit pengguna untuk mengisi NIK.');
            return;
        }

        try {
            $hasil = app(SatuSehatIhsService::class)->fetchPerawat($user->perawat);
            unset($this->users);

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

        $ids = Perawat::whereNotNull('nik')
            ->where(fn ($q) => $q->whereNull('ihs_status')->orWhere('ihs_status', 'error'))
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
            $this->dispatch('notify', type: 'success', message: 'Semua perawat sudah memiliki IHS ID.');
            return;
        }

        foreach ($ids as $id) {
            $perawat = Perawat::find($id);
            if (! $perawat) continue;

            try {
                $hasil = $service->fetchPerawat($perawat);
                $hasil['status'] === 'ditemukan' ? $this->ihsOk++ : $this->ihsGagal++;
            } catch (\RuntimeException $e) {
                $this->ihsGagal++;
                $perawat->update([
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
        unset($this->users);

        $this->dispatch('notify', type: 'success',
            message: "Selesai: {$this->ihsOk} berhasil, {$this->ihsGagal} gagal dari {$this->ihsTotal} perawat.");
    }

    public function resetIhsBulk(): void
    {
        $this->ihsRunning = false;
        $this->ihsSelesai = false;
        $this->ihsTotal   = $this->ihsDone = $this->ihsOk = $this->ihsGagal = 0;
    }

    #[On('user-saved')]
    #[On('user-deleted')]
    #[On('password-reset')]
    public function refresh(): void
    {
        unset($this->users);
    }

    public function render()
    {
        return view('livewire.pengaturan.user.user-table', [
            'satusehatAktif' => ConfigSatuSehat::aktif(),
        ]);
    }
}
