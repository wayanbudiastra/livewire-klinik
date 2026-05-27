<div class="space-y-5">
    {{-- Header PO --}}
    <div class="card">
        <div class="card-header"><h3 class="text-sm font-semibold dark:text-white">Header Purchase Order</h3></div>
        <div class="card-body grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="form-group">
                <label class="form-label dark:text-gray-300">Supplier <span class="text-red-500">*</span></label>
                <select wire:model.live="supplierId" class="form-select dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200">
                    <option value="0">— Pilih Supplier —</option>
                    @foreach($suppliers as $s)
                        <option value="{{ $s->id }}">{{ $s->kode }} — {{ $s->nama }}</option>
                    @endforeach
                </select>
                @error('supplierId') <p class="form-error">{{ $message }}</p> @enderror
            </div>
            <div class="form-group">
                <label class="form-label dark:text-gray-300">Tanggal PO <span class="text-red-500">*</span></label>
                <input wire:model="tanggalPo" type="date" class="form-input dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200"/>
            </div>
            <div class="form-group">
                <label class="form-label dark:text-gray-300">Estimasi Tiba</label>
                <input wire:model="tanggalKirimEstimasi" type="date" class="form-input dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200"/>
            </div>
            <div class="form-group">
                <label class="form-label dark:text-gray-300">Catatan</label>
                <input wire:model="catatan" type="text" class="form-input dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200"/>
            </div>
        </div>
    </div>

    {{-- Mapping Grid: Barang Terikat Supplier --}}
    @if($showMappingGrid && !empty($barangMapping))
    <div class="card">
        <div class="card-header">
            <div>
                <h3 class="text-sm font-semibold dark:text-white">
                    Barang Terikat Supplier
                    <span class="badge badge-primary ml-1">{{ count($barangMapping) }} item</span>
                </h3>
                <p class="text-xs text-gray-400 mt-0.5">Centang barang yang ingin dipesan, isi jumlah, lalu klik tambah.</p>
            </div>
            <button type="button" wire:click="tambahDariMapping" class="btn-primary btn-sm"
                @disabled(empty($selectedMapping))>
                + Tambahkan ke PO ({{ count($selectedMapping) }})
            </button>
        </div>
        <div class="card-body p-0 max-h-96 overflow-y-auto">
            <table class="table text-sm">
                <thead class="sticky top-0 bg-white dark:bg-gray-800 z-10">
                    <tr>
                        <th class="w-10"></th>
                        <th>Barang</th>
                        <th class="text-center">Stok</th>
                        <th class="text-right">Harga Terakhir</th>
                        <th class="w-28">Jumlah Pesan</th>
                        <th class="w-32">Harga Satuan</th>
                        <th class="w-20">Diskon%</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($barangMapping as $item)
                    @php $dipilih = isset($selectedMapping[$item['barang_id']]); @endphp
                    <tr class="{{ $dipilih ? 'bg-primary-50 dark:bg-blue-900/20' : '' }} cursor-pointer"
                        wire:click="toggleMappingItem({{ $item['barang_id'] }})">
                        <td class="text-center" wire:click.stop>
                            <input type="checkbox"
                                wire:click="toggleMappingItem({{ $item['barang_id'] }})"
                                @checked($dipilih)
                                class="form-checkbox" />
                        </td>
                        <td>
                            <p class="font-medium text-gray-900 dark:text-gray-100">{{ $item['nama'] }}</p>
                            @if($item['kode_barang_supplier'])
                                <p class="text-xs text-gray-400">Kode supplier: {{ $item['kode_barang_supplier'] }}</p>
                            @endif
                        </td>
                        <td class="text-center">
                            <span @class(['font-semibold', 'text-red-600'=>$item['stok_saat_ini'] <= $item['stok_minimum'], 'text-gray-700 dark:text-gray-300'=>$item['stok_saat_ini'] > $item['stok_minimum']])>
                                {{ $item['stok_saat_ini'] }} {{ $item['satuan'] }}
                            </span>
                        </td>
                        <td class="text-right text-gray-600 dark:text-gray-400">
                            Rp {{ number_format($item['harga_terakhir'], 0, ',', '.') }}
                        </td>
                        <td wire:click.stop>
                            @if($dipilih)
                            <input type="number"
                                wire:model.live="selectedMapping.{{ $item['barang_id'] }}.jumlah"
                                class="form-input text-sm py-1 px-2 w-full dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200"
                                min="1" step="1" />
                            @endif
                        </td>
                        <td wire:click.stop>
                            @if($dipilih)
                            <input type="number"
                                wire:model.live="selectedMapping.{{ $item['barang_id'] }}.harga"
                                class="form-input text-sm py-1 px-2 w-full dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200"
                                min="0" step="1" />
                            @endif
                        </td>
                        <td wire:click.stop>
                            @if($dipilih)
                            <input type="number"
                                wire:model.live="selectedMapping.{{ $item['barang_id'] }}.diskon"
                                class="form-input text-sm py-1 px-2 w-full dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200"
                                min="0" max="100" step="0.1" />
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

    {{-- Cari Barang --}}
    @if($supplierId)
    <div class="card">
        <div class="card-header"><h3 class="text-sm font-semibold dark:text-white">Cari & Tambah Barang</h3></div>
        <div class="card-body">
            <div class="relative">
                <span class="absolute inset-y-0 left-3 flex items-center text-gray-400 pointer-events-none">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 11A6 6 0 1 1 5 11a6 6 0 0 1 12 0z"/></svg>
                </span>
                <input wire:model.live.debounce.300ms="searchBarang" type="text" placeholder="Cari nama/kode barang dari supplier ini..." class="form-input pl-9 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200"/>
            </div>
            @if(!empty($hasilSearch))
            <div class="mt-1 border border-gray-200 dark:border-gray-600 rounded-xl divide-y divide-gray-100 dark:divide-gray-700 shadow-sm">
                @foreach($hasilSearch as $b)
                <button type="button" wire:click="addItem({{ $b['id'] }})"
                        class="w-full text-left px-4 py-2.5 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors text-sm">
                    <div class="flex items-center justify-between">
                        <div>
                            <span class="font-medium text-gray-900 dark:text-gray-100">{{ $b['nama'] }}</span>
                            <span class="text-gray-400 ml-2 text-xs">{{ $b['kode'] }}</span>
                        </div>
                        <div class="flex items-center gap-3 text-xs">
                            <span @class(['font-medium', 'text-red-600'=>$b['stok']==0, 'text-amber-600'=>$b['stok']<=$b['stok_minimum']&&$b['stok']>0, 'text-gray-600 dark:text-gray-400'=>$b['stok']>$b['stok_minimum']])>Stok: {{ $b['stok'] }}</span>
                            <span class="text-gray-500">Rp {{ number_format($b['harga_terakhir'],0,',','.') }}</span>
                        </div>
                    </div>
                </button>
                @endforeach
            </div>
            @endif
        </div>
    </div>
    @endif

    {{-- Tabel Item --}}
    @if(!empty($items))
    <div class="card">
        <div class="card-header"><h3 class="text-sm font-semibold dark:text-white">Daftar Item PO</h3></div>
        <div class="card-body p-0">
            <table class="table">
                <thead><tr><th>Nama Barang</th><th>Satuan</th><th>Jumlah Pesan</th><th>Harga Satuan (Rp)</th><th>Diskon (%)</th><th class="text-right">Subtotal (Rp)</th><th></th></tr></thead>
                <tbody>
                    @foreach($items as $i => $item)
                    <tr>
                        <td class="font-medium text-gray-900 dark:text-gray-100">{{ $item['nama_barang'] }}</td>
                        <td class="text-gray-500">{{ $item['satuan'] }}</td>
                        <td><input type="number" wire:model.live="items.{{ $i }}.jumlah_pesan" class="form-input w-24 text-center dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200" min="1"/></td>
                        <td><input type="number" wire:model.live="items.{{ $i }}.harga_satuan" class="form-input w-36 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200" min="0" step="1"/></td>
                        <td><input type="number" wire:model.live="items.{{ $i }}.diskon_persen" class="form-input w-20 text-center dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200" min="0" max="100" step="0.01"/></td>
                        <td class="text-right font-medium text-gray-900 dark:text-gray-100">{{ number_format($item['subtotal']??0,0,',','.') }}</td>
                        <td><button type="button" wire:click="removeItem({{ $i }})" class="text-red-400 hover:text-red-600"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button></td>
                    </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="bg-gray-50 dark:bg-gray-700/50">
                        <td colspan="5" class="px-4 py-3 text-right font-semibold text-gray-700 dark:text-gray-300">Total Nilai PO</td>
                        <td class="px-4 py-3 text-right font-bold text-gray-900 dark:text-white">Rp {{ number_format($totalNilai,0,',','.') }}</td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <div class="flex justify-end gap-3">
        <a href="{{ route('inventory.index', ['tab' => 'po']) }}" class="btn-secondary">Batal</a>
        <button type="button" wire:click="save" class="btn-primary" wire:loading.attr="disabled">
            <span wire:loading.remove wire:target="save">Buat Purchase Order</span>
            <span wire:loading wire:target="save" class="flex items-center gap-2"><div class="spinner h-4 w-4 border-white border-t-transparent"></div> Menyimpan...</span>
        </button>
    </div>
    @endif
</div>
