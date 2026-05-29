<div class="space-y-5">

    @error('items')
    <div class="alert alert-danger">{{ $message }}</div>
    @enderror

    {{-- ── Identitas Pembeli ─────────────────────────────────────────── --}}
    <div class="card">
        <div class="card-header">
            <h3 class="text-sm font-semibold dark:text-white">
                {{ $transaksi ? 'Edit Transaksi — ' . $transaksi->nomor_ritel : 'Transaksi Ritel Baru' }}
            </h3>
            @if($transaksi)
            <span class="badge badge-warning">Draft</span>
            @endif
        </div>
        <div class="card-body space-y-4">

            {{-- Cari Pasien --}}
            <div>
                <label class="form-label">Pasien Terdaftar (opsional)</label>

                @if($pasienId)
                {{-- Pasien sudah dipilih --}}
                <div class="flex items-center gap-3 p-3 rounded-lg bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800">
                    <div class="flex-1">
                        <p class="text-sm font-semibold text-blue-800 dark:text-blue-300">{{ $pasienNama }}</p>
                        <p class="text-xs text-blue-600 dark:text-blue-400 font-mono">RM: {{ $pasienRm }}</p>
                    </div>
                    <button type="button" wire:click="clearPasien"
                        class="text-xs text-blue-500 hover:text-red-500 transition-colors underline">
                        Ganti / Hapus
                    </button>
                </div>
                @else
                {{-- Search pasien --}}
                <div class="relative">
                    <span class="absolute inset-y-0 left-3 flex items-center text-gray-400 pointer-events-none">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 11A6 6 0 1 1 5 11a6 6 0 0 1 12 0z"/>
                        </svg>
                    </span>
                    <input wire:model.live.debounce.400ms="searchPasien" type="text"
                        placeholder="Cari nama / nomor RM pasien... (kosongkan jika pembeli umum)"
                        class="form-input pl-9 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200" />
                </div>

                @if(!empty($hasilSearchPasien))
                <div class="mt-1 border border-gray-200 dark:border-gray-600 rounded-xl divide-y divide-gray-100 dark:divide-gray-700 shadow-sm">
                    @foreach($hasilSearchPasien as $p)
                    <button type="button" wire:click="selectPasien({{ $p['id'] }})"
                        class="w-full text-left px-4 py-2.5 hover:bg-blue-50 dark:hover:bg-blue-900/20 transition-colors text-sm">
                        <div class="flex items-center justify-between">
                            <span class="font-medium text-gray-900 dark:text-gray-100">{{ $p['nama'] }}</span>
                            <div class="flex items-center gap-3 text-xs text-gray-500 dark:text-gray-400">
                                <span class="font-mono">{{ $p['nomor_rm'] }}</span>
                                <span>{{ $p['lahir'] }}</span>
                            </div>
                        </div>
                    </button>
                    @endforeach
                </div>
                @endif

                <p class="text-xs text-gray-400 mt-1">Biarkan kosong untuk pembeli tanpa nomor RM (umum)</p>
                @endif
            </div>

            {{-- Nama + HP --}}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="form-group mb-0">
                    <label class="form-label">Nama Pembeli <span class="text-red-500">*</span></label>
                    <input type="text" wire:model="namaPembeli"
                        class="form-input dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200"
                        placeholder="Nama lengkap pembeli"
                        @if($pasienId) readonly @endif />
                    @error('namaPembeli') <p class="form-error">{{ $message }}</p> @enderror
                    @if($pasienId)
                    <p class="text-xs text-gray-400 mt-1">Otomatis dari data pasien. Ganti pasien untuk mengubah.</p>
                    @endif
                </div>
                <div class="form-group mb-0">
                    <label class="form-label">Nomor HP</label>
                    <input type="text" wire:model="nomorHp"
                        class="form-input dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200"
                        placeholder="Opsional" />
                </div>
            </div>

            {{-- Catatan --}}
            <div class="form-group mb-0">
                <label class="form-label">Catatan</label>
                <input type="text" wire:model="catatan"
                    class="form-input dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200"
                    placeholder="Opsional — catatan khusus" />
            </div>
        </div>
    </div>

    {{-- ── Cari Obat ────────────────────────────────────────────────────── --}}
    <div class="card">
        <div class="card-header">
            <h3 class="text-sm font-semibold dark:text-white">Tambah Obat / Alkes</h3>
            <p class="text-xs text-gray-400">Filter: aktif, stok > 0</p>
        </div>
        <div class="card-body">
            <div class="relative">
                <span class="absolute inset-y-0 left-3 flex items-center text-gray-400 pointer-events-none">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 11A6 6 0 1 1 5 11a6 6 0 0 1 12 0z"/>
                    </svg>
                </span>
                <input wire:model.live.debounce.400ms="searchObat" type="text"
                    placeholder="Cari nama / kode / barcode obat..."
                    class="form-input pl-9 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200" />
            </div>

            @if(!empty($hasilSearch))
            <div class="mt-1 border border-gray-200 dark:border-gray-600 rounded-xl divide-y divide-gray-100 dark:divide-gray-700 shadow-sm">
                @foreach($hasilSearch as $b)
                <button type="button" wire:click="addItem({{ $b['id'] }})"
                        class="w-full text-left px-4 py-2.5 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors text-sm">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span class="font-medium text-gray-900 dark:text-gray-100">{{ $b['nama'] }}</span>
                            <span class="text-gray-400 text-xs">{{ $b['kode'] }}</span>
                            @if($b['butuh_resep'])
                            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300">
                                Butuh Resep
                            </span>
                            @endif
                        </div>
                        <div class="flex items-center gap-3 text-xs">
                            <span @class(['font-medium', 'text-red-600'=>$b['stok']<=0, 'text-amber-600'=>$b['stok']<5&&$b['stok']>0, 'text-emerald-600'=>$b['stok']>=5])>
                                Stok: {{ $b['stok'] }} {{ $b['satuan'] }}
                            </span>
                            <span class="text-gray-500 dark:text-gray-400">Rp {{ number_format($b['harga_jual'], 0, ',', '.') }}/{{ $b['satuan'] }}</span>
                        </div>
                    </div>
                </button>
                @endforeach
            </div>
            @endif
        </div>
    </div>

    {{-- ── Tabel Item Cart ──────────────────────────────────────────────── --}}
    @if(!empty($items))
    <div class="card">
        <div class="card-header">
            <h3 class="text-sm font-semibold dark:text-white">Daftar Item</h3>
            <p class="text-sm font-bold text-gray-700 dark:text-gray-300">
                Total: Rp {{ number_format($totalHarga, 0, ',', '.') }}
            </p>
        </div>
        <div class="card-body p-0">
            <table class="table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Obat / Alkes</th>
                        <th class="text-center">Stok</th>
                        <th class="w-28 text-center">Jumlah</th>
                        <th class="text-right">Harga Satuan</th>
                        <th class="text-right">Subtotal</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($items as $i => $item)
                    <tr wire:key="item-{{ $i }}">
                        <td class="text-gray-400 text-sm">{{ $i + 1 }}</td>
                        <td>
                            <p class="font-medium text-gray-900 dark:text-gray-100 text-sm">{{ $item['nama_barang'] }}</p>
                            <p class="text-xs text-gray-400">{{ $item['kode'] }} · {{ $item['satuan'] }}</p>
                            @if($item['butuh_resep'])
                            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300 mt-0.5">
                                ⚠ Butuh Resep
                            </span>
                            @endif
                        </td>
                        <td class="text-center">
                            <span @class(['text-sm font-medium', 'text-red-600'=>$item['stok']<=0, 'text-amber-600'=>$item['stok']>0&&$item['stok']<$item['jumlah'], 'text-gray-600 dark:text-gray-400'=>$item['stok']>=$item['jumlah']])>
                                {{ $item['stok'] }}
                            </span>
                        </td>
                        <td>
                            <input type="number" wire:model.live="items.{{ $i }}.jumlah"
                                class="form-input w-24 text-center dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200"
                                min="1" max="{{ $item['stok'] }}" step="1" />
                        </td>
                        <td class="text-right text-sm text-gray-600 dark:text-gray-400">
                            Rp {{ number_format($item['harga_satuan'], 0, ',', '.') }}
                        </td>
                        <td class="text-right font-medium text-sm">
                            Rp {{ number_format($item['subtotal'], 0, ',', '.') }}
                        </td>
                        <td>
                            <button type="button" wire:click="removeItem({{ $i }})"
                                class="text-red-400 hover:text-red-600 transition-colors">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </button>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="5" class="text-right font-semibold text-gray-700 dark:text-gray-300 py-3 px-4">Total</td>
                        <td class="text-right font-bold text-lg text-gray-900 dark:text-white py-3 px-4">
                            Rp {{ number_format($totalHarga, 0, ',', '.') }}
                        </td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
    @endif

    {{-- ── Aksi ─────────────────────────────────────────────────────────── --}}
    <div class="flex justify-end gap-3">
        <a href="{{ route('farmasi.ritel.index') }}" class="btn-secondary">Kembali</a>
        <button type="button" wire:click="simpanDraft" wire:loading.attr="disabled" class="btn-secondary">
            <span wire:loading.remove wire:target="simpanDraft">Simpan Draft</span>
            <span wire:loading wire:target="simpanDraft">Menyimpan...</span>
        </button>
        <button type="button" wire:click="submitKeKasir" wire:loading.attr="disabled" class="btn-primary"
            onclick="return confirm('Kirim transaksi ke kasir? Setelah di-submit, tidak bisa diedit lagi.')">
            <span wire:loading.remove wire:target="submitKeKasir">Submit ke Kasir →</span>
            <span wire:loading wire:target="submitKeKasir">Memproses...</span>
        </button>
    </div>

</div>
