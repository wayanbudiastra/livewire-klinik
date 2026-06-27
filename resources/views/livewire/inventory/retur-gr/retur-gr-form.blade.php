<div class="space-y-5">

    {{-- Cari GR --}}
    @if(!$goodsReceiptId)
    <div class="card">
        <div class="card-header"><h3 class="text-sm font-semibold dark:text-white">Cari Goods Receipt (GR)</h3></div>
        <div class="card-body">
            <div class="relative">
                <input wire:model.live.debounce.300ms="search" type="text"
                    placeholder="Cari nomor GR atau nama supplier..."
                    class="form-input dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200"/>
            </div>
            @if(!empty($grTersedia))
            <div class="mt-1 border border-gray-200 dark:border-gray-600 rounded-xl divide-y divide-gray-100 dark:divide-gray-700 shadow-sm">
                @foreach($grTersedia as $gr)
                <button type="button" wire:click="pilihGr({{ $gr['id'] }})"
                        class="w-full text-left px-4 py-2.5 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors text-sm">
                    <div class="flex items-center justify-between">
                        <span class="font-medium text-gray-900 dark:text-gray-100">{{ $gr['nomor_gr'] }}</span>
                        <span class="text-gray-400 text-xs">{{ $gr['supplier'] }} · {{ $gr['tanggal'] }}</span>
                    </div>
                </button>
                @endforeach
            </div>
            @endif
            @error('goodsReceiptId') <p class="form-error mt-2">{{ $message }}</p> @enderror
        </div>
    </div>
    @endif

    {{-- Form Retur --}}
    @if($goodsReceiptId)
    <div class="card">
        <div class="card-header">
            <h3 class="text-sm font-semibold dark:text-white">Retur dari GR</h3>
            <button type="button" wire:click="$set('goodsReceiptId', 0)" class="text-xs text-primary-600 hover:underline">Ganti GR</button>
        </div>
        <div class="card-body grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="form-group">
                <label class="form-label dark:text-gray-300">Alasan Retur <span class="text-red-500">*</span></label>
                <select wire:model="alasan" class="form-select dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200">
                    <option value="">— Pilih Alasan —</option>
                    <option value="rusak">Barang Rusak</option>
                    <option value="salah_kirim">Salah Kirim</option>
                    <option value="kualitas_buruk">Kualitas Buruk</option>
                    <option value="lainnya">Lainnya</option>
                </select>
                @error('alasan') <p class="form-error">{{ $message }}</p> @enderror
            </div>
            <div class="form-group">
                <label class="form-label dark:text-gray-300">Catatan</label>
                <input wire:model="catatan" type="text" class="form-input dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200"/>
            </div>
        </div>
    </div>

    @error('items') <div class="alert alert-danger">{{ $message }}</div> @enderror

    <div class="card">
        <div class="card-header"><h3 class="text-sm font-semibold dark:text-white">Item yang Bisa Diretur</h3></div>
        <div class="card-body p-0">
            <table class="table">
                <thead>
                    <tr>
                        <th>Nama Barang</th>
                        <th class="text-center">Stok Saat Ini</th>
                        <th class="text-center">Sisa Bisa Diretur</th>
                        <th class="w-28">Jumlah Retur</th>
                        <th class="text-right">Harga Satuan</th>
                        <th class="text-right">Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($items as $i => $item)
                    <tr>
                        <td class="font-medium text-gray-900 dark:text-gray-100">{{ $item['nama_barang'] }}</td>
                        <td class="text-center text-sm text-gray-500">{{ $item['stok_tersedia'] }} {{ $item['satuan'] }}</td>
                        <td class="text-center text-sm text-gray-500">{{ $item['sisa_bisa_diretur'] }}</td>
                        <td>
                            <input type="number" wire:model.live="items.{{ $i }}.jumlah_retur"
                                class="form-input w-24 text-center dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200"
                                min="0" max="{{ $item['sisa_bisa_diretur'] }}" step="1"/>
                        </td>
                        <td class="text-right text-sm text-gray-600 dark:text-gray-400">
                            Rp {{ number_format($item['harga_satuan'], 0, ',', '.') }}
                        </td>
                        <td class="text-right font-medium text-gray-900 dark:text-gray-100">
                            Rp {{ number_format(($item['jumlah_retur'] ?? 0) * $item['harga_satuan'] * (1 - $item['diskon_persen'] / 100), 0, ',', '.') }}
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="6" class="empty-state py-10"><p class="empty-state-text">Tidak ada item yang bisa diretur dari GR ini (semua sudah diretur sebelumnya)</p></td></tr>
                    @endforelse
                </tbody>
                @if(!empty($items))
                <tfoot>
                    <tr class="bg-gray-50 dark:bg-gray-700/50">
                        <td colspan="5" class="px-4 py-3 text-right font-semibold text-gray-700 dark:text-gray-300">Total Nilai Retur</td>
                        <td class="px-4 py-3 text-right font-bold text-gray-900 dark:text-white">Rp {{ number_format($totalNilai, 0, ',', '.') }}</td>
                    </tr>
                </tfoot>
                @endif
            </table>
        </div>
    </div>

    @if(!empty($items))
    <div class="flex justify-end gap-3">
        <a href="{{ route('inventory.retur-gr.index') }}" class="btn-secondary">Batal</a>
        <button type="button" wire:click="simpan" class="btn-secondary" wire:loading.attr="disabled">
            <span wire:loading.remove wire:target="simpan">Simpan Draft</span>
            <span wire:loading wire:target="simpan">Menyimpan...</span>
        </button>
        <x-confirm-button action="simpanDanVerifikasi" title="Simpan & Verifikasi Retur?"
            text="Stok akan berkurang dan hutang dagang dikoreksi. Tindakan ini tidak bisa diedit lagi."
            icon="warning" type="danger" confirm="Ya, Verifikasi"
            wire:loading.attr="disabled" class="btn-primary">
            <span wire:loading.remove wire:target="simpanDanVerifikasi">Simpan & Verifikasi</span>
            <span wire:loading wire:target="simpanDanVerifikasi">Memproses...</span>
        </x-confirm-button>
    </div>
    @endif
    @endif
</div>
