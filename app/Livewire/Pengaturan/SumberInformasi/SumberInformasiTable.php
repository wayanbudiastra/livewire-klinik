<?php

namespace App\Livewire\Pengaturan\SumberInformasi;

use App\Models\SumberInformasi;
use App\Services\SumberInformasiService;
use Livewire\Component;

class SumberInformasiTable extends Component
{
    public bool   $showForm = false;
    public ?int   $editId   = null;

    public string $kode            = '';
    public string $nama            = '';
    public string $kategori        = 'lainnya';
    public string $icon            = '';
    public bool   $butuhKeterangan = false;
    public int    $urutan          = 0;
    public bool   $isActive        = true;

    protected function rules(): array
    {
        return [
            'nama'     => ['required', 'string', 'max:100'],
            'kategori' => ['required', 'in:digital,sosial_media,word_of_mouth,offline,lainnya'],
            'icon'     => ['nullable', 'string', 'max:10'],
            'urutan'   => ['integer', 'min:0'],
        ];
    }

    protected function messages(): array
    {
        return [
            'nama.required' => 'Nama sumber wajib diisi.',
            'nama.max'      => 'Nama maksimal 100 karakter.',
        ];
    }

    public function buatBaru(): void
    {
        $this->reset(['editId', 'kode', 'nama', 'icon', 'butuhKeterangan', 'urutan']);
        $this->kategori = 'lainnya';
        $this->isActive = true;
        $this->showForm = true;
        $this->resetValidation();
    }

    public function edit(int $id): void
    {
        $s = SumberInformasi::findOrFail($id);
        $this->editId          = $s->id;
        $this->kode            = $s->kode;
        $this->nama            = $s->nama;
        $this->kategori        = $s->kategori;
        $this->icon            = $s->icon ?? '';
        $this->butuhKeterangan = $s->butuh_keterangan;
        $this->urutan          = $s->urutan;
        $this->isActive        = $s->is_active;
        $this->showForm        = true;
        $this->resetValidation();
    }

    public function simpan(SumberInformasiService $service): void
    {
        $this->validate();

        $data = [
            'nama'             => $this->nama,
            'kategori'         => $this->kategori,
            'icon'             => $this->icon ?: null,
            'butuh_keterangan' => $this->butuhKeterangan,
            'urutan'           => $this->urutan,
            'is_active'        => $this->isActive,
        ];

        if ($this->editId) {
            $service->update(SumberInformasi::findOrFail($this->editId), $data);
            $this->dispatch('notify', type: 'success', message: 'Sumber informasi berhasil diperbarui.');
        } else {
            $data['kode'] = $this->kode ?: \Illuminate\Support\Str::slug($this->nama, '_');
            $service->create($data);
            $this->dispatch('notify', type: 'success', message: 'Sumber informasi berhasil ditambahkan.');
        }

        $this->showForm = false;
    }

    public function toggleActive(int $id, SumberInformasiService $service): void
    {
        try {
            $service->toggleActive(SumberInformasi::findOrFail($id));
            $this->dispatch('notify', type: 'success', message: 'Status berhasil diubah.');
        } catch (\Exception $e) {
            $this->dispatch('notify', type: 'error', message: $e->getMessage());
        }
    }

    public function render()
    {
        return view('livewire.pengaturan.sumber-informasi.sumber-informasi-table', [
            'daftar' => SumberInformasi::orderBy('urutan')->get(),
        ]);
    }
}
