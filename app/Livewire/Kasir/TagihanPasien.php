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
use App\Services\Akuntansi\BillingJurnalService;
use App\Services\Akuntansi\SharingFeeService;
use App\Services\Harga\TarifResolver;
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
    public array $editDiskon = [];      // [itemId => diskon_value]
    public array $editDiskonTipe = [];  // [itemId => 'nominal'|'persen']

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
    public string $diskonGlobalTipe = 'nominal';  // 'nominal' | 'persen'

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

    // Audit Priority 1: komponen ini sebelumnya cuma dilindungi middleware
    // route (yg sendirinya baru ditambahkan bareng fix ini, lihat web.php),
    // tanpa authorize() sendiri. mount() jadi lapis kedua supaya kalau
    // suatu saat komponen ini di-embed di halaman lain tanpa gate route yg
    // benar, akses tetap tertutup untuk role yg tidak punya billing.view.
    public function mount(): void
    {
        $this->authorize('billing.view');
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
        return Kunjungan::with('pasien', 'dokter', 'poli', 'asesmenPerawat')->find($this->kunjunganId);
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

    /**
     * Tanda vital sudah difinalisasi perawat? Ini gate PEMBAYARAN sejak
     * 2026-11-09 (keputusan kebijakan klinik atas permintaan user): kasir
     * tidak lagi perlu menunggu dokter memfinalisasi SOAP Note, cukup
     * perawat mengunci TTV lewat KunjunganService::finalisasiTtv().
     *
     * Kunjungan yang sudah berstatus 'selesai' tetap lolos -- status itu
     * hanya bisa dicapai lewat selesaiPemeriksaan() yang mewajibkan SOAP
     * Note final, jadi jalur lama (termasuk kunjungan lama yang TTV-nya
     * tidak pernah difinalisasi) tidak ikut terkunci.
     */
    #[Computed]
    public function ttvSudahFinal(): bool
    {
        if (! $this->kunjunganId) return false;

        $kunjungan = Kunjungan::with('asesmenPerawat')->find($this->kunjunganId);
        if (! $kunjungan) return false;

        return $kunjungan->status === 'selesai'
            || (bool) $kunjungan->asesmenPerawat?->is_final;
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

        // Fetch tagihan jika:
        // 1. Pemeriksaan sudah selesai (status = selesai), ATAU
        // 2. Masih dalam pemeriksaan tapi sudah ada tanda vital (AsesmenPerawat)
        //    -- kasir bisa lihat/susun tagihan, tapi pembayaran tetap diblokir
        //    sampai perawat memfinalisasi TTV (lihat prosesPembayaran()).
        $kunjungan = Kunjungan::with('asesmenPerawat')->find($id);
        if ($kunjungan && $kunjungan->status !== 'dibatalkan'
            && ($kunjungan->status === 'selesai' || $kunjungan->asesmenPerawat)) {
            $this->fetchTagihan();
        }
    }

    public function fetchTagihan(): void
    {
        if (! $this->activeSesi) {
            session()->flash('error', 'Buka kas terlebih dahulu.');
            return;
        }

        $kunjungan = Kunjungan::with('asesmenPerawat')->find($this->kunjunganId);
        if (! $kunjungan) return;

        if ($kunjungan->status === 'dibatalkan') {
            session()->flash('error', 'Kunjungan ini sudah dibatalkan.');
            $this->kunjunganId = null;
            return;
        }

        // Kasir boleh lihat tagihan jika:
        // 1. Status selesai (SOAP sudah final), ATAU
        // 2. Masih dalam pemeriksaan tapi sudah ada tanda vital (AsesmenPerawat)
        if ($kunjungan->status !== 'selesai' && ! $kunjungan->asesmenPerawat) {
            session()->flash('error', 'Isi tanda vital terlebih dahulu sebelum dapat membuat tagihan.');
            $this->kunjunganId = null;
            return;
        }

        $invoice = $this->invoiceService->createOrRefresh($kunjungan, $this->activeSesi);

        $this->invoiceId     = $invoice->id;
        $this->editDiskon    = $invoice->items->pluck('diskon_nilai', 'id')
            ->map(fn ($v) => (string) ($v ?? 0))
            ->toArray();
        $this->editDiskonTipe    = $invoice->items->pluck('diskon_tipe', 'id')
            ->map(fn ($v) => (string) ($v ?? 'nominal'))
            ->toArray();
        $this->diskonGlobalNominal = (string) ($invoice->diskon_global_nilai ?? $invoice->diskon_global ?? 0);
        $this->diskonGlobalTipe = (string) ($invoice->diskon_global_tipe ?? 'nominal');

        unset($this->invoice, $this->kunjungan);
    }

    public function updateDiskonItem(int $itemId): void
    {
        $this->authorize('billing.edit');

        $item = InvoiceItem::find($itemId);
        if (! $item || $item->billing->status !== 'belum_bayar') return;

        $tipe  = (string) ($this->editDiskonTipe[$itemId] ?? 'nominal');
        $nilai = (float) ($this->editDiskon[$itemId] ?? 0);

        // Konversi nilai ke nominal Rp untuk disimpan
        $bruto = $item->harga_satuan * $item->qty;
        $diskonNominal = 0;

        if ($tipe === 'persen') {
            // Clamp persentase 0-100%
            $nilai = max(0, min(100, $nilai));
            $diskonNominal = ($bruto * $nilai) / 100;
        } else {
            // Nominal: clamp ke bruto
            $diskonNominal = min($nilai, $bruto);
        }

        $item->update([
            'diskon_tipe'   => $tipe,
            'diskon_nilai'  => $nilai,
            'diskon_item'   => $diskonNominal,
            'subtotal'      => max(0, $bruto - $diskonNominal),
        ]);

        $this->invoiceService->recalcTotal($item->billing);
        unset($this->invoice);
    }

    public function applyDiskonGlobal(): void
    {
        $this->authorize('billing.edit');

        $invoice = Invoice::find($this->invoiceId);
        if (! $invoice || $invoice->status !== 'belum_bayar') return;

        $tipe  = (string) ($this->diskonGlobalTipe ?? 'nominal');
        $nilai = max(0, (float) $this->diskonGlobalNominal);

        // Konversi nilai ke nominal Rp untuk disimpan
        $subtotalItems   = $invoice->items->sum('subtotal');
        $diskonNominal   = 0;

        if ($tipe === 'persen') {
            // Clamp persentase 0-100%
            $nilai = min(100, $nilai);
            $diskonNominal = ($subtotalItems * $nilai) / 100;
        } else {
            // Nominal: tidak di-clamp atas, tapi recalcTotal akan clamp total_tagihan >= 0
            $diskonNominal = $nilai;
        }

        $invoice->update([
            'diskon_global_tipe'  => $tipe,
            'diskon_global_nilai' => $nilai,
            'diskon_global'       => $diskonNominal,
        ]);

        $this->invoiceService->recalcTotal($invoice);
        unset($this->invoice);
    }

    public function removeItem(int $itemId): void
    {
        $this->authorize('billing.edit');

        $item = InvoiceItem::find($itemId);
        if (! $item || $item->jenis === 'obat' || $item->billing->status !== 'belum_bayar') return;

        $billing = $item->billing;
        $item->delete();
        $this->invoiceService->recalcTotal($billing);
        unset($this->invoice);
    }

    public function addManualItem(): void
    {
        $this->authorize('billing.edit');

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
            'diskon_tipe'  => 'nominal',
            'diskon_nilai' => 0,
            'subtotal'     => $subtotal,
        ]);

        $this->editDiskon[$item->id] = '0';
        $this->editDiskonTipe[$item->id] = 'nominal';
        $this->invoiceService->recalcTotal($invoice);

        $this->reset(['manualNama', 'manualQty', 'manualHarga', 'manualSatuan', 'showManualForm']);
        unset($this->invoice);
    }

    public function prosesPembayaran(): void
    {
        $this->authorize('pembayaran.create');

        if (! $this->activeSesi) {
            session()->flash('error', 'Kas tidak aktif. Buka kas terlebih dahulu.');
            return;
        }

        // Tagihan boleh DISUSUN kasir sejak tanda vital terisi (lihat
        // fetchTagihan()). PEMBAYARAN menunggu tanda vital DIFINALISASI
        // perawat (atau kunjungan sudah 'selesai'). Sejak 2026-11-09 gate ini
        // tidak lagi mewajibkan SOAP Note dokter final -- keputusan kebijakan
        // klinik supaya kasir tidak terhenti saat antrean panjang.
        // Tradeoff: pasien bisa lunas sebelum dokter menulis SOAP Note.
        unset($this->ttvSudahFinal);
        if (! $this->ttvSudahFinal) {
            session()->flash('error', 'Tanda vital belum difinalisasi oleh perawat. Pembayaran baru dapat diproses setelah perawat memfinalisasi tanda vital (TTV).');
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
            // Permintaan user: nama bank & no. kartu/referensi dibuat TIDAK
            // wajib (sebelumnya required) -- beberapa metode non-tunai
            // (mis. QRIS/e-wallet) kadang tidak punya nomor referensi yang
            // praktis dicatat kasir saat itu juga.
            $rules['bankNama']       = 'nullable|string|max:100';
            $rules['nomorReferensi'] = 'nullable|string|max:100';
        } elseif ($this->metodePembayaran === 'asuransi') {
            $rules['namaAsuransi'] = 'required|string|max:100';
        }

        $this->validate($rules, [
            'jumlahTunai.min'       => 'Uang yang dibayar kurang dari total tagihan.',
            'namaAsuransi.required' => 'Nama asuransi wajib diisi.',
        ]);

        try {
            DB::transaction(function () use ($invoice) {
                // Kunci ulang & cek ulang status DI DALAM transaksi --
                // sebelumnya status "lunas/dibatalkan" cuma dicek sekali di
                // luar transaksi tanpa lock, jadi klik dobel tombol "Bayar"
                // (atau 2 request bersamaan) bisa sama-sama lolos dan
                // sama-sama mencatat pembayaran penuh utk invoice yang sama
                // (dobel bayar + jurnal & sharing fee ikut dobel).
                $invoiceLocked = Invoice::where('id', $invoice->id)->lockForUpdate()->firstOrFail();

                if (in_array($invoiceLocked->status, ['lunas', 'dibatalkan'], true)) {
                    throw new \DomainException('Tagihan ini sudah diproses (kemungkinan oleh transaksi lain yang berjalan bersamaan). Silakan muat ulang halaman.');
                }

                $jumlah = $invoiceLocked->sisa;

                // Map metode pembayaran ke format PembayaranSplit
                $metodeSplit = match ($this->metodePembayaran) {
                    'tunai'     => 'tunai',
                    'non_tunai' => $this->tipeKartu ?? 'transfer',
                    'asuransi'  => 'asuransi',
                };

                PembayaranSplit::create([
                    'billing_id'    => $invoiceLocked->id,
                    'sesi_kas_id'   => $this->activeSesi->id,
                    'user_id'       => Auth::id(),
                    'metode'        => $metodeSplit,
                    'jumlah'        => $jumlah,
                    'referensi'     => $this->nomorReferensi ?: null,
                    'nama_asuransi' => $this->namaAsuransi ?: null,
                    'tanggal_bayar' => now(),
                ]);

                $this->invoiceService->recalcTotal($invoiceLocked);
                $invoiceLocked->refresh();

                if ($invoiceLocked->sisa <= 0) {
                    $invoiceLocked->update(['status' => 'lunas']);

                    $invoiceFresh = $invoiceLocked->fresh(['items', 'kunjungan.dokter']);
                    app(BillingJurnalService::class)->catatPelunasan($invoiceFresh, [
                        ['metode' => $metodeSplit, 'jumlah' => $jumlah],
                    ]);
                    app(SharingFeeService::class)->catatSharingFee($invoiceFresh);
                    app(SharingFeeService::class)->catatSharingFeePerawat($invoiceFresh);
                }
            });
        } catch (\DomainException $e) {
            session()->flash('error', $e->getMessage());
            unset($this->invoice, $this->activeSesi);
            return;
        }

        $this->reset([
            'jumlahTunai', 'bankNama', 'nomorReferensi',
            'tipeKartu', 'namaAsuransi', 'catatanBayar',
        ]);
        $this->tipeKartu = 'debit';

        unset($this->invoice, $this->activeSesi, $this->kembalian, $this->hasPendingResep);
        session()->flash('success', 'Pembayaran berhasil diproses.');
        $this->dispatch('pembayaranBerhasil');
    }

    public function resetPilihan(): void
    {
        $this->reset([
            'kunjunganId', 'invoiceId', 'searchPasien',
            'editDiskon', 'editDiskonTipe', 'diskonGlobalNominal', 'diskonGlobalTipe',
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
        $q     = strlen($this->searchKomponen) >= 2 ? $this->searchKomponen : null;
        $isWna = $this->kunjungan?->pasien?->tipe_pasien === 'WNA';

        return match ($this->komponenTab) {
            'prosedur' => MasterTindakan::aktif()
                ->when($q, fn ($query) => $query->where('nama', 'like', "%{$q}%"))
                ->orderBy('nama')
                ->limit(40)
                ->get()
                ->map(fn ($i) => [
                    'id'     => $i->id,
                    'nama'   => $i->nama,
                    'harga'  => TarifResolver::pilih(
                        (float) $i->tarif,
                        null,
                        $i->tarif_wna !== null ? (float) $i->tarif_wna : null,
                        false,
                        $isWna,
                    ),
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
                    'harga'  => TarifResolver::pilih(
                        (float) $i->tarif,
                        null,
                        $i->tarif_wna !== null ? (float) $i->tarif_wna : null,
                        false,
                        $isWna,
                    ),
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
                    'harga'  => TarifResolver::pilih(
                        (float) $i->tarif,
                        null,
                        $i->tarif_wna !== null ? (float) $i->tarif_wna : null,
                        false,
                        $isWna,
                    ),
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
        $this->authorize('billing.edit');

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
            'diskon_tipe'  => 'nominal',
            'diskon_nilai' => 0,
            'subtotal'     => $subtotal,
        ]);

        $this->editDiskon[$item->id] = '0';
        $this->editDiskonTipe[$item->id] = 'nominal';
        $this->invoiceService->recalcTotal($invoice);
        unset($this->invoice, $this->komponenList);

        $this->dispatch('notify', type: 'success', message: "{$nama} berhasil ditambahkan ke tagihan.");
    }

    public function render()
    {
        return view('livewire.kasir.tagihan-pasien');
    }
}
