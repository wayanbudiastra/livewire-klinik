<?php

namespace App\Livewire\Inventory\Bhp;

use App\Models\{Barang, PemakaianBhp};
use App\Services\Inventory\PemakaianBhpService;
use Livewire\Component;

class BhpForm extends Component
{
    public string $tanggalPemakaian = '';
    public string $catatan          = '';

    public array  $items       = [];
    public array  $hasilSearch = [];
    public string $searchBarang = '';

    public ?PemakaianBhp $bhp = null;

    public function mount(?int $id = null): void
    {
        $this->tanggalPemakaian = now()->format('Y-m-d');

        if ($id) {
            $this->bhp = PemakaianBhp::with('items.barang')->findOrFail($id);

            $this->tanggalPemakaian = $this->bhp->tanggal_pemakaian->format('Y-m-d');
            $this->catatan          = $this->bhp->catatan ?? '';

            $this->items = $this->bhp->items->map(fn ($item) => [
                'item_id'              => $item->id,
                'barang_id'            => $item->barang_id,
                'nama_barang'          => $item->barang->nama,
                'satuan'               => $item->barang->satuan,
                'jumlah'               => (float) $item->jumlah,
                'harga_pokok_saat_itu' => (float) $item->harga_pokok_saat_itu,
                'nilai_total'          => (float) $item->nilai_total,
                'stok_tersedia'        => $item->barang->stok,
                'keterangan'           => $item->keterangan ?? '',
            ])->toArray();
        }
    }

    public function updatedSearchBarang(): void
    {
        if (strlen($this->searchBarang) < 2) {
            $this->hasilSearch = [];
            return;
        }

        $this->hasilSearch = Barang::where('is_active', true)
            ->whereIn('jenis', ['bahan_habis_pakai', 'alkes'])
            ->where(fn ($q) => $q
                ->where('nama', 'like', "%{$this->searchBarang}%")
                ->orWhere('kode', 'like', "%{$this->searchBarang}%")
            )
            ->limit(8)
            ->get(['id', 'kode', 'nama', 'satuan', 'stok', 'harga_pokok'])
            ->map(fn ($b) => [
                'id'           => $b->id,
                'kode'         => $b->kode,
                'nama'         => $b->nama,
                'satuan'       => $b->satuan,
                'stok'         => (float) $b->stok,
                'harga_pokok'  => (float) $b->harga_pokok,
            ])
            ->toArray();
    }

    public function addItem(int $barangId): void
    {
        if (collect($this->items)->pluck('barang_id')->contains($barangId)) return;

        $found = collect($this->hasilSearch)->firstWhere('id', $barangId);
        if (!$found) return;

        $this->items[] = [
            'item_id'              => null,
            'barang_id'            => $found['id'],
            'nama_barang'          => $found['nama'],
            'satuan'               => $found['satuan'],
            'jumlah'               => 1,
            'harga_pokok_saat_itu' => $found['harga_pokok'],
            'nilai_total'          => $found['harga_pokok'],
            'stok_tersedia'        => $found['stok'],
            'keterangan'           => '',
        ];

        $this->searchBarang = '';
        $this->hasilSearch  = [];
    }

    public function removeItem(int $i): void
    {
        array_splice($this->items, $i, 1);
    }

    public function updatedItems(): void
    {
        foreach ($this->items as $i => $item) {
            $this->items[$i]['nilai_total'] = (float) ($item['jumlah'] ?? 0) * (float) ($item['harga_pokok_saat_itu'] ?? 0);
        }
    }

    public function getTotalNilaiProperty(): float
    {
        return collect($this->items)->sum('nilai_total');
    }

    /** Simpan/sync header + items sebagai draft (dokumen baru maupun yang sudah ada). */
    private function persistDraft(PemakaianBhpService $service): PemakaianBhp
    {
        if ($this->bhp) {
            // Update header saja (item sudah disimpan inline)
            $this->bhp->update([
                'tanggal_pemakaian' => $this->tanggalPemakaian,
                'catatan'           => $this->catatan ?: null,
            ]);
            // Sync items
            $this->bhp->items()->delete();
            foreach ($this->items as $item) {
                $service->tambahItem(
                    $this->bhp,
                    $item['barang_id'],
                    (float) $item['jumlah'],
                    $item['keterangan'] ?: null
                );
            }

            return $this->bhp;
        }

        $bhp = $service->buatDraft([
            'tanggal_pemakaian' => $this->tanggalPemakaian,
            'catatan'           => $this->catatan ?: null,
        ], auth()->id());

        foreach ($this->items as $item) {
            $service->tambahItem(
                $bhp,
                $item['barang_id'],
                (float) $item['jumlah'],
                $item['keterangan'] ?: null
            );
        }

        $this->bhp = $bhp;

        return $bhp;
    }

    public function simpan(PemakaianBhpService $service): void
    {
        $this->validate([
            'tanggalPemakaian' => 'required|date',
            'items'            => 'required|array|min:1',
            'items.*.jumlah'   => 'required|numeric|min:0.01',
        ], [
            'items.required' => 'Minimal harus ada 1 item BHP.',
            'items.min'      => 'Minimal harus ada 1 item BHP.',
        ]);

        try {
            $bhp = $this->persistDraft($service);

            session()->flash('success', "Dokumen {$bhp->nomor_bhp} berhasil disimpan.");
            $this->redirect(route('inventory.bhp.index'));
        } catch (\DomainException $e) {
            $this->addError('items', $e->getMessage());
        }
    }

    public function verifikasi(PemakaianBhpService $service): void
    {
        $this->validate([
            'tanggalPemakaian' => 'required|date',
            'items'            => 'required|array|min:1',
            'items.*.jumlah'   => 'required|numeric|min:0.01',
        ], [
            'items.required' => 'Minimal harus ada 1 item BHP.',
            'items.min'      => 'Minimal harus ada 1 item BHP.',
        ]);

        try {
            // Dokumen baru (belum di-"Simpan Draft") -- buat drafnya dulu, baru langsung verifikasi.
            $bhp = $this->persistDraft($service);

            $service->verifikasi($bhp, auth()->id());
            session()->flash('success', "BHP {$bhp->nomor_bhp} diverifikasi. Stok berkurang.");
            $this->redirect(route('inventory.bhp.index'));
        } catch (\DomainException $e) {
            $this->addError('items', $e->getMessage());
        }
    }

    public function render()
    {
        return view('livewire.inventory.bhp.bhp-form', [
            'totalNilai' => $this->totalNilai,
        ]);
    }
}
