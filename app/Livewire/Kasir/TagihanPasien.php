<?php

namespace App\Livewire\Kasir;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Kunjungan;
use App\Models\Pembayaran;
use App\Models\ShiftKasir;
use App\Services\InvoiceService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Component;

class TagihanPasien extends Component
{
    // Search
    public string $searchPasien = '';
    public array  $searchResults = [];

    // Selected kunjungan & invoice
    public ?int $kunjunganId = null;
    public ?int $invoiceId   = null;

    // Invoice items (editable diskon)
    public array $editDiskon = [];  // [itemId => diskon_value]

    // Add manual item form
    public bool   $showManualForm = false;
    public string $manualNama     = '';
    public string $manualQty      = '1';
    public string $manualHarga    = '';
    public string $manualSatuan   = '';

    // Global discount
    public string $diskonGlobalNominal = '';

    // Payment
    public string $metodePembayaran = 'tunai';
    public string $jumlahTunai      = '';
    public string $bankNama         = '';
    public string $nomorReferensi   = '';
    public string $tipeKartu        = 'debit';
    public string $namaAsuransi     = '';
    public string $catatanBayar     = '';

    protected InvoiceService $invoiceService;

    public function boot(InvoiceService $invoiceService): void
    {
        $this->invoiceService = $invoiceService;
    }

    #[Computed]
    public function activeShift(): ?ShiftKasir
    {
        return ShiftKasir::where('user_id', Auth::id())->open()->latest()->first();
    }

    #[Computed]
    public function kunjungan(): ?Kunjungan
    {
        if (! $this->kunjunganId) return null;
        return Kunjungan::with('pasien', 'dokter', 'poli')->find($this->kunjunganId);
    }

    #[Computed]
    public function invoice(): ?Invoice
    {
        if (! $this->invoiceId) return null;
        return Invoice::with('items')->find($this->invoiceId);
    }

    #[Computed]
    public function kembalian(): float
    {
        if ($this->metodePembayaran !== 'tunai') return 0;
        $bayar = (float) str_replace(',', '', $this->jumlahTunai);
        $sisa  = $this->invoice ? (float) $this->invoice->sisa : 0;
        return max(0, $bayar - $sisa);
    }

    #[Computed]
    public function hasPendingResep(): bool
    {
        if (! $this->kunjunganId) return false;
        return Kunjungan::find($this->kunjunganId)
            ?->resep()
            ->where('is_locked', false)
            ->exists() ?? false;
    }

    public function searchKunjungan(): void
    {
        if (strlen($this->searchPasien) < 2) {
            $this->searchResults = [];
            return;
        }

        $this->searchResults = Kunjungan::with('pasien', 'dokter', 'poli')
            ->whereHas('pasien', function ($q) {
                $q->where('nama', 'like', "%{$this->searchPasien}%")
                  ->orWhere('no_rm', 'like', "%{$this->searchPasien}%");
            })
            ->whereNotIn('status', ['selesai'])
            ->whereDate('tanggal', today())
            ->limit(10)
            ->get()
            ->map(fn ($k) => [
                'id'           => $k->id,
                'nomor_antrean'=> $k->nomor_antrean,
                'pasien_nama'  => $k->pasien->nama,
                'no_rm'        => $k->pasien->no_rm,
                'poli'         => $k->poli->nama ?? '-',
                'dokter'       => $k->dokter->nama ?? '-',
                'tipe'         => $k->tipe_pembayaran,
                'invoice_status' => $k->invoice?->status,
            ])
            ->toArray();
    }

    public function selectKunjungan(int $id): void
    {
        $this->kunjunganId   = $id;
        $this->searchResults = [];
        $this->searchPasien  = '';
        unset($this->kunjungan, $this->invoice);

        $this->fetchTagihan();
    }

    public function fetchTagihan(): void
    {
        if (! $this->activeShift) {
            session()->flash('error', 'Buka shift kasir terlebih dahulu.');
            return;
        }

        $kunjungan = Kunjungan::find($this->kunjunganId);
        if (! $kunjungan) return;

        $invoice = $this->invoiceService->createOrRefresh($kunjungan, $this->activeShift);

        $this->invoiceId     = $invoice->id;
        $this->editDiskon    = $invoice->items->pluck('diskon_item', 'id')
            ->map(fn ($v) => (string) $v)
            ->toArray();
        $this->diskonGlobalNominal = (string) $invoice->diskon_global;

        unset($this->invoice, $this->kunjungan);
    }

    public function updateDiskonItem(int $itemId): void
    {
        $item = InvoiceItem::find($itemId);
        if (! $item || $item->billing->status !== 'belum_bayar') return;

        $diskon = (float) ($this->editDiskon[$itemId] ?? 0);
        $diskon = min($diskon, $item->harga_satuan * $item->qty);

        $item->update([
            'diskon_item' => $diskon,
            'subtotal'    => max(0, ($item->harga_satuan * $item->qty) - $diskon),
        ]);

        $this->invoiceService->recalcTotal($item->billing);
        unset($this->invoice);
    }

    public function applyDiskonGlobal(): void
    {
        $invoice = Invoice::find($this->invoiceId);
        if (! $invoice || $invoice->status !== 'belum_bayar') return;

        $diskon = max(0, (float) $this->diskonGlobalNominal);
        $invoice->update(['diskon_global' => $diskon]);
        $this->invoiceService->recalcTotal($invoice);
        unset($this->invoice);
    }

    public function removeItem(int $itemId): void
    {
        $item = InvoiceItem::find($itemId);
        if (! $item || $item->jenis === 'obat' || $item->billing->status !== 'belum_bayar') return;

        $billing = $item->billing;
        $item->delete();
        $this->invoiceService->recalcTotal($billing);
        unset($this->invoice);
    }

    public function addManualItem(): void
    {
        $this->validate([
            'manualNama'  => 'required|string|max:255',
            'manualQty'   => 'required|numeric|min:0.01',
            'manualHarga' => 'required|numeric|min:0',
        ], [
            'manualNama.required'  => 'Nama item wajib diisi.',
            'manualQty.required'   => 'Qty wajib diisi.',
            'manualHarga.required' => 'Harga wajib diisi.',
        ]);

        $invoice = Invoice::find($this->invoiceId);
        if (! $invoice || $invoice->status !== 'belum_bayar') return;

        $subtotal = (float) $this->manualHarga * (float) $this->manualQty;

        $item = $invoice->items()->create([
            'jenis'        => 'manual',
            'ref_id'       => null,
            'nama_item'    => $this->manualNama,
            'qty'          => $this->manualQty,
            'satuan'       => $this->manualSatuan ?: null,
            'harga_satuan' => $this->manualHarga,
            'diskon_item'  => 0,
            'subtotal'     => $subtotal,
        ]);

        $this->editDiskon[$item->id] = '0';
        $this->invoiceService->recalcTotal($invoice);

        $this->reset(['manualNama', 'manualQty', 'manualHarga', 'manualSatuan', 'showManualForm']);
        unset($this->invoice);
    }

    public function prosesPembayaran(): void
    {
        if (! $this->activeShift) {
            session()->flash('error', 'Shift kasir tidak aktif.');
            return;
        }

        if ($this->hasPendingResep) {
            session()->flash('error', 'Masih ada resep obat yang belum dikonfirmasi apoteker. Selesaikan dulu sebelum proses pembayaran.');
            return;
        }

        $invoice = Invoice::with('items')->find($this->invoiceId);
        if (! $invoice || $invoice->status === 'lunas') return;

        $rules = ['metodePembayaran' => 'required|in:tunai,non_tunai,asuransi'];

        if ($this->metodePembayaran === 'tunai') {
            $rules['jumlahTunai'] = 'required|numeric|min:' . $invoice->sisa;
        } elseif ($this->metodePembayaran === 'non_tunai') {
            $rules['bankNama']       = 'required|string|max:100';
            $rules['nomorReferensi'] = 'required|string|max:100';
        } elseif ($this->metodePembayaran === 'asuransi') {
            $rules['namaAsuransi'] = 'required|string|max:100';
        }

        $this->validate($rules, [
            'jumlahTunai.min'         => 'Uang yang dibayar kurang dari total tagihan.',
            'bankNama.required'       => 'Nama bank wajib diisi.',
            'nomorReferensi.required' => 'Nomor referensi wajib diisi.',
            'namaAsuransi.required'   => 'Nama asuransi wajib diisi.',
        ]);

        DB::transaction(function () use ($invoice) {
            $jumlah = match ($this->metodePembayaran) {
                'tunai'     => $invoice->sisa,
                'non_tunai' => $invoice->sisa,
                'asuransi'  => $invoice->sisa,
            };

            Pembayaran::create([
                'billing_id'       => $invoice->id,
                'shift_id'         => $this->activeShift->id,
                'metode'           => $this->metodePembayaran,
                'jumlah'           => $jumlah,
                'bank_nama'        => $this->bankNama ?: null,
                'nomor_referensi'  => $this->nomorReferensi ?: null,
                'tipe_kartu'       => $this->tipeKartu ?: null,
                'nama_asuransi'    => $this->namaAsuransi ?: null,
                'catatan'          => $this->catatanBayar ?: null,
                'created_at'       => now(),
            ]);

            // Update shift totals
            $shift = $this->activeShift;
            if ($this->metodePembayaran === 'tunai') {
                $shift->increment('total_tunai', $jumlah);
            } elseif ($this->metodePembayaran === 'non_tunai') {
                $shift->increment('total_nontunai', $jumlah);
            } else {
                $shift->increment('total_piutang', $jumlah);
            }

            $this->invoiceService->recalcTotal($invoice);
            $invoice->refresh();

            if ($invoice->sisa <= 0) {
                $invoice->update(['status' => 'lunas']);
            }
        });

        $this->reset([
            'jumlahTunai', 'bankNama', 'nomorReferensi',
            'tipeKartu', 'namaAsuransi', 'catatanBayar',
        ]);
        $this->tipeKartu = 'debit';

        unset($this->invoice, $this->activeShift, $this->kembalian, $this->hasPendingResep);
        session()->flash('success', 'Pembayaran berhasil diproses.');
    }

    public function resetPilihan(): void
    {
        $this->reset([
            'kunjunganId', 'invoiceId', 'searchPasien',
            'editDiskon', 'diskonGlobalNominal',
            'metodePembayaran', 'jumlahTunai', 'bankNama',
            'nomorReferensi', 'namaAsuransi', 'catatanBayar',
        ]);
        $this->metodePembayaran = 'tunai';
        $this->tipeKartu        = 'debit';
        unset($this->kunjungan, $this->invoice, $this->kembalian, $this->hasPendingResep);
    }

    public function render()
    {
        return view('livewire.kasir.tagihan-pasien');
    }
}
