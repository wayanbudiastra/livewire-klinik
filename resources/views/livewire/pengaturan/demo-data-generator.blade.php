<div class="space-y-6">

    {{-- ═══════════════════════════════════════════════════════════ --}}
    {{-- PANEL GENERATE                                             --}}
    {{-- ═══════════════════════════════════════════════════════════ --}}
    <div class="card">
        <div class="card-header">
            <div>
                <h3 class="font-semibold text-slate-800">Generate Transaksi Demo</h3>
                <p class="text-sm text-slate-500 mt-0.5">Data yang dihasilkan identik dengan transaksi nyata — siap tampil di laporan keuangan</p>
            </div>
        </div>

        <div class="card-body space-y-6">

            {{-- Error --}}
            @if($errorMsg)
            <div class="rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700 flex gap-2 items-start">
                <svg class="w-4 h-4 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span>{{ $errorMsg }}</span>
            </div>
            @endif

            {{-- Rentang Tanggal --}}
            <div>
                <label class="label">Rentang Tanggal <span class="text-slate-400 text-xs font-normal">(maks. 10 hari)</span></label>
                <div class="flex items-center gap-3 mt-1">
                    <input type="date" wire:model.live="dari"
                        max="{{ now()->toDateString() }}"
                        class="input w-44">
                    <span class="text-slate-400 text-sm">s/d</span>
                    <input type="date" wire:model.live="sampai"
                        max="{{ now()->toDateString() }}"
                        class="input w-44">
                    @php $est = $this->estimasi @endphp
                    @if(!empty($est))
                        @if(!$est['valid'])
                            <span class="text-xs text-red-600 font-medium">⚠ Melebihi 10 hari</span>
                        @else
                            <span class="text-xs text-slate-500">{{ $est['hari'] }} hari</span>
                        @endif
                    @endif
                </div>
            </div>

            {{-- Jenis Data: PO+GRN & Ritel --}}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                {{-- PO + GRN --}}
                <div class="rounded-lg border border-slate-200 p-4 space-y-3">
                    <label class="flex items-center gap-3 cursor-pointer">
                        <input type="checkbox" wire:model.live="generatePoGrn"
                            class="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                        <span class="font-medium text-slate-700">Generate PO + GRN</span>
                    </label>
                    @if($generatePoGrn)
                    <div class="space-y-1">
                        <label class="text-xs text-slate-500">Target nilai pembelian / hari</label>
                        <div class="flex items-center gap-2">
                            <span class="text-xs text-slate-400">Rp</span>
                            <input type="number" wire:model="targetPoHarian"
                                min="{{ \App\Services\Demo\DemoDataGeneratorService::MIN_TARGET_PO }}"
                                max="{{ \App\Services\Demo\DemoDataGeneratorService::MAX_TARGET_PO }}"
                                step="500000"
                                class="input text-sm w-full"
                                placeholder="10000000">
                        </div>
                        <p class="text-xs text-slate-400">Min Rp 1 juta · Maks Rp 100 juta</p>
                    </div>
                    @endif
                </div>

                {{-- Penjualan Ritel --}}
                <div class="rounded-lg border border-slate-200 p-4 space-y-3">
                    <label class="flex items-center gap-3 cursor-pointer">
                        <input type="checkbox" wire:model.live="generateRitel"
                            class="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                        <span class="font-medium text-slate-700">Generate Penjualan Ritel</span>
                    </label>
                    @if($generateRitel)
                    <div class="space-y-1">
                        <label class="text-xs text-slate-500">Target omzet penjualan / hari</label>
                        <div class="flex items-center gap-2">
                            <span class="text-xs text-slate-400">Rp</span>
                            <input type="number" wire:model="targetRitelHarian"
                                min="{{ \App\Services\Demo\DemoDataGeneratorService::MIN_TARGET_RITEL }}"
                                max="{{ \App\Services\Demo\DemoDataGeneratorService::MAX_TARGET_RITEL }}"
                                step="500000"
                                class="input text-sm w-full"
                                placeholder="5000000">
                        </div>
                        <p class="text-xs text-slate-400">Min Rp 500 rb · Maks Rp 50 juta</p>
                    </div>
                    @endif
                </div>
            </div>

            {{-- Kunjungan Pasien --}}
            <div class="rounded-lg border border-slate-200 p-4 space-y-4">
                <label class="flex items-center gap-3 cursor-pointer">
                    <input type="checkbox" wire:model.live="generateKunjungan"
                        class="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                    <span class="font-medium text-slate-700">Generate Kunjungan Pasien + Billing</span>
                </label>

                @if($generateKunjungan)
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 pl-7">
                    <div class="space-y-1">
                        <label class="text-xs text-slate-500">Jumlah kunjungan per hari</label>
                        <input type="number" wire:model="kunjunganPerHari"
                            min="{{ \App\Services\Demo\DemoDataGeneratorService::MIN_KUNJUNGAN }}"
                            max="{{ \App\Services\Demo\DemoDataGeneratorService::MAX_KUNJUNGAN }}"
                            class="input text-sm w-full"
                            placeholder="10">
                        <p class="text-xs text-slate-400">
                            Min 1 · Maks 50 · 5 pola: ISPA 35%, Kronik 25%, Tindakan 15%, Anak 15%, Nebul 10%
                        </p>
                    </div>

                    <div class="space-y-2">
                        <label class="text-xs text-slate-500">Mix metode pembayaran (%)</label>
                        <div class="grid grid-cols-3 gap-2">
                            <div>
                                <label class="text-xs text-slate-400">Tunai</label>
                                <input type="number" wire:model="mixBayarTunai" min="0" max="100"
                                    class="input text-sm w-full" placeholder="60">
                            </div>
                            <div>
                                <label class="text-xs text-slate-400">Transfer</label>
                                <input type="number" wire:model="mixBayarTransfer" min="0" max="100"
                                    class="input text-sm w-full" placeholder="30">
                            </div>
                            <div>
                                <label class="text-xs text-slate-400">BPJS</label>
                                <input type="number" wire:model="mixBayarBpjs" min="0" max="100"
                                    class="input text-sm w-full" placeholder="10">
                            </div>
                        </div>
                        @php $totalMix = $mixBayarTunai + $mixBayarTransfer + $mixBayarBpjs; @endphp
                        <p class="text-xs {{ $totalMix > 0 ? 'text-slate-400' : 'text-red-500' }}">
                            Total: {{ $totalMix }}% {{ $totalMix > 0 ? '(dialokasikan proporsional)' : '⚠ harus > 0' }}
                        </p>
                    </div>
                </div>

                <div class="pl-7 flex items-center gap-6">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" wire:model="includeResepStok"
                            class="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                        <span class="text-xs text-slate-600">Kurangi stok dari penggunaan resep</span>
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" wire:model="generateBillingJurnal"
                            class="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                        <span class="text-xs text-slate-600">Generate jurnal akuntansi billing</span>
                    </label>
                </div>
                @endif
            </div>

            {{-- Jurnal PO+GRN & Ritel --}}
            <label class="flex items-center gap-3 cursor-pointer">
                <input type="checkbox" wire:model="generateJurnal"
                    class="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                <span class="text-sm text-slate-700">Generate jurnal PO+GRN & Ritel otomatis (double-entry)</span>
            </label>

            {{-- Estimasi --}}
            @if(!empty($est) && $est['valid'])
            <div class="rounded-lg bg-indigo-50 border border-indigo-200 p-4">
                <p class="text-xs font-semibold text-indigo-700 uppercase tracking-wide mb-2">Estimasi Hasil</p>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-3 text-sm">
                    @if($generatePoGrn)
                    <div>
                        <p class="text-xs text-indigo-500">PO + GRN</p>
                        <p class="font-semibold text-indigo-800">{{ $est['jumlah_po'] }} PO + {{ $est['jumlah_gr'] }} GR</p>
                        <p class="text-xs text-indigo-500">≈ Rp {{ number_format($est['total_po'],0,',','.') }}</p>
                    </div>
                    @endif
                    @if($generateRitel)
                    <div>
                        <p class="text-xs text-indigo-500">Penjualan Ritel</p>
                        <p class="font-semibold text-indigo-800">≈ {{ $est['jumlah_trx'] }} transaksi</p>
                        <p class="text-xs text-indigo-500">≈ Rp {{ number_format($est['total_ritel'],0,',','.') }}</p>
                    </div>
                    @endif
                    @if($generateKunjungan)
                    <div>
                        <p class="text-xs text-indigo-500">Kunjungan Pasien</p>
                        <p class="font-semibold text-indigo-800">{{ $est['jumlah_kunjungan'] }} kunjungan</p>
                        <p class="text-xs text-indigo-500">≈ Rp {{ number_format($est['perkiraan_pendapatan'],0,',','.') }}</p>
                    </div>
                    @endif
                </div>
            </div>
            @endif

            {{-- Peringatan Konflik --}}
            @if($konflik && $konflik['ada_konflik'] && !$hasil)
            <div class="rounded-lg bg-amber-50 border border-amber-300 p-4 space-y-2">
                <p class="text-sm font-semibold text-amber-800">⚠ Ditemukan data yang sudah ada di rentang ini</p>
                <div class="text-xs text-amber-700 space-y-0.5">
                    @if($konflik['po'] || $konflik['gr'])
                    <p>• {{ $konflik['po'] }} PO + {{ $konflik['gr'] }} GRN (total Rp {{ number_format($konflik['total_po'],0,',','.') }})</p>
                    @endif
                    @if($konflik['ritel'])
                    <p>• {{ $konflik['ritel'] }} transaksi ritel (total Rp {{ number_format($konflik['total_ritel'],0,',','.') }})</p>
                    @endif
                    @if(isset($konflik['kunjungan']) && $konflik['kunjungan'])
                    <p>• {{ $konflik['kunjungan'] }} kunjungan pasien</p>
                    @endif
                </div>
                <p class="text-xs text-amber-700">Generate akan <strong>menghapus data tersebut</strong> dan menggantinya dengan data baru.</p>
                <div class="flex items-center gap-3 pt-1">
                    <input type="checkbox" wire:model.live="konfirmasiGanti" id="konfirmasiGanti"
                        class="h-4 w-4 rounded border-amber-300 text-amber-600 focus:ring-amber-500">
                    <label for="konfirmasiGanti" class="text-xs font-medium text-amber-700 cursor-pointer">
                        Ya, saya mengerti dan ingin mengganti data lama
                    </label>
                </div>
            </div>
            @endif

            {{-- Tombol Generate --}}
            <div class="flex items-center gap-3">
                @php
                    $adaJenisData = $generatePoGrn || $generateRitel || $generateKunjungan;
                    $mixValid     = !$generateKunjungan || ($mixBayarTunai + $mixBayarTransfer + $mixBayarBpjs) > 0;
                    $bolehGenerate = (!$konflik || !$konflik['ada_konflik'] || $konfirmasiGanti)
                        && $adaJenisData && !empty($est) && $est['valid'] && $mixValid;
                @endphp
                <button
                    wire:click="generate"
                    wire:loading.attr="disabled"
                    wire:loading.class="opacity-75 cursor-wait"
                    @disabled(!$bolehGenerate)
                    class="btn-primary disabled:opacity-40 disabled:cursor-not-allowed flex items-center gap-2">
                    <svg wire:loading wire:target="generate" class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                    </svg>
                    <span wire:loading.remove wire:target="generate">
                        <svg class="w-4 h-4 inline-block mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                        </svg>
                        Generate Data
                    </span>
                    <span wire:loading wire:target="generate">Sedang diproses...</span>
                </button>
                @if($hasil)
                <button wire:click="$set('hasil', null)" class="btn-secondary text-sm">Reset Form</button>
                @endif
            </div>
        </div>
    </div>

    {{-- ═══════════════════════════════════════════════════════════ --}}
    {{-- HASIL GENERATE                                             --}}
    {{-- ═══════════════════════════════════════════════════════════ --}}
    @if($hasil)
    <div class="card border-emerald-200">
        <div class="card-header bg-emerald-50">
            <div class="flex items-center gap-2">
                <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <h3 class="font-semibold text-emerald-800">Generate Selesai</h3>
            </div>
        </div>
        <div class="card-body">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-5">
                {{-- PO+GRN --}}
                <div class="rounded-lg bg-slate-50 border border-slate-200 p-4">
                    <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-1">📦 PO + GRN</p>
                    @if($hasil['po_grn']['jumlah_po'] > 0)
                    <p class="text-lg font-bold text-slate-800">{{ $hasil['po_grn']['jumlah_po'] }} PO · {{ $hasil['po_grn']['jumlah_gr'] }} GR</p>
                    <p class="text-sm text-slate-500">Total Rp {{ number_format($hasil['po_grn']['total_nilai'],0,',','.') }}</p>
                    @else
                    <p class="text-sm text-slate-400">Tidak di-generate</p>
                    @endif
                </div>
                {{-- Ritel --}}
                <div class="rounded-lg bg-slate-50 border border-slate-200 p-4">
                    <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-1">🛒 Penjualan Ritel</p>
                    @if($hasil['ritel']['jumlah_transaksi'] > 0)
                    <p class="text-lg font-bold text-slate-800">{{ $hasil['ritel']['jumlah_transaksi'] }} transaksi</p>
                    <p class="text-sm text-slate-500">Total Rp {{ number_format($hasil['ritel']['total_harga'],0,',','.') }}</p>
                    @else
                    <p class="text-sm text-slate-400">Tidak di-generate</p>
                    @endif
                </div>
                {{-- Kunjungan --}}
                <div class="rounded-lg bg-slate-50 border border-slate-200 p-4">
                    <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-1">🏥 Kunjungan + Billing</p>
                    @if($hasil['kunjungan']['jumlah_kunjungan'] > 0)
                    <p class="text-lg font-bold text-slate-800">{{ $hasil['kunjungan']['jumlah_kunjungan'] }} kunjungan</p>
                    <p class="text-sm text-slate-500">
                        Tindakan: Rp {{ number_format($hasil['kunjungan']['total_tindakan'],0,',','.') }}<br>
                        Obat: Rp {{ number_format($hasil['kunjungan']['total_obat'],0,',','.') }}
                    </p>
                    <p class="text-xs font-medium text-emerald-600 mt-1">Total: Rp {{ number_format($hasil['kunjungan']['total_pendapatan'],0,',','.') }}</p>
                    @else
                    <p class="text-sm text-slate-400">Tidak di-generate</p>
                    @endif
                </div>
                {{-- Jurnal --}}
                <div class="rounded-lg bg-slate-50 border border-slate-200 p-4">
                    <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-1">📒 Jurnal Akuntansi</p>
                    @if($hasil['jurnal']['total'] > 0)
                    <p class="text-lg font-bold text-slate-800">{{ $hasil['jurnal']['total'] }} entri</p>
                    <p class="text-sm text-slate-500">
                        GRN: {{ $hasil['jurnal']['jumlah_grn'] }} ·
                        Ritel: {{ $hasil['jurnal']['jumlah_ritel'] }} ·
                        Billing: {{ $hasil['jurnal']['jumlah_billing'] }}
                    </p>
                    @else
                    <p class="text-sm text-slate-400">Tidak di-generate</p>
                    @endif
                </div>
            </div>

            {{-- Log Per Hari --}}
            @if(count($logs) > 0)
            <div>
                <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">Log Per Hari</p>
                <div class="rounded-lg border border-slate-200 divide-y divide-slate-100 max-h-64 overflow-y-auto text-sm">
                    @foreach(array_unique(array_column($logs, 'tanggal')) as $tgl)
                        @php
                            $logsHari  = array_filter($logs, fn($l) => $l['tanggal'] === $tgl);
                            $poHari    = array_sum(array_column(array_filter($logsHari, fn($l) => $l['tipe'] === 'po_grn'), 'po'));
                            $grHari    = array_sum(array_column(array_filter($logsHari, fn($l) => $l['tipe'] === 'po_grn'), 'gr'));
                            $nilaiPo   = array_sum(array_column(array_filter($logsHari, fn($l) => $l['tipe'] === 'po_grn'), 'nilai'));
                            $trxHari   = array_sum(array_column(array_filter($logsHari, fn($l) => $l['tipe'] === 'ritel'), 'trx'));
                            $nilaiRit  = array_sum(array_column(array_filter($logsHari, fn($l) => $l['tipe'] === 'ritel'), 'harga'));
                            $kunjHari  = array_sum(array_column(array_filter($logsHari, fn($l) => $l['tipe'] === 'kunjungan'), 'jumlah'));
                        @endphp
                        <div class="px-4 py-2.5 flex flex-wrap items-center gap-3">
                            <span class="text-slate-500 w-28 flex-shrink-0">{{ \Carbon\Carbon::parse($tgl)->translatedFormat('d M Y') }}</span>
                            @if($poHari > 0)
                            <span class="text-indigo-600 text-xs">{{ $poHari }} PO + {{ $grHari }} GR
                                <span class="text-slate-400">(Rp {{ number_format($nilaiPo,0,',','.') }})</span>
                            </span>
                            @endif
                            @if($trxHari > 0)
                            <span class="text-emerald-600 text-xs">{{ $trxHari }} trx ritel
                                <span class="text-slate-400">(Rp {{ number_format($nilaiRit,0,',','.') }})</span>
                            </span>
                            @endif
                            @if($kunjHari > 0)
                            <span class="text-blue-600 text-xs">{{ $kunjHari }} kunjungan</span>
                            @endif
                            <svg class="w-4 h-4 text-emerald-500 ml-auto flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                        </div>
                    @endforeach
                </div>
            </div>
            @endif

            {{-- Link ke Laporan --}}
            <div class="flex flex-wrap items-center gap-3 mt-4 pt-4 border-t border-slate-100">
                <a href="{{ route('akuntansi.laba-rugi') }}" class="btn-secondary text-sm">
                    Lihat Laporan Laba Rugi →
                </a>
                <a href="{{ route('akuntansi.neraca') }}" class="btn-secondary text-sm">
                    Lihat Neraca →
                </a>
                <a href="{{ route('akuntansi.jurnal-umum') }}" class="btn-secondary text-sm">
                    Lihat Jurnal Umum →
                </a>
            </div>
        </div>
    </div>
    @endif

    {{-- ═══════════════════════════════════════════════════════════ --}}
    {{-- ZONA BAHAYA — HAPUS DATA DEMO                             --}}
    {{-- ═══════════════════════════════════════════════════════════ --}}
    <div class="card border-red-200" x-data="{ open: false }">
        <div class="card-header bg-red-50 cursor-pointer" @click="open = !open">
            <div class="flex items-center justify-between w-full">
                <div class="flex items-center gap-2">
                    <svg class="w-5 h-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                    <h3 class="font-semibold text-red-700">Zona Berbahaya — Hapus Data Demo</h3>
                </div>
                <svg class="h-4 w-4 text-red-400 transition-transform duration-200" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
            </div>
        </div>

        <div x-show="open" x-transition class="card-body space-y-4">
            <p class="text-sm text-slate-600">
                Hapus <strong>semua</strong> data PO, GRN, Penjualan Ritel, Kunjungan Pasien, Billing, Mutasi Stok, dan Jurnal dalam rentang tanggal yang dipilih.
                Stok barang akan dihitung ulang otomatis setelah penghapusan.
            </p>

            {{-- Hasil Hapus --}}
            @if($hasilHapus)
            <div class="rounded-lg bg-emerald-50 border border-emerald-200 p-4 text-sm space-y-1">
                <p class="font-semibold text-emerald-700">Data berhasil dihapus:</p>
                @if(isset($hasilHapus['deleted_kunjungan']))
                <p class="text-emerald-600">• {{ $hasilHapus['deleted_kunjungan'] }} kunjungan pasien + billing terkait</p>
                @endif
                <p class="text-emerald-600">• {{ $hasilHapus['deleted_po'] ?? 0 }} PO + {{ $hasilHapus['deleted_gr'] ?? 0 }} GR</p>
                <p class="text-emerald-600">• {{ $hasilHapus['deleted_ritel'] ?? 0 }} transaksi ritel</p>
                <p class="text-emerald-600">• {{ $hasilHapus['deleted_jurnal'] ?? 0 }} entri jurnal</p>
                <p class="text-emerald-600">• {{ $hasilHapus['barang_updated'] ?? 0 }} barang stok dihitung ulang</p>
            </div>
            @endif

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="label">Tanggal Mulai</label>
                    <input type="date" wire:model="resetDari" class="input mt-1">
                </div>
                <div>
                    <label class="label">Tanggal Selesai</label>
                    <input type="date" wire:model="resetSampai" class="input mt-1">
                </div>
            </div>

            <div>
                <label class="label text-red-700">Ketik <strong>HAPUS</strong> untuk konfirmasi</label>
                <input type="text" wire:model="konfirmasiHapus"
                    placeholder="HAPUS"
                    class="input mt-1 border-red-300 focus:ring-red-500 focus:border-red-500 uppercase">
            </div>

            <button
                wire:click="hapus"
                wire:loading.attr="disabled"
                wire:loading.class="opacity-75 cursor-wait"
                class="btn-danger flex items-center gap-2"
                @disabled(strtoupper(trim($konfirmasiHapus)) !== 'HAPUS' || !$resetDari || !$resetSampai)>
                <svg wire:loading wire:target="hapus" class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                </svg>
                <span wire:loading.remove wire:target="hapus">Hapus Data Range Ini</span>
                <span wire:loading wire:target="hapus">Menghapus...</span>
            </button>
        </div>
    </div>
</div>
