<?php

namespace App\Livewire\Kasir\Susulan;

use App\Models\Barang;
use App\Models\Dokter;
use App\Models\Invoice;
use App\Models\ItemPenunjang;
use App\Models\Kunjungan;
use App\Models\Pasien;
use App\Models\TransaksiRitel;
use App\Services\Farmasi\ObatRitelService;
use App\Services\Farmasi\SusulanService;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Halaman kasir: buat order susulan (lab ATAU obat) atas saran dokter untuk
 * pasien yang bill kunjungannya sudah lunas, lalu proses pembayarannya.
 * Aturan bisnis ditegakkan di SusulanService.
 */
class SusulanKasir extends Component
{
    public string $cariPasien = '';
    public ?int $pasienId = null;
    public ?int $kunjunganId = null;
    public ?int $dokterId = null;
    public string $jenis = 'lab';
    public string $cariItem = '';
    public array $keranjang = []; // [{id, nama, harga, jumlah}]
    public string $catatan = '';

    public ?int $bayarId = null;
    public string $metodeBayar = 'tunai';
    public string $totalBayarInput = '';

    public function mount(): void
    {
        $this->authorize('ritel.susulan');
    }

    #[Computed]
    public function hasilPasien()
    {
        if (strlen(trim($this->cariPasien)) < 2 || $this->pasienId) return collect();
        return Pasien::search($this->cariPasien)->limit(8)->get(['id', 'nomor_rm', 'nama']);
    }

    #[Computed]
    public function pasien(): ?Pasien
    {
        return $this->pasienId ? Pasien::find($this->pasienId) : null;
    }

    /** Kunjungan pasien yang memenuhi syarat: billing lunas & dalam batas hari. */
    #[Computed]
    public function kunjunganLayak()
    {
        if (! $this->pasienId) return collect();
        $maks = (int) config('emr.susulan_maks_hari', 14);

        return Kunjungan::with('dokter.user', 'poli')
            ->where('pasien_id', $this->pasienId)
            ->where('status', '!=', 'dibatalkan')
            ->whereDate('tanggal', '>=', today()->subDays($maks))
            ->whereHas('invoice', fn ($q) => $q->where('status', 'lunas'))
            ->latest('tanggal')
            ->get();
    }

    #[Computed]
    public function daftarDokter()
    {
        return Dokter::with('user')->get();
    }

    #[Computed]
    public function hasilItem()
    {
        $kata = trim($this->cariItem);
        if (strlen($kata) < 2) return collect();

        if ($this->jenis === 'lab') {
            return ItemPenunjang::where('kategori', 'lab')->where('is_active', true)
                ->where(fn ($q) => $q->where('nama', 'like', "%{$kata}%")->orWhere('kode', 'like', "%{$kata}%"))
                ->limit(8)->get()
                ->map(fn ($i) => ['id' => $i->id, 'nama' => $i->nama, 'harga' => (float) $i->tarif]);
        }

        return Barang::where('is_active', true)
            ->where(fn ($q) => $q->where('nama', 'like', "%{$kata}%")->orWhere('kode', 'like', "%{$kata}%"))
            ->limit(8)->get()
            ->map(fn ($b) => ['id' => $b->id, 'nama' => $b->nama, 'harga' => (float) $b->harga_jual]);
    }

    /** Susulan pasien terpilih yang menunggu pembayaran. */
    #[Computed]
    public function menungguBayar()
    {
        if (! $this->pasienId) return collect();
        return TransaksiRitel::susulan()->with('items.barang', 'items.itemPenunjang', 'dokter.user')
            ->where('pasien_id', $this->pasienId)->where('status', 'menunggu_kasir')->latest()->get();
    }

    public function pilihPasien(int $id): void
    {
        $this->reset('kunjunganId', 'keranjang', 'bayarId');
        $this->pasienId = $id;
        $this->cariPasien = '';
    }

    public function gantiPasien(): void
    {
        $this->reset('pasienId', 'kunjunganId', 'dokterId', 'keranjang', 'cariItem', 'bayarId');
    }

    public function updatedJenis(): void
    {
        // lab & obat dipisah: ganti jenis mengosongkan keranjang
        $this->keranjang = [];
        $this->cariItem = '';
    }

    public function tambahItem(int $id, string $nama, float $harga): void
    {
        foreach ($this->keranjang as $row) {
            if ($row['id'] === $id) return;
        }
        $this->keranjang[] = ['id' => $id, 'nama' => $nama, 'harga' => $harga, 'jumlah' => 1];
        $this->cariItem = '';
    }

    public function hapusItem(int $index): void
    {
        array_splice($this->keranjang, $index, 1);
    }

    public function getTotalProperty(): float
    {
        return collect($this->keranjang)->sum(fn ($r) => $r['harga'] * ($this->jenis === 'lab' ? 1 : max(1, (int) $r['jumlah'])));
    }

    public function simpan(SusulanService $service): void
    {
        $this->authorize('ritel.susulan');

        $items = collect($this->keranjang)->map(fn ($r) => $this->jenis === 'lab'
            ? ['item_penunjang_id' => $r['id']]
            : ['barang_id' => $r['id'], 'jumlah' => (int) $r['jumlah']]
        )->all();

        try {
            $tr = $service->buat([
                'pasien_id'    => $this->pasienId,
                'kunjungan_id' => $this->kunjunganId,
                'dokter_id'    => $this->dokterId,
                'jenis'        => $this->jenis,
                'catatan'      => $this->catatan ?: null,
                'items'        => $items,
            ], auth()->id());
        } catch (\DomainException $e) {
            $this->addError('simpan', $e->getMessage());
            return;
        }

        $this->reset('keranjang', 'cariItem', 'catatan');
        $this->bayarId = $tr->id;
        $this->totalBayarInput = (string) (float) $tr->total_harga;
        unset($this->menungguBayar);
        session()->flash('success', "Susulan {$tr->nomor_ritel} dibuat. Lanjutkan pembayaran.");
    }

    public function bukaBayar(int $id): void
    {
        $tr = TransaksiRitel::susulan()->find($id);
        $this->bayarId = $tr?->id;
        $this->totalBayarInput = $tr ? (string) (float) $tr->total_harga : '';
    }

    public function bayar(ObatRitelService $service): void
    {
        $this->authorize('ritel.susulan');
        $this->validate([
            'metodeBayar'     => 'required|in:tunai,transfer,kartu,split',
            'totalBayarInput' => 'required|numeric|min:0',
        ]);

        $tr = TransaksiRitel::susulan()->findOrFail($this->bayarId);
        try {
            $service->prosesBayar($tr, [
                'metode_bayar' => $this->metodeBayar,
                'total_bayar'  => (float) $this->totalBayarInput,
            ], auth()->id());
        } catch (\DomainException $e) {
            $this->addError('totalBayarInput', $e->getMessage());
            return;
        }

        $this->reset('bayarId', 'totalBayarInput');
        unset($this->menungguBayar);
        session()->flash('success', 'Pembayaran susulan berhasil. Obat diserahkan oleh farmasi.');
    }

    public function batalkan(int $id, ObatRitelService $service): void
    {
        $this->authorize('ritel.susulan');
        $tr = TransaksiRitel::susulan()->findOrFail($id);
        try {
            $service->batalkan($tr); // hanya draft/menunggu_kasir; tidak ada retur setelah dibayar
        } catch (\DomainException $e) {
            $this->addError('simpan', $e->getMessage());
            return;
        }
        $this->reset('bayarId');
        unset($this->menungguBayar);
    }

    public function render()
    {
        return view('livewire.kasir.susulan.susulan-kasir');
    }
}
