<?php

namespace App\Livewire\Farmasi;

use App\Models\LokasiGudang;
use Livewire\Attributes\Computed;
use Livewire\Component;

class GudangTable extends Component
{
    public string $kode      = '';
    public string $nama      = '';
    public ?int   $editId    = null;
    public bool   $showForm  = false;

    #[Computed]
    public function gudang()
    {
        return LokasiGudang::orderBy('nama')->get();
    }

    public function openCreate(): void
    {
        $this->editId   = null;
        $this->kode     = '';
        $this->nama     = '';
        $this->showForm = true;
        $this->resetValidation();
    }

    public function openEdit(int $id): void
    {
        $g = LokasiGudang::findOrFail($id);
        $this->editId   = $id;
        $this->kode     = $g->kode;
        $this->nama     = $g->nama;
        $this->showForm = true;
        $this->resetValidation();
    }

    public function save(): void
    {
        $rules = [
            'kode' => ['required', 'string', 'min:2'],
            'nama' => ['required', 'string', 'min:3'],
        ];

        if (! $this->editId) {
            $rules['kode'][] = 'unique:lokasi_gudang,kode';
        }

        $this->validate($rules);

        if ($this->editId) {
            LokasiGudang::findOrFail($this->editId)->update(['kode' => strtoupper($this->kode), 'nama' => $this->nama]);
            $msg = 'Lokasi gudang diupdate.';
        } else {
            LokasiGudang::create(['kode' => strtoupper($this->kode), 'nama' => $this->nama, 'is_active' => true]);
            $msg = 'Lokasi gudang ditambahkan.';
        }

        $this->showForm = false;
        unset($this->gudang);
        $this->dispatch('notify', type: 'success', message: $msg);
    }

    public function toggleAktif(int $id): void
    {
        $g = LokasiGudang::findOrFail($id);
        $g->update(['is_active' => ! $g->is_active]);
        unset($this->gudang);
        $this->dispatch('notify', type: 'success', message: 'Status gudang diupdate.');
    }

    public function render()
    {
        return view('livewire.farmasi.gudang-table');
    }
}
