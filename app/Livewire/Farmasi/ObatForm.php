<?php

namespace App\Livewire\Farmasi;

use App\Models\Barang;
use App\Services\FarmasiService;
use Illuminate\Validation\Rule;
use Livewire\Component;

class ObatForm extends Component
{
    public bool   $showModal  = false;
    public ?int   $obatId     = null;
    public bool   $isEdit     = false;

    // Form properties (nama internal sama agar blade tidak perlu banyak ubah)
    public string $kode            = '';
    public string $barcode         = '';
    public string $nama            = '';
    public string $generik         = '';    // → nama_generik
    public string $jenis_barang    = 'obat'; // → jenis
    public bool   $is_paten        = false;
    public string $satuan          = '';
    public string $satuan_besar    = '';    // string (dulu satuan_besar_id FK)
    public int    $konversi        = 1;     // → isi_satuan_besar
    public string $stok            = '0';
    public string $harga           = '';    // → harga_jual
    public string $harga_beli      = '';    // → harga_pokok
    public string $harga_bpjs      = '';
    public string $kategori        = '';
    public string $expired_date    = '';
    public bool   $is_active       = true;

    public function getRules(): array
    {
        $uniqueKode = $this->isEdit
            ? Rule::unique('barang', 'kode')->ignore($this->obatId)
            : 'unique:barang,kode';

        return [
            'kode'        => ['required', 'string', $uniqueKode],
            'barcode'     => ['nullable', 'string'],
            'nama'        => ['required', 'string', 'min:3'],
            'generik'     => ['nullable', 'string'],
            'jenis_barang'=> ['required', 'in:obat,alkes'],
            'satuan'      => ['required', 'string'],
            'satuan_besar'=> ['nullable', 'string'],
            'konversi'    => ['required', 'integer', 'min:1'],
            'stok'        => ['required', 'integer', 'min:0'],
            'harga'       => ['required', 'numeric', 'min:0'],
            'harga_beli'  => ['nullable', 'numeric', 'min:0'],
            'harga_bpjs'  => ['nullable', 'numeric', 'min:0'],
            'kategori'    => ['nullable', 'string'],
            'expired_date'=> ['nullable', 'date'],
        ];
    }

    public function getMessages(): array
    {
        return [
            'kode.unique' => 'Kode obat sudah digunakan.',
            'nama.min'    => 'Nama minimal 3 karakter.',
        ];
    }

    public function openCreate(): void
    {
        $this->authorize('obat.create');
        $this->reset(['obatId', 'kode', 'barcode', 'nama', 'generik',
                      'harga', 'harga_beli', 'harga_bpjs', 'kategori',
                      'expired_date', 'satuan_besar']);
        $this->jenis_barang = 'obat';
        $this->is_paten     = false;
        $this->konversi     = 1;
        $this->stok         = '0';
        $this->is_active    = true;
        $this->isEdit       = false;
        $this->showModal    = true;
        $this->resetValidation();
    }

    public function openEdit(int $id): void
    {
        $this->authorize('obat.edit');
        $b = Barang::findOrFail($id);
        $this->obatId       = $id;
        $this->kode         = $b->kode;
        $this->barcode      = $b->barcode      ?? '';
        $this->nama         = $b->nama;
        $this->generik      = $b->nama_generik ?? '';
        $this->jenis_barang = $b->jenis        ?? 'obat';
        $this->is_paten     = (bool) $b->is_paten;
        $this->satuan       = $b->satuan;
        $this->satuan_besar = $b->satuan_besar ?? '';
        $this->konversi     = $b->isi_satuan_besar ?? 1;
        $this->stok         = (string) $b->stok;
        $this->harga        = (string) $b->harga_jual;
        $this->harga_beli   = $b->harga_pokok  ? (string) $b->harga_pokok  : '';
        $this->harga_bpjs   = $b->harga_bpjs   ? (string) $b->harga_bpjs   : '';
        $this->kategori     = $b->kategori     ?? '';
        $this->expired_date = $b->expired_date ? $b->expired_date->format('Y-m-d') : '';
        $this->is_active    = (bool) $b->is_active;
        $this->isEdit       = true;
        $this->showModal    = true;
        $this->resetValidation();
    }

    public function save(FarmasiService $service): void
    {
        $this->validate($this->getRules(), $this->getMessages());

        $data = [
            'kode'             => strtoupper($this->kode),
            'barcode'          => $this->barcode      ?: null,
            'nama'             => $this->nama,
            'nama_generik'     => $this->generik      ?: null,
            'jenis'            => $this->jenis_barang,
            'is_paten'         => $this->is_paten,
            'satuan'           => $this->satuan,
            'satuan_besar'     => $this->satuan_besar ?: null,
            'isi_satuan_besar' => $this->konversi > 1 ? $this->konversi : null,
            'stok'             => (int) $this->stok,
            'harga_jual'       => (float) $this->harga,
            'harga_pokok'      => $this->harga_beli  ? (float) $this->harga_beli  : 0,
            'harga_bpjs'       => $this->harga_bpjs  ? (float) $this->harga_bpjs  : null,
            'kategori'         => $this->kategori    ?: null,
            'expired_date'     => $this->expired_date ?: null,
            'is_active'        => $this->is_active,
            'butuh_resep'      => ($this->jenis_barang === 'obat'),
        ];

        $this->isEdit
            ? $service->updateObat($this->obatId, $data)
            : $service->createObat($data);

        $this->showModal = false;
        $this->dispatch('obat-saved');
        $msg = $this->isEdit ? 'Data obat berhasil diupdate.' : 'Obat/Alkes baru ditambahkan.';
        $this->dispatch('notify', type: 'success', message: $msg);
    }

    public function render()
    {
        return view('livewire.farmasi.obat-form');
    }
}
