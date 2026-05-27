<?php

namespace App\Livewire\Pemeriksaan;

use App\Models\Barang;
use App\Models\BahanRacikan;
use App\Models\ItemResep;
use App\Models\Racikan;
use App\Models\Resep;
use Livewire\Attributes\Computed;
use Livewire\Component;

class ResepObat extends Component
{
    public int    $kunjunganId;
    public string $activeTab = 'non_racikan';

    // ── Non-racikan ─────────────────────────────────────────
    public string $searchObat = '';
    public array  $cartObat   = [];
    // item: {id, kode, nama, harga, satuan, jumlah, signa}

    // ── Racikan ─────────────────────────────────────────────
    public string $searchBahan          = '';
    public string $namaRacikan          = '';
    public string $metodeRacikan        = 'puyer';
    public int    $jumlahSediaanRacikan = 10;
    public string $aturanPakaiRacikan   = '';
    public string $catatanRacikan       = '';
    public array  $cartBahan            = [];
    // item: {id, kode, nama, jumlah, satuan}

    // ────────────────────────────────────────────────────────
    #[Computed]
    public function suggestionsObat()
    {
        if (strlen($this->searchObat) < 2) return collect();

        return Barang::aktif()
            ->whereIn('jenis', ['obat', 'alkes'])
            ->where('stok', '>', 0)
            ->search($this->searchObat)
            ->limit(10)
            ->get(['id', 'kode', 'nama', 'harga_jual', 'satuan', 'stok']);
    }

    #[Computed]
    public function suggestionsBahan()
    {
        if (strlen($this->searchBahan) < 2) return collect();

        return Barang::aktif()
            ->whereIn('jenis', ['obat', 'alkes'])
            ->search($this->searchBahan)
            ->limit(10)
            ->get(['id', 'kode', 'nama', 'satuan', 'stok']);
    }

    #[Computed]
    public function resep()
    {
        return Resep::with(['itemResep.barang', 'racikan.bahanRacikan.barang'])
            ->where('kunjungan_id', $this->kunjunganId)
            ->latest()
            ->first();
    }

    #[Computed]
    public function isLocked(): bool
    {
        return $this->resep?->is_locked ?? false;
    }

    // ── Non-racikan actions ──────────────────────────────────
    public function addToCartObat(int $id, string $kode, string $nama, string $harga, string $satuan): void
    {
        foreach ($this->cartObat as $item) {
            if ($item['id'] === $id) {
                $this->searchObat = '';
                $this->dispatch('notify', type: 'warning', message: "{$nama} sudah ada di keranjang.");
                return;
            }
        }

        $this->cartObat[] = [
            'id'     => $id,
            'kode'   => $kode,
            'nama'   => $nama,
            'harga'  => $harga,
            'satuan' => $satuan,
            'jumlah' => 1,
            'signa'  => '',
        ];

        $this->searchObat = '';
        unset($this->suggestionsObat);
    }

    public function removeFromCartObat(int $index): void
    {
        array_splice($this->cartObat, $index, 1);
    }

    public function submitNonRacikan(): void
    {
        if (empty($this->cartObat)) return;

        foreach ($this->cartObat as $item) {
            $barang = Barang::find($item['id']);
            if (! $barang || $barang->stok < $item['jumlah']) {
                $this->dispatch('notify', type: 'error',
                    message: "Stok {$item['nama']} tidak mencukupi (tersisa {$barang?->stok}).");
                return;
            }
        }

        $resep = $this->getOrCreateResep();

        foreach ($this->cartObat as $item) {
            ItemResep::create([
                'resep_id'    => $resep->id,
                'barang_id'   => $item['id'],
                'jumlah'      => $item['jumlah'],
                'aturan_pakai'=> $item['signa'] ?: null,
            ]);
        }

        $count = count($this->cartObat);
        $this->cartObat   = [];
        $this->searchObat = '';
        unset($this->resep);
        $this->dispatch('notify', type: 'success', message: "{$count} obat berhasil ditambahkan ke resep.");
    }

    // ── Racikan actions ──────────────────────────────────────
    public function addBahan(int $id, string $kode, string $nama, string $satuan): void
    {
        foreach ($this->cartBahan as $b) {
            if ($b['id'] === $id) {
                $this->searchBahan = '';
                $this->dispatch('notify', type: 'warning', message: "{$nama} sudah ada di komposisi.");
                return;
            }
        }

        $this->cartBahan[] = [
            'id'     => $id,
            'kode'   => $kode,
            'nama'   => $nama,
            'jumlah' => 1,
            'satuan' => $satuan,
        ];

        $this->searchBahan = '';
        unset($this->suggestionsBahan);
    }

    public function removeBahan(int $index): void
    {
        array_splice($this->cartBahan, $index, 1);
    }

    public function submitRacikan(): void
    {
        $this->validate([
            'namaRacikan'          => 'required|string|max:255',
            'metodeRacikan'        => 'required|in:puyer,kapsul,salep,krim,sirup',
            'jumlahSediaanRacikan' => 'required|integer|min:1|max:9999',
            'aturanPakaiRacikan'   => 'nullable|string|max:255',
        ]);

        if (empty($this->cartBahan)) {
            $this->dispatch('notify', type: 'error', message: 'Tambahkan minimal 1 bahan racikan.');
            return;
        }

        $resep = $this->getOrCreateResep();

        $racikan = Racikan::create([
            'resep_id'       => $resep->id,
            'nama_racikan'   => $this->namaRacikan,
            'metode'         => $this->metodeRacikan,
            'jumlah_sediaan' => $this->jumlahSediaanRacikan,
            'aturan_pakai'   => $this->aturanPakaiRacikan ?: null,
            'catatan'        => $this->catatanRacikan ?: null,
        ]);

        foreach ($this->cartBahan as $b) {
            BahanRacikan::create([
                'racikan_id' => $racikan->id,
                'barang_id'  => $b['id'],
                'jumlah'     => $b['jumlah'],
                'satuan'     => $b['satuan'] ?: null,
            ]);
        }

        $this->resetRacikanForm();
        unset($this->resep);
        $this->dispatch('notify', type: 'success', message: "Racikan {$racikan->nama_racikan} berhasil ditambahkan.");
    }

    // ── Delete actions ───────────────────────────────────────
    public function batalkanItem(int $itemId): void
    {
        $item = ItemResep::find($itemId);
        if (! $item || $item->resep->kunjungan_id !== $this->kunjunganId) return;
        if ($item->resep->is_locked) {
            $this->dispatch('notify', type: 'error', message: 'Resep sudah dikunci oleh Apoteker.');
            return;
        }

        $item->delete();
        unset($this->resep);
        $this->dispatch('notify', type: 'success', message: 'Item resep dihapus.');
    }

    public function batalkanRacikan(int $racikanId): void
    {
        $racikan = Racikan::find($racikanId);
        if (! $racikan || $racikan->resep->kunjungan_id !== $this->kunjunganId) return;
        if ($racikan->resep->is_locked) {
            $this->dispatch('notify', type: 'error', message: 'Resep sudah dikunci oleh Apoteker.');
            return;
        }

        $racikan->delete();
        unset($this->resep);
        $this->dispatch('notify', type: 'success', message: 'Racikan dihapus.');
    }

    // ── Helpers ──────────────────────────────────────────────
    private function getOrCreateResep(): Resep
    {
        $existing = Resep::where('kunjungan_id', $this->kunjunganId)
            ->where('is_locked', false)
            ->latest()
            ->first();

        if ($existing) return $existing;

        return Resep::create([
            'kunjungan_id' => $this->kunjunganId,
            'dokter_id'    => auth()->user()?->dokter?->id,
            'status'       => 'menunggu',
        ]);
    }

    private function resetRacikanForm(): void
    {
        $this->namaRacikan          = '';
        $this->metodeRacikan        = 'puyer';
        $this->jumlahSediaanRacikan = 10;
        $this->aturanPakaiRacikan   = '';
        $this->catatanRacikan       = '';
        $this->cartBahan            = [];
        $this->searchBahan          = '';
    }

    public function render()
    {
        return view('livewire.pemeriksaan.resep-obat');
    }
}
