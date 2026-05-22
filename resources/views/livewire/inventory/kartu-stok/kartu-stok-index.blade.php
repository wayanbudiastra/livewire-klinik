<div class="space-y-5">

    {{-- Panel Filter --}}
    <div class="card">
        <div class="card-header">
            <h3 class="text-sm font-semibold dark:text-white">Filter Kartu Stok</h3>
        </div>
        <div class="card-body space-y-4">

            {{-- Pilih Barang --}}
            <div class="form-group relative">
                <label class="form-label dark:text-gray-300">Barang <span class="text-red-500">*</span></label>
                <input wire:model.live.debounce.400ms="searchBarang" type="text"
                       placeholder="Cari nama atau kode barang..."
                       class="form-input dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200"/>
                @error('selectedBarangId') <p class="form-error">{{ $message }}</p> @enderror

                {{-- Dropdown suggestions --}}
                @if ($this->barangSuggestions->isNotEmpty() && !$selectedBarangId)
                <div class="absolute z-20 top-full left-0 right-0 mt-1 bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-600 shadow-lg max-h-60 overflow-y-auto">
                    @foreach ($this->barangSuggestions as $b)
                    <button type="button" wire:click="pilihBarang({{ $b->id }}, '{{ addslashes($b->nama) }}')"
                            class="w-full text-left px-4 py-3 hover:bg-blue-50 dark:hover:bg-gray-700 border-b border-gray-100 dark:border-gray-700 last:border-0 transition-colors">
                        <div class="flex items-center justify-between">
                            <div>
                                <span class="font-medium text-sm text-gray-900 dark:text-gray-100">{{ $b->nama }}</span>
                                <span class="text-xs font-mono text-gray-400 ml-2">{{ $b->kode }}</span>
                            </div>
                            <div class="flex items-center gap-2 text-xs">
                                <span class="text-gray-500">{{ $b->satuan }}</span>
                                <span @class(['font-semibold',
                                    'text-red-600'   => $b->stok === 0,
                                    'text-amber-600' => $b->stok <= $b->stok_minimum && $b->stok > 0,
                                    'text-gray-700 dark:text-gray-300' => $b->stok > $b->stok_minimum,
                                ])>Stok: {{ $b->stok }}</span>
                            </div>
                        </div>
                    </button>
                    @endforeach
                </div>
                @endif

                {{-- Barang terpilih --}}
                @if ($selectedBarangId)
                <div class="mt-2 flex items-center justify-between rounded-lg bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-700 px-4 py-2">
                    <span class="text-sm font-medium text-blue-700 dark:text-blue-400">✓ {{ $selectedBarangNama }}</span>
                    <button wire:click="resetPilihan" class="text-xs text-red-400 hover:text-red-600">Ganti</button>
                </div>
                @endif
            </div>

            {{-- Tanggal & Filter Tipe --}}
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div class="form-group">
                    <label class="form-label dark:text-gray-300">Dari Tanggal</label>
                    <input wire:model="tanggalMulai" type="date"
                           class="form-input dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200"/>
                    @error('tanggalMulai') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                <div class="form-group">
                    <label class="form-label dark:text-gray-300">Sampai Tanggal</label>
                    <input wire:model="tanggalAkhir" type="date"
                           class="form-input dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200"/>
                    @error('tanggalAkhir') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                <div class="form-group">
                    <label class="form-label dark:text-gray-300">Tipe Mutasi</label>
                    <select wire:model="tipeMutasi"
                            class="form-select dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200">
                        @foreach ($this->getTipeOptions() as $val => $lbl)
                            <option value="{{ $val }}">{{ $lbl }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="flex justify-end">
                <button wire:click="lihatKartu" class="btn-primary" wire:loading.attr="disabled">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/>
                    </svg>
                    <span wire:loading.remove wire:target="lihatKartu">Tampilkan Kartu Stok</span>
                    <span wire:loading wire:target="lihatKartu" class="flex items-center gap-2">
                        <div class="spinner h-4 w-4 border-white border-t-transparent"></div> Memuat...
                    </span>
                </button>
            </div>
        </div>
    </div>

    {{-- Hasil Kartu Stok --}}
    @if ($sudahCari && !empty($kartuData))
    @php
        $barang      = $kartuData['barang'];
        $rows        = $kartuData['rows'];
        $saldoAwal   = $kartuData['saldo_awal'];
        $hprAwal     = $kartuData['hpr_awal'];
        $totalMasuk  = $kartuData['total_masuk'];
        $totalKeluar = $kartuData['total_keluar'];
        $saldoAkhir  = $kartuData['saldo_akhir'];
    @endphp

    {{-- Info Barang --}}
    <div class="card">
        <div class="card-body">
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 text-sm">
                <div>
                    <p class="text-gray-500 dark:text-gray-400 text-xs uppercase tracking-wide mb-1">Kode</p>
                    <p class="font-mono font-semibold text-gray-800 dark:text-gray-200">{{ $barang->kode }}</p>
                </div>
                <div class="sm:col-span-2">
                    <p class="text-gray-500 dark:text-gray-400 text-xs uppercase tracking-wide mb-1">Nama Barang</p>
                    <p class="font-semibold text-gray-900 dark:text-white">{{ $barang->nama }}</p>
                    @if($barang->nama_generik)
                    <p class="text-xs text-gray-400 italic">{{ $barang->nama_generik }}</p>
                    @endif
                </div>
                <div>
                    <p class="text-gray-500 dark:text-gray-400 text-xs uppercase tracking-wide mb-1">Satuan</p>
                    <p class="font-medium text-gray-800 dark:text-gray-200">{{ $barang->satuan }}</p>
                </div>
                <div>
                    <p class="text-gray-500 dark:text-gray-400 text-xs uppercase tracking-wide mb-1">Stok Saat Ini</p>
                    <p class="font-bold text-lg {{ $barang->stok === 0 ? 'text-red-600' : ($barang->stok <= $barang->stok_minimum ? 'text-amber-600' : 'text-emerald-600') }}">
                        {{ number_format($barang->stok) }}
                    </p>
                </div>
                <div>
                    <p class="text-gray-500 dark:text-gray-400 text-xs uppercase tracking-wide mb-1">HPR Saat Ini</p>
                    <p class="font-semibold text-gray-800 dark:text-gray-200">Rp {{ number_format($barang->harga_pokok, 0, ',', '.') }}</p>
                </div>
                <div>
                    <p class="text-gray-500 dark:text-gray-400 text-xs uppercase tracking-wide mb-1">Supplier Utama</p>
                    <p class="text-gray-700 dark:text-gray-300">{{ $barang->supplierUtama?->nama ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-gray-500 dark:text-gray-400 text-xs uppercase tracking-wide mb-1">Periode</p>
                    <p class="text-gray-700 dark:text-gray-300 text-xs">
                        {{ \Carbon\Carbon::parse($tanggalMulai)->format('d/m/Y') }} —
                        {{ \Carbon\Carbon::parse($tanggalAkhir)->format('d/m/Y') }}
                    </p>
                </div>
            </div>
        </div>
    </div>

    {{-- Summary Bar --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
        <div class="card p-4 text-center">
            <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">Saldo Awal</p>
            <p class="text-xl font-bold text-gray-800 dark:text-gray-200">{{ number_format($saldoAwal) }}</p>
        </div>
        <div class="card p-4 text-center border-l-4 border-emerald-400">
            <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">Total Masuk</p>
            <p class="text-xl font-bold text-emerald-600">+{{ number_format($totalMasuk) }}</p>
        </div>
        <div class="card p-4 text-center border-l-4 border-red-400">
            <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">Total Keluar</p>
            <p class="text-xl font-bold text-red-600">-{{ number_format($totalKeluar) }}</p>
        </div>
        <div class="card p-4 text-center border-l-4 border-[#0a3d62]">
            <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">Saldo Akhir</p>
            <p class="text-xl font-bold text-[#0a3d62] dark:text-blue-400">{{ number_format($saldoAkhir) }}</p>
        </div>
    </div>

    {{-- Tabel Kartu Stok --}}
    <div class="card">
        <div class="card-header">
            <h3 class="text-sm font-semibold dark:text-white">Detail Mutasi Stok</h3>
            <div class="flex gap-2">
                <a href="{{ route('inventory.kartu-stok.export-pdf', [
                    'barang_id'     => $selectedBarangId,
                    'tanggal_mulai' => $tanggalMulai,
                    'tanggal_akhir' => $tanggalAkhir,
                    'tipe'          => $tipeMutasi,
                ]) }}" target="_blank" class="btn-danger btn-sm">
                    PDF
                </a>
                <a href="{{ route('inventory.kartu-stok.export-excel', [
                    'barang_id'     => $selectedBarangId,
                    'tanggal_mulai' => $tanggalMulai,
                    'tanggal_akhir' => $tanggalAkhir,
                    'tipe'          => $tipeMutasi,
                ]) }}" class="btn-success btn-sm">
                    Excel
                </a>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-wrapper rounded-none border-0 overflow-x-auto">
                <table class="table" style="min-width:800px">
                    <thead>
                        <tr>
                            <th>Tanggal</th>
                            <th>Waktu</th>
                            <th>Tipe Mutasi</th>
                            <th>Keterangan</th>
                            <th class="text-center text-emerald-600">Masuk</th>
                            <th class="text-center text-red-600">Keluar</th>
                            <th class="text-center font-bold">Saldo</th>
                            <th class="text-right">HPR (Rp)</th>
                            <th>Dicatat Oleh</th>
                        </tr>
                    </thead>
                    <tbody>
                        {{-- Baris Saldo Awal --}}
                        <tr class="bg-blue-50/50 dark:bg-blue-900/10 font-medium">
                            <td class="text-xs text-blue-700 dark:text-blue-400">
                                {{ \Carbon\Carbon::parse($tanggalMulai)->format('d/m/Y') }}
                            </td>
                            <td>—</td>
                            <td><span class="badge-info">Saldo Awal</span></td>
                            <td class="text-xs text-gray-500">Saldo awal periode</td>
                            <td class="text-center">—</td>
                            <td class="text-center">—</td>
                            <td class="text-center font-bold text-gray-900 dark:text-white">{{ number_format($saldoAwal) }}</td>
                            <td class="text-right text-xs text-gray-500">{{ number_format($hprAwal, 0, ',', '.') }}</td>
                            <td>—</td>
                        </tr>

                        @forelse ($rows as $row)
                        <tr @class([
                            'bg-red-50/30 dark:bg-red-900/10'       => $row['is_anomali'],
                            'hover:bg-emerald-50/30 dark:hover:bg-emerald-900/10' => $row['masuk'] > 0,
                        ])>
                            <td class="text-sm text-gray-700 dark:text-gray-300">{{ $row['tanggal'] }}</td>
                            <td class="text-xs text-gray-400">{{ $row['waktu'] }}</td>
                            <td>
                                <span @class([
                                    'badge',
                                    'badge-success' => in_array($row['tipe'], ['masuk_pembelian','penyesuaian_masuk']),
                                    'badge-danger'  => in_array($row['tipe'], ['expired','retur_ke_supplier']),
                                    'badge-warning' => !in_array($row['tipe'], ['masuk_pembelian','penyesuaian_masuk','expired','retur_ke_supplier']),
                                ])>
                                    {{ \App\Services\Inventory\KartuStokService::getTipeLabel($row['tipe']) }}
                                </span>
                            </td>
                            <td class="text-xs text-gray-600 dark:text-gray-400 max-w-xs truncate">
                                {{ $row['keterangan'] ?? ($row['referensi_tipe'] ? "{$row['referensi_tipe']} #{$row['referensi_id']}" : '—') }}
                            </td>
                            <td class="text-center">
                                @if($row['masuk'] > 0)
                                <span class="font-semibold text-emerald-600">+{{ number_format($row['masuk']) }}</span>
                                @else
                                <span class="text-gray-300">—</span>
                                @endif
                            </td>
                            <td class="text-center">
                                @if($row['keluar'] > 0)
                                <span class="font-semibold text-red-600">-{{ number_format($row['keluar']) }}</span>
                                @else
                                <span class="text-gray-300">—</span>
                                @endif
                            </td>
                            <td class="text-center">
                                <span @class(['font-bold text-sm',
                                    'text-red-600'              => $row['is_anomali'],
                                    'text-gray-900 dark:text-white' => !$row['is_anomali'],
                                ])>
                                    {{ number_format($row['saldo']) }}
                                    @if($row['is_anomali'])
                                    <span class="text-xs text-red-500 block">⚠ Anomali</span>
                                    @endif
                                </span>
                            </td>
                            <td class="text-right text-xs text-gray-500">
                                {{ number_format($row['hpr'], 0, ',', '.') }}
                            </td>
                            <td class="text-xs text-gray-500">{{ $row['user_nama'] }}</td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="9">
                                <div class="empty-state py-8">
                                    <p class="empty-state-text">Tidak ada mutasi stok dalam periode ini</p>
                                </div>
                            </td>
                        </tr>
                        @endforelse

                        {{-- Baris Total --}}
                        @if(count($rows) > 0)
                        <tr class="bg-gray-50 dark:bg-gray-700/50 font-semibold border-t-2 border-gray-300 dark:border-gray-500">
                            <td colspan="4" class="text-right text-gray-700 dark:text-gray-300 px-4 py-3">
                                Total Periode
                            </td>
                            <td class="text-center text-emerald-600 px-4 py-3">
                                +{{ number_format($totalMasuk) }}
                            </td>
                            <td class="text-center text-red-600 px-4 py-3">
                                -{{ number_format($totalKeluar) }}
                            </td>
                            <td class="text-center text-gray-900 dark:text-white font-black px-4 py-3">
                                {{ number_format($saldoAkhir) }}
                            </td>
                            <td colspan="2"></td>
                        </tr>
                        @endif
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @elseif($sudahCari)
    <div class="card">
        <div class="card-body">
            <div class="empty-state py-10">
                <p class="empty-state-text">Tidak ada data mutasi untuk barang ini dalam periode yang dipilih</p>
            </div>
        </div>
    </div>
    @endif

</div>
