<?php

namespace App\Livewire\Farmasi;

use App\Models\Barang;
use App\Models\BahanRacikan;
use App\Models\ItemResep;
use App\Models\MutasiStok;
use App\Models\Racikan;
use App\Models\Resep;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

class ResepFarmasi extends Component
{
    use WithPagination;

    public string $search       = '';
    public string $statusFilter = 'menunggu';

    // Edit item non-racikan
    public ?int  $editingItemId  = null;
    public int   $editJumlah     = 1;
    public string $editSigna     = '';

    // Edit racikan header
    public ?int  $editingRacikanId  = null;
    public int   $editJumlahSediaan = 10;
    public string $editAturanPakai  = '';

    // ────────────────────────────────────────────────────────
    public function updatedSearch(): void    { $this->resetPage(); }
    public function updatedStatusFilter(): void { $this->resetPage(); }

    #[Computed]
    public function resepList()
    {
        return Resep::with([
                'kunjungan.pasien',
                'kunjungan.dokter.user:id,nama',
                'kunjungan.invoice' => fn ($q) => $q->select('billing.id', 'billing.kunjungan_id', 'billing.status'),
                'itemResep.barang',
                'racikan.bahanRacikan.barang',
                'locker:id,nama',
            ])
            ->when($this->statusFilter === 'menunggu', fn ($q) => $q->where('is_locked', false)->where('status', 'menunggu'))
            ->when($this->statusFilter === 'dikonfirmasi', fn ($q) => $q->where('is_locked', true))
            ->when($this->search, function ($q) {
                $q->whereHas('kunjungan.pasien', fn ($p) =>
                    $p->where('nama', 'like', '%'.$this->search.'%')
                      ->orWhere('nomor_rm', 'like', '%'.$this->search.'%')
                );
            })
            ->latest()
            ->paginate(10);
    }

    // ── Edit item non-racikan ────────────────────────────────
    public function openEditItem(int $itemId): void
    {
        $item = ItemResep::find($itemId);
        if (! $item) return;
        $this->editingItemId = $itemId;
        $this->editJumlah    = $item->jumlah;
        $this->editSigna     = $item->aturan_pakai ?? '';
    }

    public function saveEditItem(): void
    {
        $this->validate([
            'editJumlah' => 'required|integer|min:1',
        ]);

        $item = ItemResep::find($this->editingItemId);
        // Tombol edit memang disembunyikan di Blade kalau resep sudah
        // terkunci, tapi itu cuma proteksi UI -- hapusItem() di bawah sudah
        // benar mengecek is_locked di server, saveEditItem() ini tadinya
        // lupa. Kalau resep sudah dikonfirmasi, stok sudah dipotong sesuai
        // jumlah LAMA; mengubah jumlah di sini tanpa itu akan bikin jumlah
        // tercatat tidak nyambung lagi dengan stok yang sudah benar-benar
        // keluar.
        if (! $item || $item->resep?->is_locked) return;

        $barang = Barang::find($item->barang_id);
        if ($barang && $barang->stok < $this->editJumlah) {
            $this->dispatch('notify', ['type' => 'error',
                'message' => "Stok {$barang->nama} tidak mencukupi (tersisa {$barang->stok})."]);
            return;
        }

        $item->update([
            'jumlah'      => $this->editJumlah,
            'aturan_pakai'=> $this->editSigna ?: null,
        ]);

        $this->editingItemId = null;
        unset($this->resepList);
        $this->dispatch('notify', ['type' => 'success', 'message' => 'Item resep diperbarui.']);
    }

    public function cancelEditItem(): void
    {
        $this->editingItemId = null;
    }

    // ── Edit racikan ─────────────────────────────────────────
    public function openEditRacikan(int $racikanId): void
    {
        $r = Racikan::find($racikanId);
        if (! $r) return;
        $this->editingRacikanId  = $racikanId;
        $this->editJumlahSediaan = $r->jumlah_sediaan;
        $this->editAturanPakai   = $r->aturan_pakai ?? '';
    }

    public function saveEditRacikan(): void
    {
        $this->validate([
            'editJumlahSediaan' => 'required|integer|min:1',
        ]);

        $r = Racikan::find($this->editingRacikanId);
        // Sama seperti saveEditItem() -- is_locked wajib dicek di server,
        // bukan cuma disembunyikan tombolnya di Blade.
        if ($r && ! $r->resep?->is_locked) {
            $r->update([
                'jumlah_sediaan' => $this->editJumlahSediaan,
                'aturan_pakai'   => $this->editAturanPakai ?: null,
            ]);
        }

        $this->editingRacikanId = null;
        unset($this->resepList);
        $this->dispatch('notify', ['type' => 'success', 'message' => 'Racikan diperbarui.']);
    }

    public function cancelEditRacikan(): void
    {
        $this->editingRacikanId = null;
    }

    // ── Hapus item / racikan ─────────────────────────────────
    public function hapusItem(int $itemId): void
    {
        $item = ItemResep::find($itemId);
        if (! $item || $item->resep?->is_locked) return;
        $item->delete();
        unset($this->resepList);
        $this->dispatch('notify', ['type' => 'success', 'message' => 'Item resep dihapus.']);
    }

    public function hapusRacikan(int $racikanId): void
    {
        $r = Racikan::find($racikanId);
        if (! $r || $r->resep?->is_locked) return;
        $r->delete();
        unset($this->resepList);
        $this->dispatch('notify', ['type' => 'success', 'message' => 'Racikan dihapus.']);
    }

    // ── Konfirmasi (lock) resep ──────────────────────────────
    public function konfirmasi(int $resepId): void
    {
        $resep = Resep::with(['itemResep.barang', 'racikan.bahanRacikan.barang'])->find($resepId);
        if (! $resep || $resep->is_locked) return;

        try {
            \Illuminate\Support\Facades\DB::transaction(function () use ($resep) {
                // Kunci ulang resep DI DALAM transaksi -- mencegah 2 apoteker
                // konfirmasi resep yang sama bersamaan.
                $resepLocked = Resep::where('id', $resep->id)->lockForUpdate()->firstOrFail();
                if ($resepLocked->is_locked) {
                    throw new \DomainException('Resep ini sudah dikonfirmasi (kemungkinan oleh proses lain yang berjalan bersamaan).');
                }

                // Barang::pastikanCukup() mengunci row barang & mengecek ulang
                // stok DI DALAM transaksi ini (pola yang sama seperti yang
                // sudah benar dipakai ObatRitelService) -- sebelumnya stok
                // dicek sekali sebelum transaksi lalu dipotong tanpa lock,
                // jadi 2 resep beda yang sama-sama pakai obat yang sama bisa
                // lolos cek "stok cukup" bersamaan padahal digabung sudah
                // tidak cukup.
                foreach ($resep->itemResep as $item) {
                    if (! $item->barang_id) continue;

                    $barang      = Barang::pastikanCukup($item->barang_id, $item->jumlah);
                    $stokSebelum = $barang->stok;
                    $barang->decrement('stok', $item->jumlah);

                    MutasiStok::create([
                        'barang_id'      => $barang->id,
                        'user_id'        => auth()->id(),
                        'tipe'           => 'keluar_resep',
                        'jumlah'         => $item->jumlah,
                        'stok_sebelum'   => $stokSebelum,
                        'stok_sesudah'   => $stokSebelum - $item->jumlah,
                        'hpr_sebelum'    => $barang->harga_pokok,
                        'hpr_sesudah'    => $barang->harga_pokok,
                        'referensi_tipe' => 'resep',
                        'referensi_id'   => $resep->id,
                        'keterangan'     => "Resep #{$resep->id}: {$barang->nama}",
                    ]);
                }

                // Potong stok bahan racikan + catat MutasiStok
                foreach ($resep->racikan as $racikan) {
                    foreach ($racikan->bahanRacikan as $bahan) {
                        if (! $bahan->barang_id) continue;

                        $barang      = Barang::pastikanCukup($bahan->barang_id, $bahan->jumlah);
                        $stokSebelum = $barang->stok;
                        $barang->decrement('stok', $bahan->jumlah);

                        MutasiStok::create([
                            'barang_id'      => $barang->id,
                            'user_id'        => auth()->id(),
                            'tipe'           => 'keluar_resep',
                            'jumlah'         => $bahan->jumlah,
                            'stok_sebelum'   => $stokSebelum,
                            'stok_sesudah'   => $stokSebelum - $bahan->jumlah,
                            'hpr_sebelum'    => $barang->harga_pokok,
                            'hpr_sesudah'    => $barang->harga_pokok,
                            'referensi_tipe' => 'resep',
                            'referensi_id'   => $resep->id,
                            'keterangan'     => "Resep #{$resep->id} (racikan {$racikan->nama_racikan}): {$barang->nama}",
                        ]);
                    }
                }

                $resepLocked->update([
                    'is_locked'  => true,
                    'locked_by'  => auth()->id(),
                    'locked_at'  => now(),
                    'status'     => 'siap',
                ]);
            });
        } catch (\DomainException $e) {
            $this->dispatch('notify', ['type' => 'error', 'message' => $e->getMessage()]);
            return;
        }

        unset($this->resepList);
        $this->dispatch('notify', ['type' => 'success', 'message' => 'Resep dikonfirmasi dan stok telah dipotong.']);
    }

    public function batalkanKonfirmasi(int $resepId): void
    {
        $resep = Resep::with([
            'itemResep.barang',
            'racikan.bahanRacikan.barang',
            'kunjungan.invoice',
        ])->find($resepId);

        if (! $resep || ! $resep->is_locked) return;

        if ($resep->kunjungan?->invoice?->status === 'lunas') {
            $this->dispatch('notify', ['type' => 'error',
                'message' => 'Resep tidak dapat dibatalkan karena billing sudah lunas.']);
            return;
        }

        \Illuminate\Support\Facades\DB::transaction(function () use ($resep) {
            // Kembalikan stok + catat MutasiStok utk tiap pengembalian --
            // sebelumnya stok dikembalikan tapi TIDAK ada MutasiStok yang
            // dibuat, jadi Kartu Stok cuma menunjukkan stok berkurang saat
            // konfirmasi tanpa pernah menunjukkan kapan/kenapa naik lagi
            // saat dibatalkan (riwayat auditnya bolong).
            foreach ($resep->itemResep as $item) {
                if (! $item->barang) continue;

                $stokSebelum = $item->barang->stok;
                $item->barang->increment('stok', $item->jumlah);

                MutasiStok::create([
                    'barang_id'      => $item->barang->id,
                    'user_id'        => auth()->id(),
                    'tipe'           => 'penyesuaian_masuk',
                    'jumlah'         => $item->jumlah,
                    'stok_sebelum'   => $stokSebelum,
                    'stok_sesudah'   => $stokSebelum + $item->jumlah,
                    'hpr_sebelum'    => $item->barang->harga_pokok,
                    'hpr_sesudah'    => $item->barang->harga_pokok,
                    'referensi_tipe' => 'resep',
                    'referensi_id'   => $resep->id,
                    'keterangan'     => "Batal konfirmasi Resep #{$resep->id}: {$item->barang->nama}",
                ]);
            }

            foreach ($resep->racikan as $racikan) {
                foreach ($racikan->bahanRacikan as $bahan) {
                    if (! $bahan->barang) continue;

                    $stokSebelum = $bahan->barang->stok;
                    $bahan->barang->increment('stok', $bahan->jumlah);

                    MutasiStok::create([
                        'barang_id'      => $bahan->barang->id,
                        'user_id'        => auth()->id(),
                        'tipe'           => 'penyesuaian_masuk',
                        'jumlah'         => $bahan->jumlah,
                        'stok_sebelum'   => $stokSebelum,
                        'stok_sesudah'   => $stokSebelum + $bahan->jumlah,
                        'hpr_sebelum'    => $bahan->barang->harga_pokok,
                        'hpr_sesudah'    => $bahan->barang->harga_pokok,
                        'referensi_tipe' => 'resep',
                        'referensi_id'   => $resep->id,
                        'keterangan'     => "Batal konfirmasi Resep #{$resep->id} (racikan {$racikan->nama_racikan}): {$bahan->barang->nama}",
                    ]);
                }
            }

            $resep->update([
                'is_locked'  => false,
                'locked_by'  => null,
                'locked_at'  => null,
                'status'     => 'menunggu',
            ]);
        });

        unset($this->resepList);
        $this->dispatch('notify', ['type' => 'success',
            'message' => 'Konfirmasi resep dibatalkan dan stok dikembalikan.']);
    }

    public function render()
    {
        return view('livewire.farmasi.resep-farmasi');
    }
}
