<?php

namespace App\Livewire\Pasien;

use App\Services\PasienService;
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

    #[On('pasien-saved')]
    public function refresh(): void { unset($this->pasien); }

    public function render()
    {
        return view('livewire.pasien.pasien-table');
    }
}
