<?php

namespace App\Livewire\Farmasi\Ritel;

use App\Models\{Barang, Pasien, TransaksiRitel};
use App\Services\Farmasi\ObatRitelService;
use Livewire\Attributes\Computed;
use Livewire\Component;

class RitelForm extends Component
{
    // ── Identitas pembeli ────────────────────────────────────────────────
    public string $namaPembeli    = '';
    public string $nomorHp        = '';
    public string $catatan        = '';

    // ── Pasien terkait (opsional) ────────────────────────────────────────
    public ?int   $pasienId       = null;
    public string $pasienNama     = '';
    public string $pasienRm       = '';
    public string $searchPasien   = '';
    public array  $hasilSearchPasien = [];

    // ── Item obat ────────────────────────────────────────────────────────
    public array  $items          = [];
    public string $searchObat     = '';
    public array  $hasilSearch    = [];

    public ?TransaksiRitel $transaksi = null;

    public function mount(?int $id = null): void
    {
        if ($id) {
            $this->transaksi = TransaksiRitel::with('items.barang', 'pasien')->findOrFail($id);

            if ($this->transaksi->status !== 'draft') {
                abort(403, 'Transaksi ini tidak lagi dalam status draft.');
            }

            $this->namaPembeli = $this->transaksi->nama_pembeli;
            $this->nomorHp     = $this->transaksi->nomor_hp ?? '';
            $this->catatan     = $this->transaksi->catatan ?? '';

            if ($this->transaksi->pasien) {
                $this->pasienId   = $this->transaksi->pasien_id;
                $this->pasienNama = $this->transaksi->pasien->nama;
                $this->pasienRm   = $this->transaksi->pasien->nomor_rm;
            }

            $this->items = $this->transaksi->items->map(fn ($item) => [
                'barang_id'    => $item->barang_id,
                'nama_barang'  => $item->barang->nama,
                'kode'         => $item->barang->kode,
                'satuan'       => $item->barang->satuan,
                'jumlah'       => (int) $item->jumlah,
                'harga_satuan' => (float) $item->harga_satuan,
                'subtotal'     => (float) $item->subtotal,
                'stok'         => $item->barang->stok,
                'butuh_resep'  => $item->barang->butuh_resep,
            ])->toArray();
        }
    }

    // ── Pencarian pasien ─────────────────────────────────────────────────

    public function updatedSearchPasien(): void
    {
        if (strlen($this->searchPasien) < 2) {
            $this->hasilSearchPasien = [];
            return;
        }

        $this->hasilSearchPasien = Pasien::where('is_active', true)
            ->where(fn ($q) => $q
                ->where('nama',      'like', "%{$this->searchPasien}%")
                ->orWhere('nomor_rm', 'like', "%{$this->searchPasien}%")
            )
            ->limit(8)
            ->get(['id', 'nomor_rm', 'nama', 'tanggal_lahir'])
            ->map(fn ($p) => [
                'id'       => $p->id,
                'nomor_rm' => $p->nomor_rm,
                'nama'     => $p->nama,
                'lahir'    => $p->tanggal_lahir?->format('d/m/Y') ?? '—',
            ])
            ->toArray();
    }

    public function selectPasien(int $id): void
    {
        $pasien = Pasien::find($id);
        if (!$pasien) return;

        $this->pasienId           = $pasien->id;
        $this->pasienNama         = $pasien->nama;
        $this->pasienRm           = $pasien->nomor_rm;
        $this->namaPembeli        = $pasien->nama;
        $this->searchPasien       = '';
        $this->hasilSearchPasien  = [];
    }

    public function clearPasien(): void
    {
        $this->pasienId          = null;
        $this->pasienNama        = '';
        $this->pasienRm          = '';
        $this->searchPasien      = '';
        $this->hasilSearchPasien = [];
    }

    // ── Pencarian obat ───────────────────────────────────────────────────

    public function updatedSearchObat(): void
    {
        if (strlen($this->searchObat) < 2) {
            $this->hasilSearch = [];
            return;
        }

        $this->hasilSearch = Barang::aktif()
            ->whereIn('jenis', ['obat', 'alkes'])
            ->where('stok', '>', 0)
            ->search($this->searchObat)
            ->limit(10)
            ->get(['id', 'kode', 'nama', 'harga_jual', 'satuan', 'stok', 'butuh_resep'])
            ->map(fn ($b) => [
                'id'          => $b->id,
                'kode'        => $b->kode,
                'nama'        => $b->nama,
                'satuan'      => $b->satuan,
                'stok'        => (float) $b->stok,
                'harga_jual'  => (float) $b->harga_jual,
                'butuh_resep' => (bool) $b->butuh_resep,
            ])
            ->toArray();
    }

    public function addItem(int $barangId): void
    {
        if (collect($this->items)->pluck('barang_id')->contains($barangId)) {
            $this->searchObat  = '';
            $this->hasilSearch = [];
            return;
        }

        $found = collect($this->hasilSearch)->firstWhere('id', $barangId);
        if (!$found) return;

        $this->items[] = [
            'barang_id'    => $found['id'],
            'nama_barang'  => $found['nama'],
            'kode'         => $found['kode'],
            'satuan'       => $found['satuan'],
            'jumlah'       => 1,
            'harga_satuan' => $found['harga_jual'],
            'subtotal'     => $found['harga_jual'],
            'stok'         => $found['stok'],
            'butuh_resep'  => $found['butuh_resep'],
        ];

        $this->searchObat  = '';
        $this->hasilSearch = [];
    }

    public function removeItem(int $i): void
    {
        array_splice($this->items, $i, 1);
    }

    public function updatedItems(): void
    {
        foreach ($this->items as $i => $item) {
            $this->items[$i]['subtotal'] = (int) ($item['jumlah'] ?? 0) * (float) ($item['harga_satuan'] ?? 0);
        }
    }

    #[Computed]
    public function totalHarga(): float
    {
        return collect($this->items)->sum('subtotal');
    }

    // ── Simpan ──────────────────────────────────────────────────────────

    public function simpanDraft(ObatRitelService $service): void
    {
        $this->doValidate();

        try {
            $tr = $this->doSimpan($service);
            session()->flash('success', "Draft {$tr->nomor_ritel} berhasil disimpan.");
            $this->redirect(route('farmasi.ritel.index'));
        } catch (\DomainException $e) {
            $this->addError('items', $e->getMessage());
        }
    }

    public function submitKeKasir(ObatRitelService $service): void
    {
        $this->doValidate();

        try {
            $tr = $this->doSimpan($service);
            $service->submitKeKasir($tr);
            session()->flash('success', "Transaksi {$tr->nomor_ritel} berhasil dikirim ke kasir.");
            $this->redirect(route('farmasi.ritel.index'));
        } catch (\DomainException $e) {
            $this->addError('items', $e->getMessage());
        }
    }

    private function doValidate(): void
    {
        $this->validate([
            'namaPembeli'    => 'required|min:3',
            'items'          => 'required|array|min:1',
            'items.*.jumlah' => 'required|integer|min:1',
        ], [
            'namaPembeli.required' => 'Nama pembeli wajib diisi.',
            'namaPembeli.min'      => 'Nama minimal 3 karakter.',
            'items.required'       => 'Minimal tambahkan 1 item obat.',
            'items.min'            => 'Minimal tambahkan 1 item obat.',
        ]);
    }

    private function doSimpan(ObatRitelService $service): TransaksiRitel
    {
        $header = [
            'nama_pembeli' => $this->namaPembeli,
            'nomor_hp'     => $this->nomorHp ?: null,
            'pasien_id'    => $this->pasienId,
            'catatan'      => $this->catatan ?: null,
        ];

        if ($this->transaksi) {
            return $service->simpanDraft($this->transaksi, $header, $this->itemsForService());
        }

        $tr = $service->buatDraft($header, auth()->id());
        return $service->simpanDraft($tr, $header, $this->itemsForService());
    }

    private function itemsForService(): array
    {
        return array_map(fn ($item) => [
            'barang_id'    => $item['barang_id'],
            'jumlah'       => (int) $item['jumlah'],
            'harga_satuan' => (float) $item['harga_satuan'],
        ], $this->items);
    }

    public function render()
    {
        return view('livewire.farmasi.ritel.ritel-form', [
            'totalHarga' => $this->totalHarga,
        ]);
    }
}
