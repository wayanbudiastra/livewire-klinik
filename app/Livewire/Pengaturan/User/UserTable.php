<?php

namespace App\Livewire\Pengaturan\User;

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

    public function updatingSearch(): void    { $this->resetPage(); }
    public function updatingFilterRole(): void { $this->resetPage(); }
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

    #[Computed]
    public function users()
    {
        $isActive = null;
        if ($this->filterStatus === '1') $isActive = true;
        if ($this->filterStatus === '0') $isActive = false;

        return app(UserService::class)->paginate([
            'search'    => $this->search    ?: null,
            'role'      => $this->filterRole ?: null,
            'is_active' => $isActive,
            'sort_by'   => $this->sortBy,
            'sort_dir'  => $this->sortDir,
        ], $this->perPage);
    }

    public function toggleActive(int $userId, bool $state): void
    {
        $this->authorize('update', \App\Models\User::findOrFail($userId));
        app(UserService::class)->toggleActive($userId, $state);

        $msg = $state ? 'User berhasil diaktifkan.' : 'User berhasil dinonaktifkan.';
        $this->dispatch('notify', type: 'success', message: $msg);
    }

    public function deleteUser(int $userId): void
    {
        $this->authorize('delete', \App\Models\User::findOrFail($userId));
        app(UserService::class)->delete($userId);

        $this->dispatch('notify', type: 'success', message: 'User berhasil dihapus.');
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
        return view('livewire.pengaturan.user.user-table');
    }
}
