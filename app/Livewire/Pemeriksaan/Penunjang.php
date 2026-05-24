<?php

namespace App\Livewire\Pemeriksaan;

use App\Models\ItemPenunjang;
use App\Models\PermintaanPenunjang;
use Livewire\Attributes\Computed;
use Livewire\Component;

class Penunjang extends Component
{
    public int    $kunjunganId;
    public string $activeTab = 'lab';

    // ── Lab ───────────────────────────────────────────────────
    public string $searchLab = '';
    public array  $cartLab   = [];
    // each item: {id, kode, nama, tarif, prioritas, catatan}

    // ── Radiologi ─────────────────────────────────────────────
    public string $searchRad = '';
    public array  $cartRad   = [];
    // each item: {id, kode, nama, tarif, lokasi_tubuh, indikasi_klinis}

    // ─────────────────────────────────────────────────────────
    #[Computed]
    public function suggestionsLab()
    {
        if (strlen($this->searchLab) < 2) return collect();

        return ItemPenunjang::aktif()->lab()
            ->where(function ($q) {
                $q->where('nama', 'like', '%'.$this->searchLab.'%')
                  ->orWhere('kode', 'like', '%'.$this->searchLab.'%');
            })
            ->orderByRaw("CASE WHEN kode LIKE ? THEN 0 ELSE 1 END", [$this->searchLab.'%'])
            ->limit(10)
            ->get(['id', 'kode', 'nama', 'tarif']);
    }

    #[Computed]
    public function suggestionsRad()
    {
        if (strlen($this->searchRad) < 2) return collect();

        return ItemPenunjang::aktif()->radiologi()
            ->where(function ($q) {
                $q->where('nama', 'like', '%'.$this->searchRad.'%')
                  ->orWhere('kode', 'like', '%'.$this->searchRad.'%');
            })
            ->orderByRaw("CASE WHEN kode LIKE ? THEN 0 ELSE 1 END", [$this->searchRad.'%'])
            ->limit(10)
            ->get(['id', 'kode', 'nama', 'tarif']);
    }

    #[Computed]
    public function ordersLab()
    {
        return PermintaanPenunjang::with('itemPenunjang')
            ->where('kunjungan_id', $this->kunjunganId)
            ->whereHas('itemPenunjang', fn ($q) => $q->where('kategori', 'lab'))
            ->latest()
            ->get();
    }

    #[Computed]
    public function ordersRad()
    {
        return PermintaanPenunjang::with('itemPenunjang')
            ->where('kunjungan_id', $this->kunjunganId)
            ->whereHas('itemPenunjang', fn ($q) => $q->where('kategori', 'radiologi'))
            ->latest()
            ->get();
    }

    // ── Lab actions ───────────────────────────────────────────
    public function addToCartLab(int $id, string $kode, string $nama, string $tarif): void
    {
        foreach ($this->cartLab as $item) {
            if ($item['id'] === $id) {
                $this->searchLab = '';
                $this->dispatch('notify', type: 'warning', message: "{$nama} sudah ada di daftar order.");
                return;
            }
        }

        $alreadyOrdered = PermintaanPenunjang::where('kunjungan_id', $this->kunjunganId)
            ->where('item_penunjang_id', $id)
            ->whereNotIn('status', ['dibatalkan'])
            ->exists();

        if ($alreadyOrdered) {
            $this->searchLab = '';
            $this->dispatch('notify', type: 'warning', message: "{$nama} sudah pernah dipesan untuk kunjungan ini.");
            return;
        }

        $this->cartLab[] = [
            'id'        => $id,
            'kode'      => $kode,
            'nama'      => $nama,
            'tarif'     => $tarif,
            'prioritas' => 'normal',
            'catatan'   => '',
        ];

        $this->searchLab = '';
        unset($this->suggestionsLab);
    }

    public function removeFromCartLab(int $index): void
    {
        array_splice($this->cartLab, $index, 1);
    }

    public function submitLab(): void
    {
        if (empty($this->cartLab)) return;

        foreach ($this->cartLab as $item) {
            PermintaanPenunjang::create([
                'kunjungan_id'      => $this->kunjunganId,
                'item_penunjang_id' => $item['id'],
                'jumlah'            => 1,
                'prioritas'         => $item['prioritas'],
                'catatan'           => $item['catatan'] ?: null,
                'status'            => 'dipesan',
                'ordered_by'        => auth()->id(),
            ]);
        }

        $count = count($this->cartLab);
        $this->cartLab = [];
        unset($this->ordersLab);
        $this->dispatch('notify', type: 'success', message: "{$count} order laboratorium berhasil dikirim.");
    }

    // ── Radiologi actions ─────────────────────────────────────
    public function addToCartRad(int $id, string $kode, string $nama, string $tarif): void
    {
        foreach ($this->cartRad as $item) {
            if ($item['id'] === $id) {
                $this->searchRad = '';
                $this->dispatch('notify', type: 'warning', message: "{$nama} sudah ada di daftar order.");
                return;
            }
        }

        $alreadyOrdered = PermintaanPenunjang::where('kunjungan_id', $this->kunjunganId)
            ->where('item_penunjang_id', $id)
            ->whereNotIn('status', ['dibatalkan'])
            ->exists();

        if ($alreadyOrdered) {
            $this->searchRad = '';
            $this->dispatch('notify', type: 'warning', message: "{$nama} sudah pernah dipesan untuk kunjungan ini.");
            return;
        }

        $this->cartRad[] = [
            'id'              => $id,
            'kode'            => $kode,
            'nama'            => $nama,
            'tarif'           => $tarif,
            'lokasi_tubuh'    => '',
            'indikasi_klinis' => '',
        ];

        $this->searchRad = '';
        unset($this->suggestionsRad);
    }

    public function removeFromCartRad(int $index): void
    {
        array_splice($this->cartRad, $index, 1);
    }

    public function submitRad(): void
    {
        if (empty($this->cartRad)) return;

        foreach ($this->cartRad as $item) {
            PermintaanPenunjang::create([
                'kunjungan_id'      => $this->kunjunganId,
                'item_penunjang_id' => $item['id'],
                'jumlah'            => 1,
                'lokasi_tubuh'      => $item['lokasi_tubuh'] ?: null,
                'indikasi_klinis'   => $item['indikasi_klinis'] ?: null,
                'status'            => 'dipesan',
                'ordered_by'        => auth()->id(),
            ]);
        }

        $count = count($this->cartRad);
        $this->cartRad = [];
        unset($this->ordersRad);
        $this->dispatch('notify', type: 'success', message: "{$count} order radiologi berhasil dikirim.");
    }

    // ── Shared actions ────────────────────────────────────────
    public function batalkan(int $id): void
    {
        $order = PermintaanPenunjang::find($id);
        if (! $order || $order->kunjungan_id !== $this->kunjunganId) return;

        if ($order->status !== 'dipesan') {
            $this->dispatch('notify', type: 'warning', message: 'Order sudah diproses, tidak dapat dibatalkan.');
            return;
        }

        $order->update(['status' => 'dibatalkan']);

        unset($this->ordersLab);
        unset($this->ordersRad);
        $this->dispatch('notify', type: 'success', message: 'Order berhasil dibatalkan.');
    }

    public function render()
    {
        return view('livewire.pemeriksaan.penunjang');
    }
}
