<div class="space-y-5">
    @if($bhp && $bhp->status !== 'draft')
    <div class="alert alert-info">
        Dokumen ini berstatus <strong>{{ $bhp->status_label }}</strong> dan tidak dapat diedit.
    </div>
    @endif

    @error('items')
    <div class="alert alert-danger">{{ $message }}</div>
    @enderror

    {{-- Header --}}
    <div class="card">
        <div class="card-header">
            <h3 class="text-sm font-semibold dark:text-white">
                {{ $bhp ? 'Dokumen BHP — ' . $bhp->nomor_bhp : 'Buat Dokumen Pemakaian BHP' }}
            </h3>
            @if($bhp)
            <span @class(['badge', 'badge-warning'=>$bhp->status==='draft', 'badge-success'=>$bhp->status==='selesai', 'badge-gray'=>$bhp->status==='dibatalkan'])>
                {{ $bhp->status_label }}
            </span>
            @endif
        </div>
        <div class="card-body grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="form-group">
                <label class="form-label">Tanggal Pemakaian <span class="text-red-500">*</span></label>
                <input type="date" wire:model="tanggalPemakaian"
                    class="form-input dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200"
                    @disabled($bhp && $bhp->status !== 'draft') />
                @error('tanggalPemakaian') <p class="form-error">{{ $message }}</p> @enderror
            </div>
            <div class="form-group">
                <label class="form-label">Catatan</label>
                <input type="text" wire:model="catatan"
                    class="form-input dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200"
                    placeholder="Opsional — tujuan pemakaian, dll."
                    @disabled($bhp && $bhp->status !== 'draft') />
            </div>
        </div>
    </div>

    {{-- Cari Barang BHP --}}
    @if(!$bhp || $bhp->status === 'draft')
    <div class="card">
        <div class="card-header">
            <h3 class="text-sm font-semibold dark:text-white">Tambah Bahan Habis Pakai</h3>
            <p class="text-xs text-gray-400">Hanya barang berjenis "bahan habis pakai"</p>
        </div>
        <div class="card-body">
            <div class="relative">
                <span class="absolute inset-y-0 left-3 flex items-center text-gray-400 pointer-events-none">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 11A6 6 0 1 1 5 11a6 6 0 0 1 12 0z"/>
                    </svg>
                </span>
                <input wire:model.live.debounce.300ms="searchBarang" type="text"
                    placeholder="Cari nama/kode bahan habis pakai..."
                    class="form-input pl-9 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200" />
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
                            <span @class(['font-medium', 'text-red-600'=>$b['stok']<=0, 'text-amber-600'=>$b['stok']<5&&$b['stok']>0, 'text-gray-500'=>$b['stok']>=5])>
                                Stok: {{ $b['stok'] }} {{ $b['satuan'] }}
                            </span>
                            <span class="text-gray-400">HPP: Rp {{ number_format($b['harga_pokok'], 0, ',', '.') }}</span>
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
        <div class="card-header">
            <h3 class="text-sm font-semibold dark:text-white">Daftar Item BHP</h3>
            <p class="text-sm font-semibold text-gray-700 dark:text-gray-300">
                Total: Rp {{ number_format($totalNilai, 0, ',', '.') }}
            </p>
        </div>
        <div class="card-body p-0">
            <table class="table">
                <thead>
                    <tr>
                        <th>Barang</th>
                        <th class="text-center">Stok Tersedia</th>
                        <th class="w-28">Jumlah</th>
                        <th class="text-right">HPP/satuan</th>
                        <th class="text-right">Nilai</th>
                        <th>Keterangan</th>
                        @if(!$bhp || $bhp->status === 'draft')
                        <th></th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @foreach($items as $i => $item)
                    <tr>
                        <td>
                            <p class="font-medium text-gray-900 dark:text-gray-100 text-sm">{{ $item['nama_barang'] }}</p>
                            <p class="text-xs text-gray-400">{{ $item['satuan'] }}</p>
                        </td>
                        <td class="text-center">
                            <span @class(['text-sm font-medium', 'text-red-600'=>$item['stok_tersedia']<=0, 'text-amber-600'=>$item['stok_tersedia']>0&&$item['stok_tersedia']<$item['jumlah'], 'text-gray-600 dark:text-gray-400'=>$item['stok_tersedia']>=$item['jumlah']])>
                                {{ $item['stok_tersedia'] }}
                            </span>
                        </td>
                        <td>
                            @if(!$bhp || $bhp->status === 'draft')
                            <input type="number" wire:model.live="items.{{ $i }}.jumlah"
                                class="form-input w-24 text-center dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200"
                                min="0.01" step="0.01" />
                            @else
                            <span class="text-sm">{{ $item['jumlah'] }}</span>
                            @endif
                        </td>
                        <td class="text-right text-sm text-gray-600 dark:text-gray-400">
                            Rp {{ number_format($item['harga_pokok_saat_itu'], 0, ',', '.') }}
                        </td>
                        <td class="text-right font-medium text-sm">
                            Rp {{ number_format($item['nilai_total'], 0, ',', '.') }}
                        </td>
                        <td>
                            @if(!$bhp || $bhp->status === 'draft')
                            <input type="text" wire:model.live="items.{{ $i }}.keterangan"
                                class="form-input text-sm dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200"
                                placeholder="Opsional" />
                            @else
                            <span class="text-sm text-gray-500">{{ $item['keterangan'] ?: '-' }}</span>
                            @endif
                        </td>
                        @if(!$bhp || $bhp->status === 'draft')
                        <td>
                            <button type="button" wire:click="removeItem({{ $i }})"
                                class="text-red-400 hover:text-red-600">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </button>
                        </td>
                        @endif
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    @if(!$bhp || $bhp->status === 'draft')
    <div class="flex justify-end gap-3">
        <a href="{{ route('inventory.bhp.index') }}" class="btn-secondary">Kembali</a>
        <button type="button" wire:click="simpan" wire:loading.attr="disabled" class="btn-secondary">
            <span wire:loading.remove wire:target="simpan">Simpan Draft</span>
            <span wire:loading wire:target="simpan">Menyimpan...</span>
        </button>
        <button type="button" wire:click="verifikasi" wire:loading.attr="disabled" class="btn-primary"
            onclick="return confirm('Stok akan berkurang. Proses tidak bisa dibatalkan. Lanjutkan?')">
            <span wire:loading.remove wire:target="verifikasi">Verifikasi & Keluarkan Stok</span>
            <span wire:loading wire:target="verifikasi">Memproses...</span>
        </button>
    </div>
    @else
    <div class="flex justify-end">
        <a href="{{ route('inventory.bhp.index') }}" class="btn-secondary">Kembali ke Daftar</a>
    </div>
    @endif
    @endif
</div>
