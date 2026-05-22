<?php

namespace App\Livewire\Inventory\KartuStok;

use App\Models\Barang;
use App\Services\Inventory\KartuStokService;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

class KartuStokIndex extends Component
{
    #[Url(as: 'q')]
    public string $searchBarang = '';
    public ?int   $selectedBarangId = null;
    public string $selectedBarangNama = '';

    #[Url]
    public string $tanggalMulai = '';
    #[Url]
    public string $tanggalAkhir = '';
    #[Url]
    public string $tipeMutasi = '';

    public array $kartuData = [];
    public bool  $sudahCari = false;

    public function mount(): void
    {
        $this->tanggalMulai = now()->startOfMonth()->format('Y-m-d');
        $this->tanggalAkhir = now()->format('Y-m-d');
    }

    #[Computed]
    public function barangSuggestions()
    {
        if (strlen($this->searchBarang) < 2) return collect();
        return Barang::where('is_active', true)
            ->where(fn ($q) =>
                $q->where('nama', 'like', "%{$this->searchBarang}%")
                  ->orWhere('kode', 'like', "%{$this->searchBarang}%"))
            ->select('id', 'kode', 'nama', 'satuan', 'stok', 'stok_minimum', 'jenis')
            ->limit(10)
            ->get();
    }

    public function pilihBarang(int $id, string $nama): void
    {
        $this->selectedBarangId   = $id;
        $this->selectedBarangNama = $nama;
        $this->searchBarang       = $nama;
        $this->sudahCari          = false;
        $this->kartuData          = [];
    }

    public function lihatKartu(KartuStokService $service): void
    {
        $this->validate([
            'selectedBarangId' => 'required|integer|exists:barang,id',
            'tanggalMulai'     => 'required|date',
            'tanggalAkhir'     => 'required|date|after_or_equal:tanggalMulai',
        ], [
            'selectedBarangId.required'   => 'Pilih barang terlebih dahulu.',
            'tanggalAkhir.after_or_equal' => 'Tanggal akhir harus setelah tanggal mulai.',
        ]);

        $this->kartuData = $service->getKartuStok(
            $this->selectedBarangId,
            $this->tanggalMulai,
            $this->tanggalAkhir,
            $this->tipeMutasi ?: null
        );

        $this->sudahCari = true;
    }

    public function resetPilihan(): void
    {
        $this->selectedBarangId   = null;
        $this->selectedBarangNama = '';
        $this->searchBarang       = '';
        $this->kartuData          = [];
        $this->sudahCari          = false;
    }

    public function getTipeOptions(): array
    {
        return [
            ''                   => 'Semua Tipe',
            'masuk_pembelian'    => 'Pembelian (Masuk)',
            'keluar_resep'       => 'Resep (Keluar)',
            'keluar_tindakan'    => 'Tindakan (Keluar)',
            'penyesuaian_masuk'  => 'Opname Tambah',
            'penyesuaian_keluar' => 'Opname Kurang',
            'retur_ke_supplier'  => 'Retur Supplier',
            'expired'            => 'Disposal Expired',
        ];
    }

    public function render()
    {
        return view('livewire.inventory.kartu-stok.kartu-stok-index');
    }
}
