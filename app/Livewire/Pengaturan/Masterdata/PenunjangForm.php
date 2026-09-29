<?php

namespace App\Livewire\Pengaturan\Masterdata;

use App\Models\ItemPenunjang;
use App\Models\KonfigurasiHargaWna;
use App\Services\Harga\MarkupHargaService;
use App\Services\MasterdataService;
use Illuminate\Validation\Rule;
use Livewire\Component;

class PenunjangForm extends Component
{
    public bool   $showModal   = false;
    public ?int   $penunjangId = null;
    public bool   $isEdit      = false;
    public string $defaultKategori = 'lab';

    public string $kode         = '';
    public string $nama         = '';
    public string $kategori     = 'lab';
    public string $tarif        = '';
    public string $tarif_bpjs   = '';
    public string $tarif_wna    = '';
    public string $harga_modal  = '';
    public string $deskripsi    = '';
    public string $satuan_waktu = '';
    public bool   $is_active    = true;

    public function getRules(): array
    {
        $uniqueKode = $this->isEdit
            ? Rule::unique('item_penunjang', 'kode')->ignore($this->penunjangId)
            : 'unique:item_penunjang,kode';

        return [
            'kode'         => ['required', 'string', 'min:2', $uniqueKode],
            'nama'         => ['required', 'string', 'min:3'],
            'kategori'     => ['required', 'in:lab,radiologi'],
            'tarif'        => ['required', 'numeric', 'min:0'],
            'tarif_bpjs'   => ['nullable', 'numeric', 'min:0'],
            'tarif_wna'    => ['nullable', 'numeric', 'min:0'],
            'harga_modal'  => ['nullable', 'numeric', 'min:0'],
            'deskripsi'    => ['nullable', 'string'],
            'satuan_waktu' => ['nullable', 'string'],
        ];
    }

    /** Lab ikut markup otomatis berbasis harga modal; Radiologi tetap manual. */
    public function getIkutMarkupOtomatisProperty(): bool
    {
        return $this->kategori === 'lab';
    }

    /** Markup WNA saat ini, dipakai tombol "Generate" di blade -- utk Radiologi (manual, bukan Lab). */
    public function getMarkupWnaPersenProperty(): float
    {
        return KonfigurasiHargaWna::markupPersen();
    }

    /** Isi tarif_wna dari tarif umum × markup (masih bisa diedit manual sebelum simpan) -- Radiologi saja. */
    public function generateTarifWna(): void
    {
        if ($this->tarif === '' || ! is_numeric($this->tarif)) return;

        $markup = KonfigurasiHargaWna::markupPersen();
        $this->tarif_wna = (string) round(((float) $this->tarif) * (1 + $markup / 100));
    }

    /**
     * Tarif & tarif_wna dihitung ULANG otomatis setiap kali harga modal
     * diketik ulang -- cuma kategori Lab, Radiologi tetap manual. Masih
     * bisa ditimpa manual sesudahnya, tapi kalau modal diubah lagi,
     * dihitung ulang lagi.
     */
    public function updatedHargaModal(MarkupHargaService $service): void
    {
        if (! $this->ikutMarkupOtomatis) return;
        if ($this->harga_modal === '' || ! is_numeric($this->harga_modal)) return;

        $hasil = $service->hitung('lab', (float) $this->harga_modal);
        if ($hasil['ktp'] === null) return;

        $this->tarif     = (string) $hasil['ktp'];
        $this->tarif_wna = (string) $hasil['wna'];
    }

    public function updatedKategori(MarkupHargaService $service): void
    {
        $this->updatedHargaModal($service);
    }

    public function openCreate(string $kategori = 'lab'): void
    {
        $this->authorize('masterdata.create');
        $this->reset(['penunjangId','kode','nama','tarif','tarif_bpjs','tarif_wna','harga_modal','deskripsi','satuan_waktu']);
        $this->kategori  = $kategori;
        $this->is_active = true;
        $this->isEdit    = false;
        $this->showModal = true;
        $this->resetValidation();
    }

    public function openEdit(int $id): void
    {
        $this->authorize('masterdata.edit');
        $item = ItemPenunjang::findOrFail($id);
        $this->penunjangId  = $id;
        $this->kode         = $item->kode;
        $this->nama         = $item->nama;
        $this->kategori     = $item->kategori;
        $this->tarif        = (string) $item->tarif;
        $this->tarif_bpjs   = $item->tarif_bpjs ? (string) $item->tarif_bpjs : '';
        $this->tarif_wna    = $item->tarif_wna  ? (string) $item->tarif_wna  : '';
        $this->harga_modal  = $item->harga_modal ? (string) $item->harga_modal : '';
        $this->deskripsi    = $item->deskripsi ?? '';
        $this->satuan_waktu = $item->satuan_waktu ?? '';
        $this->is_active    = $item->is_active;
        $this->isEdit       = true;
        $this->showModal    = true;
        $this->resetValidation();
    }

    public function save(MasterdataService $service): void
    {
        $this->validate($this->getRules());

        $data = [
            'kode'         => strtoupper($this->kode),
            'nama'         => $this->nama,
            'kategori'     => $this->kategori,
            'tarif'        => (float) $this->tarif,
            'tarif_bpjs'   => $this->tarif_bpjs ? (float) $this->tarif_bpjs : null,
            'tarif_wna'    => $this->tarif_wna  ? (float) $this->tarif_wna  : null,
            'harga_modal'  => $this->harga_modal !== '' ? (float) $this->harga_modal : null,
            'deskripsi'    => $this->deskripsi    ?: null,
            'satuan_waktu' => $this->satuan_waktu ?: null,
            'is_active'    => $this->is_active,
        ];

        $item = $this->isEdit
            ? $service->updatePenunjang($this->penunjangId, $data)
            : $service->createPenunjang($data);

        $this->showModal = false;
        $this->dispatch('penunjang-saved');

        // Validasi harga (peringatan saja, TETAP disimpan) -- kalau tarif
        // di bawah harga modal (khusus Lab, yg punya harga_modal), berpotensi rugi.
        if ($this->kategori === 'lab' && $this->harga_modal !== '' && (float) $this->tarif < (float) $this->harga_modal) {
            activity('masterdata')
                ->performedOn($item)
                ->causedBy(auth()->user())
                ->withProperties(['tarif' => $this->tarif, 'harga_modal' => $this->harga_modal])
                ->log("Tarif \"{$item->nama}\" disimpan DI BAWAH harga modal (berpotensi rugi)");

            $msg = $this->isEdit ? 'Item penunjang diupdate. ' : 'Item penunjang ditambahkan. ';
            $this->dispatch('notify', type: 'warning', message:
                $msg . "Perhatian: tarif (Rp " . number_format((float) $this->tarif, 0, ',', '.')
                . ") di bawah harga modal (Rp " . number_format((float) $this->harga_modal, 0, ',', '.') . ").");
            return;
        }

        $msg = $this->isEdit ? 'Item penunjang diupdate.' : 'Item penunjang ditambahkan.';
        $this->dispatch('notify', type: 'success', message: $msg);
    }

    public function render()
    {
        return view('livewire.pengaturan.masterdata.penunjang-form');
    }
}
