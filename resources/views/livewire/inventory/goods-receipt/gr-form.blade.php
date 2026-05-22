<div class="space-y-5">
    {{-- Header GR --}}
    <div class="card">
        <div class="card-header"><h3 class="text-sm font-semibold dark:text-white">Header Penerimaan Barang</h3></div>
        <div class="card-body grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
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
                <label class="form-label dark:text-gray-300">Tanggal Terima <span class="text-red-500">*</span></label>
                <input wire:model="tanggalTerima" type="date" class="form-input dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200"/>
            </div>
            <div class="form-group">
                <label class="form-label dark:text-gray-300">No. Faktur Supplier</label>
                <input wire:model="nomorFaktur" type="text" class="form-input font-mono dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200"/>
            </div>
            <div class="form-group">
                <label class="form-label dark:text-gray-300">Tanggal Faktur</label>
                <input wire:model="tanggalFaktur" type="date" class="form-input dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200"/>
            </div>
            <div class="form-group">
                <label class="form-label dark:text-gray-300">No. Surat Jalan</label>
                <input wire:model="nomorSuratJalan" type="text" class="form-input dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200"/>
            </div>
            <div class="form-group">
                <label class="form-label dark:text-gray-300">Catatan</label>
                <input wire:model="catatan" type="text" class="form-input dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200"/>
            </div>
        </div>
    </div>

    {{-- Load dari PO --}}
    @if($supplierId && !empty($poTersedia))
    <div class="card">
        <div class="card-header"><h3 class="text-sm font-semibold dark:text-white">Pilih dari Purchase Order</h3></div>
        <div class="card-body">
            <div class="flex flex-wrap gap-2">
                @foreach($poTersedia as $po)
                <button type="button" wire:click="loadDariPo({{ $po['id'] }})"
                        @class(['px-4 py-2 rounded-xl border text-sm font-medium transition-colors',
                            'border-[#0a3d62] bg-[#0a3d62] text-white' => $poId === $po['id'],
                            'border-gray-200 text-gray-600 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-400' => $poId !== $po['id']])>
                    {{ $po['nomor_po'] }} <span class="text-xs opacity-70">({{ $po['status'] }})</span>
                </button>
                @endforeach
            </div>
        </div>
    </div>
    @endif

    {{-- Tabel Item GR --}}
    @if(!empty($items))
    <div class="card">
        <div class="card-header">
            <h3 class="text-sm font-semibold dark:text-white">Item yang Diterima</h3>
            <span class="text-sm text-gray-500">Total: <strong>Rp {{ number_format($totalNilai,0,',','.') }}</strong></span>
        </div>
        <div class="card-body p-0 overflow-x-auto">
            <table class="table" style="min-width:900px">
                <thead><tr><th>Nama Barang</th><th>Sisa PO</th><th>Jml Terima</th><th>Harga Satuan</th><th>Diskon%</th><th>No. Batch</th><th>Expired</th><th class="text-right">Subtotal</th></tr></thead>
                <tbody>
                    @foreach($items as $i => $item)
                    <tr>
                        <td class="font-medium text-gray-900 dark:text-gray-100 text-sm">{{ $item['nama_barang'] }}</td>
                        <td class="text-sm text-center text-gray-500">{{ $item['sisa_pesan'] ?? '—' }}</td>
                        <td><input type="number" wire:model.live="items.{{ $i }}.jumlah_terima" class="form-input w-24 text-center dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200" min="1"/>
                            @error("items.{$i}.jumlah_terima") <p class="form-error">{{ $message }}</p> @enderror
                        </td>
                        <td><input type="number" wire:model="items.{{ $i }}.harga_satuan" class="form-input w-32 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200" min="0"/></td>
                        <td><input type="number" wire:model="items.{{ $i }}.diskon_persen" class="form-input w-20 text-center dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200" min="0" max="100"/></td>
                        <td><input type="text" wire:model="items.{{ $i }}.nomor_batch" class="form-input w-32 font-mono dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200" placeholder="Batch No."/></td>
                        <td><input type="date" wire:model="items.{{ $i }}.expired_date" class="form-input w-36 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200"/></td>
                        <td class="text-right font-medium text-sm text-gray-900 dark:text-gray-100">
                            Rp {{ number_format(($item['jumlah_terima']??0)*($item['harga_satuan']??0)*(1-($item['diskon_persen']??0)/100),0,',','.') }}
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    {{-- Info HPR --}}
    <div class="card border-blue-200 dark:border-blue-700">
        <div class="card-body">
            <div class="flex items-start gap-3">
                <svg class="h-5 w-5 text-blue-500 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                </svg>
                <div class="text-sm text-blue-700 dark:text-blue-300">
                    <p class="font-semibold mb-1">Verifikasi akan otomatis:</p>
                    <ul class="space-y-0.5 text-xs list-disc ml-4">
                        <li>Update stok barang</li>
                        <li>Hitung ulang Harga Pokok Rata-rata (Moving Average)</li>
                        <li>Catat mutasi stok</li>
                        <li>Update harga terakhir di supplier</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <div class="flex justify-end gap-3">
        <a href="{{ route('inventory.index', ['tab' => 'gr']) }}" class="btn-secondary">Batal</a>
        <button type="button" wire:click="simpan" class="btn-secondary" wire:loading.attr="disabled">Simpan Draft</button>
        <button type="button" wire:click="simpanDanVerifikasi" class="btn-primary" wire:loading.attr="disabled">
            <span wire:loading.remove wire:target="simpanDanVerifikasi">Simpan & Verifikasi</span>
            <span wire:loading wire:target="simpanDanVerifikasi" class="flex items-center gap-2"><div class="spinner h-4 w-4 border-white border-t-transparent"></div> Memproses...</span>
        </button>
    </div>
    @elseif($supplierId)
    <div class="card"><div class="card-body"><p class="text-sm text-gray-400 text-center">Pilih PO di atas atau tambahkan item secara manual</p></div></div>
    @endif
</div>
