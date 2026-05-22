<?php

namespace App\Livewire\Farmasi;

use App\Models\Satuan;
use Livewire\Attributes\Computed;
use Livewire\Component;

class SatuanTable extends Component
{
    public string $nama      = '';
    public ?int   $editId    = null;
    public bool   $showForm  = false;

    #[Computed]
    public function satuan()
    {
        return Satuan::orderBy('nama')->get();
    }

    public function openCreate(): void
    {
        $this->editId   = null;
        $this->nama     = '';
        $this->showForm = true;
        $this->resetValidation();
    }

    public function openEdit(int $id): void
    {
        $s = Satuan::findOrFail($id);
        $this->editId   = $id;
        $this->nama     = $s->nama;
        $this->showForm = true;
        $this->resetValidation();
    }

    public function save(): void
    {
        $this->validate(['nama' => 'required|string|min:2|max:50']);

        if ($this->editId) {
            Satuan::findOrFail($this->editId)->update(['nama' => $this->nama]);
            $msg = 'Satuan berhasil diupdate.';
        } else {
            Satuan::create(['nama' => $this->nama, 'is_active' => true]);
            $msg = 'Satuan baru ditambahkan.';
        }

        $this->showForm = false;
        $this->nama     = '';
        unset($this->satuan);
        $this->dispatch('notify', type: 'success', message: $msg);
    }

    public function toggleAktif(int $id): void
    {
        $s = Satuan::findOrFail($id);
        $s->update(['is_active' => ! $s->is_active]);
        unset($this->satuan);
        $this->dispatch('notify', type: 'success', message: 'Status satuan diupdate.');
    }

    public function render()
    {
        return view('livewire.farmasi.satuan-table');
    }
}
