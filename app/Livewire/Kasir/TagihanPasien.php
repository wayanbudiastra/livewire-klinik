<?php

namespace App\Livewire\Kasir;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\ItemPenunjang;
use App\Models\Kunjungan;
use App\Models\MasterTindakan;
use App\Models\PeralatanMedis;
use App\Models\PembayaranSplit;
use App\Models\SesiKas;
use App\Services\InvoiceService;
use App\Services\Kasir\SesiKasService;
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

    // Add komponen form
    public bool   $showKomponenForm = false;
    public string $komponenTab      = 'prosedur';
    public string $searchKomponen   = '';

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
    protected SesiKasService $sesiKasService;

    public function boot(InvoiceService $invoiceService, SesiKasService $sesiKasService): void
    {
        $this->invoiceService = $invoiceService;
        $this->sesiKasService = $sesiKasService;
    }

    #[Computed]
    public function activeSesi(): ?SesiKas
    {
        return $this->sesiKasService->getSesiAktif(Auth::id());
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
        $k = Kunjungan::find($this->kunjunganId);
        if (! $k) return false;
        return $k->resep()->where('is_locked', false)->exists();
    }

    #[Computed]
    public function daftarHariIni(): array
    {
        return Kunjungan::with('pasien', 'dokter.user', 'poli', 'invoice')
            ->whereNotIn('status', ['dibatalkan'])
            ->whereDate('tanggal', today())
            ->whereDoesntHave('invoice', fn ($q) => $q->where('status', 'lunas'))
            ->orderByRaw("FIELD(status,'selesai','dalam_pemeriksaan','menunggu')")
            ->get()
            ->map(fn ($k) => [
                'id'             => $k->id,
                'nomor_antrean'  => $k->nomor_antrean,
                'pasien_nama'    => $k->pasien->nama,
                'no_rm'          => $k->pasien->nomor_rm,
                'poli'           => $k->poli?->nama ?? '-',
                'dokter'         => $k->dokter?->user?->nama ?? '-',
                'tipe'           => $k->tipe_pembayaran,
                'tanggal'        => $k->tanggal->format('d/m/Y'),
                'status'         => $k->status,
                'invoice_status' => optional($k->invoice)->status,
            ])
            ->toArray();
    }

    // Auto-trigger search setiap kali $searchPasien berubah (live debounce)
    public function updatedSearchPasien(): void
    {
        $this->searchKunjungan();
    }

    public function searchKunjungan(): void
    {
        if (strlen($this->searchPasien) < 2) {
            $this->searchResults = [];
            return;
        }

        // Tampilkan semua kunjungan terdaftar (kecuali dibatalkan & sudah lunas)
        // dalam 30 hari terakhir, kasir bisa lihat antrian lengkap
        $this->searchResults = Kunjungan::with('pasien', 'dokter.user', 'poli', 'invoice')
            ->whereHas('pasien', function ($q) {
                $q->where('nama', 'like', "%{$this->searchPasien}%")
                  ->orWhere('nomor_rm', 'like', "%{$this->searchPasien}%");
            })
            ->whereNotIn('status', ['dibatalkan'])
            ->where('tanggal', '>=', now()->subDays(30))
            ->whereDoesntHave('invoice', fn ($q) => $q->where('status', 'lunas'))
            ->orderByDesc('tanggal')
            ->limit(10)
            ->get()
            ->map(fn ($k) => [
                'id'             => $k->id,
                'nomor_antrean'  => $k->nomor_antrean,
                'pasien_nama'    => $k->pasien->nama,
                'no_rm'          => $k->pasien->nomor_rm,
                'poli'           => $k->poli?->nama ?? '-',
                'dokter'         => $k->dokter?->user?->nama ?? '-',
                'tipe'           => $k->tipe_pembayaran,
                'tanggal'        => $k->tanggal->format('d/m/Y'),
                'status'         => $k->status,
                'invoice_status' => optional($k->invoice)->status,
            ])
            ->toArray();
    }

    public function selectKunjungan(int $id): void
    {
        $this->kunjunganId   = $id;
        $this->searchResults = [];
        $this->searchPasien  = '';
        unset($this->kunjungan, $this->invoice, $this->hasPendingResep);

        // Hanya fetch tagihan jika pemeriksaan sudah selesai
        $kunjungan = Kunjungan::find($id);
        if ($kunjungan && $kunjungan->status === 'selesai') {
            $this->fetchTagihan();
        }
    }

    public function fetchTagihan(): void
    {
        if (! $this->activeSesi) {
            session()->flash('error', 'Buka kas terlebih dahulu.');
            return;
        }

        $kunjungan = Kunjungan::find($this->kunjunganId);
        if (! $kunjungan) return;

        if ($kunjungan->status !== 'selesai') {
            session()->flash('error', 'Pemeriksaan pasien belum selesai. Tagihan hanya dapat dibuat setelah dokter menyelesaikan pemeriksaan.');
            $this->kunjunganId = null;
            return;
        }

        $invoice = $this->invoiceService->createOrRefresh($kunjungan, $this->activeSesi);

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
        if (! $this->activeSesi) {
            session()->flash('error', 'Kas tidak aktif. Buka kas terlebih dahulu.');
            return;
        }

        $kunjungan = Kunjungan::find($this->kunjunganId);
        if (! $kunjungan || $kunjungan->status !== 'selesai') {
            session()->flash('error', 'Pemeriksaan pasien belum selesai.');
            return;
        }

        if ($this->hasPendingResep) {
            session()->flash('error', 'Masih ada resep obat yang belum dikonfirmasi apoteker. Selesaikan dulu sebelum proses pembayaran.');
            return;
        }

        $invoice = Invoice::with('items')->find($this->invoiceId);
        if (! $invoice || in_array($invoice->status, ['lunas', 'dibatalkan'])) return;

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
            $jumlah = $invoice->sisa;

            // Map metode pembayaran ke format PembayaranSplit
            $metodeSplit = match ($this->metodePembayaran) {
                'tunai'     => 'tunai',
                'non_tunai' => $this->tipeKartu ?? 'transfer',
                'asuransi'  => 'asuransi',
            };

            PembayaranSplit::create([
                'billing_id'    => $invoice->id,
                'sesi_kas_id'   => $this->activeSesi->id,
                'user_id'       => Auth::id(),
                'metode'        => $metodeSplit,
                'jumlah'        => $jumlah,
                'referensi'     => $this->nomorReferensi ?: null,
                'nama_asuransi' => $this->namaAsuransi ?: null,
                'tanggal_bayar' => now(),
            ]);

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

        unset($this->invoice, $this->activeSesi, $this->kembalian, $this->hasPendingResep);
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
        unset($this->kunjungan, $this->invoice, $this->kembalian, $this->hasPendingResep, $this->activeSesi);
    }

    // ── Tambah Komponen ──────────────────────────────────────────

    #[Computed]
    public function komponenList(): array
    {
        $q = strlen($this->searchKomponen) >= 2 ? $this->searchKomponen : null;

        return match ($this->komponenTab) {
            'prosedur' => MasterTindakan::aktif()
                ->when($q, fn ($query) => $query->where('nama', 'like', "%{$q}%"))
                ->orderBy('nama')
                ->limit(40)
                ->get()
                ->map(fn ($i) => [
                    'id'     => $i->id,
                    'nama'   => $i->nama,
                    'harga'  => (float) $i->tarif,
                    'satuan' => 'tindakan',
                    'info'   => $i->kategori ?? '',
                ])
                ->toArray(),

            'peralatan' => PeralatanMedis::where('is_active', true)
                ->when($q, fn ($query) => $query->where('nama', 'like', "%{$q}%")
                                                ->orWhere('kode', 'like', "%{$q}%"))
                ->orderBy('nama')
                ->limit(40)
                ->get()
                ->map(fn ($i) => [
                    'id'     => $i->id,
                    'nama'   => $i->nama,
                    'harga'  => 0,
                    'satuan' => 'penggunaan',
                    'info'   => trim(($i->merk ?? '') . ($i->status ? ' · ' . ucfirst($i->status) : '')),
                ])
                ->toArray(),

            'lab' => ItemPenunjang::aktif()->lab()
                ->when($q, fn ($query) => $query->where('nama', 'like', "%{$q}%")
                                                ->orWhere('kode', 'like', "%{$q}%"))
                ->orderBy('nama')
                ->limit(40)
                ->get()
                ->map(fn ($i) => [
                    'id'     => $i->id,
                    'nama'   => $i->nama,
                    'harga'  => (float) $i->tarif,
                    'satuan' => 'pemeriksaan',
                    'info'   => '',
                ])
                ->toArray(),

            'radiologi' => ItemPenunjang::aktif()->radiologi()
                ->when($q, fn ($query) => $query->where('nama', 'like', "%{$q}%")
                                                ->orWhere('kode', 'like', "%{$q}%"))
                ->orderBy('nama')
                ->limit(40)
                ->get()
                ->map(fn ($i) => [
                    'id'     => $i->id,
                    'nama'   => $i->nama,
                    'harga'  => (float) $i->tarif,
                    'satuan' => 'pemeriksaan',
                    'info'   => '',
                ])
                ->toArray(),

            default => [],
        };
    }

    public function switchKomponenTab(string $tab): void
    {
        $this->komponenTab    = $tab;
        $this->searchKomponen = '';
        unset($this->komponenList);
    }

    public function addKomponenItem(int $refId, string $nama, float $harga, string $satuan, int $qty): void
    {
        $invoice = Invoice::find($this->invoiceId);
        if (! $invoice || $invoice->status !== 'belum_bayar') return;

        $qty      = max(1, $qty);
        $subtotal = $harga * $qty;

        $jenis = match ($this->komponenTab) {
            'prosedur'  => 'tindakan',
            'peralatan' => 'alkes',
            default     => 'penunjang',
        };

        $item = $invoice->items()->create([
            'jenis'        => $jenis,
            'ref_id'       => null,
            'nama_item'    => $nama,
            'qty'          => $qty,
            'satuan'       => $satuan,
            'harga_satuan' => $harga,
            'diskon_item'  => 0,
            'subtotal'     => $subtotal,
        ]);

        $this->editDiskon[$item->id] = '0';
        $this->invoiceService->recalcTotal($invoice);
        unset($this->invoice, $this->komponenList);

        $this->dispatch('notify', type: 'success', message: "{$nama} berhasil ditambahkan ke tagihan.");
    }

    public function render()
    {
        return view('livewire.kasir.tagihan-pasien');
    }
}
