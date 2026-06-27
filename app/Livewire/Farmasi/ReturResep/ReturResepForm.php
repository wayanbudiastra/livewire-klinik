<?php

namespace App\Livewire\Farmasi\ReturResep;

use App\Models\Resep;
use App\Services\Farmasi\ReturResepService;
use Livewire\Component;

class ReturResepForm extends Component
{
    public string $search        = '';
    public array  $resepTersedia = [];
    public ?int   $resepId       = null;

    public array  $itemRows    = [];
    public array  $racikanRows = [];

    public string $alasan              = '';
    public string $catatan             = '';
    public string $metodePengembalian  = 'tunai';

    public function updatedSearch(): void
    {
        if (strlen($this->search) < 2) {
            $this->resepTersedia = [];
            return;
        }

        $this->resepTersedia = Resep::where('is_locked', true)
            ->whereHas('kunjungan.invoice', fn ($q) => $q->where('status', 'lunas'))
            ->whereHas('kunjungan.pasien', fn ($q) =>
                $q->where('nama', 'like', "%{$this->search}%")
                  ->orWhere('nomor_rm', 'like', "%{$this->search}%")
            )
            ->with('kunjungan.pasien:id,nama,nomor_rm')
            ->latest('locked_at')
            ->limit(8)
            ->get()
            ->map(fn ($r) => [
                'id'          => $r->id,
                'pasien_nama' => $r->kunjungan->pasien->nama,
                'no_rm'       => $r->kunjungan->pasien->nomor_rm,
                'locked_at'   => $r->locked_at?->format('d/m/Y H:i'),
            ])
            ->toArray();
    }

    public function pilihResep(int $id, ReturResepService $service): void
    {
        $resep = Resep::with(['kunjungan.invoice', 'itemResep.barang', 'racikan.bahanRacikan.barang'])->findOrFail($id);

        $cek = $service->cekBolehRetur($resep);
        if (! $cek['boleh']) {
            $this->dispatch('notify', type: 'error', message: $cek['alasan']);
            return;
        }

        $this->resepId        = $id;
        $this->search          = '';
        $this->resepTersedia   = [];

        $this->itemRows = $resep->itemResep
            ->map(function ($i) use ($service) {
                $sisa = $service->hitungSisaBisaDiretur($i);
                return [
                    'item_resep_id' => $i->id,
                    'barang_id'     => $i->barang_id,
                    'nama_barang'   => $i->barang->nama,
                    'satuan'        => $i->barang->satuan,
                    'harga_satuan'  => (string) $i->barang->harga_jual,
                    'sisa'          => $sisa,
                    'jumlah_retur'  => 0,
                ];
            })
            ->filter(fn ($r) => $r['sisa'] > 0)
            ->values()
            ->toArray();

        $this->racikanRows = $resep->racikan
            ->map(function ($r) use ($service) {
                $totalBahan = $r->bahanRacikan->sum(fn ($b) => $b->barang->harga_jual * $b->jumlah);
                return [
                    'racikan_id'   => $r->id,
                    'barang_id'    => $r->bahanRacikan->first()?->barang_id,
                    'nama_racikan' => $r->nama_racikan,
                    'harga_satuan' => (string) $totalBahan,
                    'bisa_diretur' => $service->racikanBisaDiretur($r),
                    'dipilih'      => false,
                ];
            })
            ->filter(fn ($r) => $r['bisa_diretur'])
            ->values()
            ->toArray();
    }

    public function getTotalNilaiReturProperty(): float
    {
        $totalItem    = collect($this->itemRows)->sum(fn ($i) => ($i['jumlah_retur'] ?? 0) * $i['harga_satuan']);
        $totalRacikan = collect($this->racikanRows)->where('dipilih', true)->sum('harga_satuan');
        return $totalItem + $totalRacikan;
    }

    private function itemDipilih(): array
    {
        $items = collect($this->itemRows)
            ->filter(fn ($i) => (float) ($i['jumlah_retur'] ?? 0) > 0)
            ->map(fn ($i) => [
                'item_resep_id' => $i['item_resep_id'],
                'racikan_id'    => null,
                'barang_id'     => $i['barang_id'],
                'jumlah_retur'  => (float) $i['jumlah_retur'],
                'harga_satuan'  => (float) $i['harga_satuan'],
            ])
            ->values();

        $racikan = collect($this->racikanRows)
            ->filter(fn ($r) => $r['dipilih'])
            ->map(fn ($r) => [
                'item_resep_id' => null,
                'racikan_id'    => $r['racikan_id'],
                'barang_id'     => $r['barang_id'],
                'jumlah_retur'  => 1,
                'harga_satuan'  => (float) $r['harga_satuan'],
            ])
            ->values();

        return $items->merge($racikan)->toArray();
    }

    public function proses(ReturResepService $service): void
    {
        $this->validate([
            'resepId'             => 'required|exists:resep,id',
            'alasan'              => 'required|string|max:100',
            'metodePengembalian'  => 'required|in:tunai,bank,deposit',
        ]);

        $items = $this->itemDipilih();
        if (empty($items)) {
            $this->addError('items', 'Pilih minimal satu item untuk diretur.');
            return;
        }

        try {
            $retur = $service->proses([
                'resep_id'             => $this->resepId,
                'alasan'               => $this->alasan,
                'catatan'              => $this->catatan ?: null,
                'metode_pengembalian'  => $this->metodePengembalian,
                'items'                => $items,
            ], auth()->id());

            $this->dispatch('notify', type: 'success',
                message: "Retur {$retur->nomor_retur} berhasil diproses. Rp " . number_format($retur->total_nilai_retur, 0, ',', '.') . ' dikembalikan.');
            $this->redirect(route('farmasi.retur-resep.index'));
        } catch (\DomainException $e) {
            $this->addError('items', $e->getMessage());
        }
    }

    public function render()
    {
        return view('livewire.farmasi.retur-resep.retur-resep-form');
    }
}
