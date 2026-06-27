<div class="space-y-5">

    {{-- Cari Resep --}}
    @if(!$resepId)
    <div class="card">
        <div class="card-header"><h3 class="text-sm font-semibold dark:text-white">Cari Pasien / Resep</h3></div>
        <div class="card-body">
            <input wire:model.live.debounce.300ms="search" type="text"
                placeholder="Cari nama pasien atau No. RM..."
                class="form-input dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200"/>
            <p class="text-xs text-gray-400 mt-1">Hanya menampilkan resep yang sudah dikonfirmasi & invoice-nya lunas hari ini.</p>

            @if(!empty($resepTersedia))
            <div class="mt-2 border border-gray-200 dark:border-gray-600 rounded-xl divide-y divide-gray-100 dark:divide-gray-700 shadow-sm">
                @foreach($resepTersedia as $r)
                <button type="button" wire:click="pilihResep({{ $r['id'] }})"
                        class="w-full text-left px-4 py-2.5 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors text-sm">
                    <div class="flex items-center justify-between">
                        <span class="font-medium text-gray-900 dark:text-gray-100">{{ $r['pasien_nama'] }}</span>
                        <span class="text-gray-400 text-xs">{{ $r['no_rm'] }} · dikonfirmasi {{ $r['locked_at'] }}</span>
                    </div>
                </button>
                @endforeach
            </div>
            @endif
        </div>
    </div>
    @endif

    {{-- Form Retur --}}
    @if($resepId)
    <div class="card">
        <div class="card-header">
            <h3 class="text-sm font-semibold dark:text-white">Detail Retur</h3>
            <button type="button" wire:click="$set('resepId', null)" class="text-xs text-primary-600 hover:underline">Ganti Resep</button>
        </div>
        <div class="card-body grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="form-group">
                <label class="form-label dark:text-gray-300">Alasan Retur <span class="text-red-500">*</span></label>
                <select wire:model="alasan" class="form-select dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200">
                    <option value="">— Pilih Alasan —</option>
                    <option value="salah_resep">Salah Resep</option>
                    <option value="pasien_menolak">Pasien Menolak</option>
                    <option value="reaksi_alergi">Reaksi Alergi</option>
                    <option value="lainnya">Lainnya</option>
                </select>
                @error('alasan') <p class="form-error">{{ $message }}</p> @enderror
            </div>
            <div class="form-group">
                <label class="form-label dark:text-gray-300">Metode Pengembalian <span class="text-red-500">*</span></label>
                <select wire:model="metodePengembalian" class="form-select dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200">
                    <option value="tunai">Tunai</option>
                    <option value="bank">Transfer Bank</option>
                    <option value="deposit">Konversi ke Deposit Pasien</option>
                </select>
                @error('metodePengembalian') <p class="form-error">{{ $message }}</p> @enderror
            </div>
            <div class="form-group">
                <label class="form-label dark:text-gray-300">Catatan</label>
                <input wire:model="catatan" type="text" class="form-input dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200"/>
            </div>
        </div>
    </div>

    @error('items') <div class="alert alert-danger">{{ $message }}</div> @enderror

    @if(!empty($itemRows))
    <div class="card">
        <div class="card-header"><h3 class="text-sm font-semibold dark:text-white">Item Obat</h3></div>
        <div class="card-body p-0">
            <table class="table">
                <thead>
                    <tr>
                        <th>Nama Barang</th>
                        <th class="text-center">Sisa Bisa Diretur</th>
                        <th class="w-28">Jumlah Retur</th>
                        <th class="text-right">Harga Satuan</th>
                        <th class="text-right">Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($itemRows as $i => $item)
                    <tr>
                        <td class="font-medium text-gray-900 dark:text-gray-100">{{ $item['nama_barang'] }}</td>
                        <td class="text-center text-sm text-gray-500">{{ $item['sisa'] }} {{ $item['satuan'] }}</td>
                        <td>
                            <input type="number" wire:model.live="itemRows.{{ $i }}.jumlah_retur"
                                class="form-input w-24 text-center dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200"
                                min="0" max="{{ $item['sisa'] }}" step="1"/>
                        </td>
                        <td class="text-right text-sm text-gray-600 dark:text-gray-400">Rp {{ number_format($item['harga_satuan'], 0, ',', '.') }}</td>
                        <td class="text-right font-medium text-gray-900 dark:text-gray-100">
                            Rp {{ number_format(($item['jumlah_retur'] ?? 0) * $item['harga_satuan'], 0, ',', '.') }}
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

    @if(!empty($racikanRows))
    <div class="card">
        <div class="card-header"><h3 class="text-sm font-semibold dark:text-white">Racikan (diretur satu kesatuan)</h3></div>
        <div class="card-body p-0">
            <table class="table">
                <thead>
                    <tr>
                        <th class="w-10"></th>
                        <th>Nama Racikan</th>
                        <th class="text-right">Nilai</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($racikanRows as $i => $r)
                    <tr>
                        <td class="text-center"><input type="checkbox" wire:model.live="racikanRows.{{ $i }}.dipilih" class="form-checkbox" /></td>
                        <td class="font-medium text-gray-900 dark:text-gray-100">{{ $r['nama_racikan'] }}</td>
                        <td class="text-right text-sm">Rp {{ number_format($r['harga_satuan'], 0, ',', '.') }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

    @if(empty($itemRows) && empty($racikanRows))
    <div class="alert alert-info">Tidak ada item dari resep ini yang bisa diretur (semua sudah pernah diretur sebelumnya).</div>
    @else
    <div class="card">
        <div class="card-body flex items-center justify-between">
            <span class="text-sm font-semibold text-gray-700 dark:text-gray-300">Total Nilai Retur (akan dikembalikan)</span>
            <span class="text-lg font-bold text-gray-900 dark:text-white">Rp {{ number_format($this->totalNilaiRetur, 0, ',', '.') }}</span>
        </div>
    </div>

    <div class="flex justify-end gap-3">
        <a href="{{ route('farmasi.retur-resep.index') }}" class="btn-secondary">Batal</a>
        <x-confirm-button action="proses" title="Proses Retur Resep Ini?"
            text="Stok akan dikembalikan dan dana akan dikembalikan ke pasien sesuai metode yang dipilih. Tindakan ini tidak bisa dibatalkan."
            icon="warning" type="danger" confirm="Ya, Proses"
            wire:loading.attr="disabled" class="btn-primary">
            <span wire:loading.remove wire:target="proses">Proses Retur</span>
            <span wire:loading wire:target="proses">Memproses...</span>
        </x-confirm-button>
    </div>
    @endif
    @endif
</div>
