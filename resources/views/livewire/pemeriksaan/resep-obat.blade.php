<div>
    @if($this->isLocked)
    <div class="rounded-xl border-2 border-emerald-400 bg-emerald-50 dark:bg-emerald-900/20 px-4 py-3 mb-4 flex items-center gap-3">
        <svg class="w-5 h-5 text-emerald-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
        </svg>
        <div>
            <p class="font-semibold text-emerald-700 dark:text-emerald-400 text-sm">Resep telah dikonfirmasi oleh Apoteker</p>
            <p class="text-xs text-emerald-600 dark:text-emerald-500 mt-0.5">
                Dikunci pada {{ $this->resep?->locked_at?->format('d/m/Y H:i') }} · Data tidak dapat diubah
            </p>
        </div>
    </div>
    @endif

    {{-- ═══ TAB NAVIGATOR ═══ --}}
    @if(!$this->isLocked)
    <div class="flex gap-1 mb-4 border-b border-gray-200 dark:border-gray-700">
        <button type="button" wire:click="$set('activeTab', 'non_racikan')"
                @class([
                    'px-5 py-2.5 text-sm font-medium border-b-2 -mb-px transition-colors flex items-center gap-2',
                    'border-[#0a3d62] text-[#0a3d62] dark:border-blue-400 dark:text-blue-400' => $activeTab === 'non_racikan',
                    'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200' => $activeTab !== 'non_racikan',
                ])>
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"/>
            </svg>
            Obat Jadi
            @if(count($cartObat) > 0)
            <span class="inline-flex items-center justify-center w-5 h-5 text-xs rounded-full bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300 font-bold">{{ count($cartObat) }}</span>
            @endif
        </button>

        <button type="button" wire:click="$set('activeTab', 'racikan')"
                @class([
                    'px-5 py-2.5 text-sm font-medium border-b-2 -mb-px transition-colors flex items-center gap-2',
                    'border-[#0a3d62] text-[#0a3d62] dark:border-blue-400 dark:text-blue-400' => $activeTab === 'racikan',
                    'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200' => $activeTab !== 'racikan',
                ])>
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M9 3H5a2 2 0 00-2 2v4m6-6h10a2 2 0 012 2v4M9 3v18m0 0h10a2 2 0 002-2V9M9 21H5a2 2 0 01-2-2V9m0 0h18"/>
            </svg>
            Racikan
            @if(count($cartBahan) > 0)
            <span class="inline-flex items-center justify-center w-5 h-5 text-xs rounded-full bg-purple-100 text-purple-700 dark:bg-purple-900 dark:text-purple-300 font-bold">{{ count($cartBahan) }}</span>
            @endif
        </button>
    </div>

    {{-- ═══ TAB: OBAT JADI (NON-RACIKAN) ═══ --}}
    @if($activeTab === 'non_racikan')
    <div class="card mb-4">
        <div class="card-header">
            <h3 class="text-sm font-semibold dark:text-white">Tambah Obat Jadi</h3>
        </div>
        <div class="card-body">
            <div x-data="{ open: false }" class="relative">
                <label class="form-label dark:text-gray-300">Cari Obat</label>
                <div class="relative">
                    <input wire:model.live.debounce.300ms="searchObat"
                           @focus="open = true" @click.away="open = false"
                           type="text"
                           placeholder="Ketik nama, kode, atau generik (min. 2 karakter)..."
                           class="form-input pr-10 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200"/>
                    <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none">
                        <svg wire:loading wire:target="searchObat" class="animate-spin h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                        </svg>
                    </div>
                </div>

                @if(strlen($searchObat) >= 2)
                <div x-show="open" x-transition
                     class="absolute z-50 w-full mt-1 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-600 rounded-xl shadow-lg max-h-60 overflow-y-auto">
                    @forelse($this->suggestionsObat as $obat)
                    <button type="button"
                            wire:click="addToCartObat({{ $obat->id }}, '{{ $obat->kode }}', @js($obat->nama), '{{ $obat->harga }}', @js($obat->satuan ?? ''))"
                            @click="open = false"
                            class="w-full flex items-center justify-between px-4 py-2.5 text-sm hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors text-left">
                        <div>
                            <span class="font-medium text-gray-900 dark:text-gray-100">{{ $obat->nama }}</span>
                            @if($obat->generik)
                            <span class="ml-1 text-xs text-gray-400">({{ $obat->generik }})</span>
                            @endif
                            <span class="ml-2 text-xs text-gray-400 font-mono">{{ $obat->kode }}</span>
                        </div>
                        <div class="flex items-center gap-3 ml-4 flex-shrink-0">
                            <span class="text-xs text-gray-500">Stok: {{ $obat->stok }}</span>
                            <span class="text-xs text-blue-600 dark:text-blue-400 font-semibold">
                                Rp {{ number_format($obat->harga, 0, ',', '.') }}
                            </span>
                        </div>
                    </button>
                    @empty
                    <div class="px-4 py-3 text-sm text-gray-400 text-center">Tidak ada obat ditemukan (aktif & stok > 0)</div>
                    @endforelse
                </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Cart Obat Jadi --}}
    @if(count($cartObat) > 0)
    <div class="card mb-4">
        <div class="card-header flex items-center justify-between">
            <h3 class="text-sm font-semibold dark:text-white">
                Keranjang Resep
                <span class="ml-1 text-xs font-normal text-gray-400">(belum disimpan)</span>
            </h3>
        </div>
        <div class="card-body p-0">
            <div class="divide-y divide-gray-100 dark:divide-gray-700">
                @foreach($cartObat as $idx => $item)
                <div wire:key="cart-obat-{{ $idx }}" class="flex flex-col sm:flex-row gap-3 items-start sm:items-center px-4 py-3">
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $item['nama'] }}</p>
                        <p class="text-xs text-gray-400 font-mono">{{ $item['kode'] }} · Rp {{ number_format($item['harga'], 0, ',', '.') }}</p>
                    </div>
                    <div class="flex items-center gap-2 flex-shrink-0">
                        <label class="text-xs text-gray-500 dark:text-gray-400">Jml:</label>
                        <input wire:model="cartObat.{{ $idx }}.jumlah"
                               type="number" min="1"
                               class="form-input w-16 text-xs py-1.5 text-center dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200"/>
                        <span class="text-xs text-gray-400">{{ $item['satuan'] }}</span>
                    </div>
                    <div class="w-full sm:w-56">
                        <input wire:model="cartObat.{{ $idx }}.signa"
                               type="text"
                               placeholder="Signa: 3x1 sesudah makan..."
                               class="form-input text-xs py-1.5 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200"/>
                    </div>
                    <button type="button" wire:click="removeFromCartObat({{ $idx }})"
                            class="text-red-400 hover:text-red-600 flex-shrink-0 transition-colors">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                        </svg>
                    </button>
                </div>
                @endforeach
            </div>
            <div class="px-4 py-3 bg-gray-50 dark:bg-gray-700/50 flex justify-end border-t border-gray-100 dark:border-gray-700">
                <button type="button" wire:click="submitNonRacikan"
                        class="btn-primary flex items-center gap-2"
                        wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="submitNonRacikan">
                        <svg class="w-4 h-4 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
                        </svg>
                        Simpan ke Resep
                    </span>
                    <span wire:loading wire:target="submitNonRacikan" class="flex items-center gap-2">
                        <div class="spinner h-4 w-4 border-white border-t-transparent"></div>
                        Menyimpan...
                    </span>
                </button>
            </div>
        </div>
    </div>
    @endif

    {{-- ═══ TAB: RACIKAN ═══ --}}
    @elseif($activeTab === 'racikan')
    <div class="card mb-4">
        <div class="card-header">
            <h3 class="text-sm font-semibold dark:text-white">Form Racikan</h3>
        </div>
        <div class="card-body space-y-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div class="form-group">
                    <label class="form-label dark:text-gray-300">Nama Racikan <span class="text-red-500">*</span></label>
                    <input wire:model="namaRacikan" type="text"
                           placeholder="Contoh: Puyer Batuk No.10"
                           class="form-input dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200"/>
                    @error('namaRacikan') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                <div class="form-group">
                    <label class="form-label dark:text-gray-300">Aturan Pakai (Signa)</label>
                    <input wire:model="aturanPakaiRacikan" type="text"
                           placeholder="Contoh: 3x1 bungkus sesudah makan"
                           class="form-input dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200"/>
                </div>
                <div class="form-group">
                    <label class="form-label dark:text-gray-300">Metode <span class="text-red-500">*</span></label>
                    <select wire:model="metodeRacikan"
                            class="form-select dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200">
                        <option value="puyer">Puyer / Serbuk</option>
                        <option value="kapsul">Kapsul</option>
                        <option value="salep">Salep</option>
                        <option value="krim">Krim</option>
                        <option value="sirup">Sirup</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label dark:text-gray-300">Jumlah Sediaan <span class="text-red-500">*</span></label>
                    <div class="flex items-center gap-2">
                        <input wire:model="jumlahSediaanRacikan" type="number" min="1"
                               class="form-input w-24 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200"/>
                        <span class="text-sm text-gray-500 dark:text-gray-400">bungkus/kapsul/dll</span>
                    </div>
                    @error('jumlahSediaanRacikan') <p class="form-error">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="form-group">
                <label class="form-label dark:text-gray-300">Catatan (opsional)</label>
                <input wire:model="catatanRacikan" type="text"
                       placeholder="Catatan tambahan untuk apoteker..."
                       class="form-input dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200"/>
            </div>

            {{-- Bahan Racikan --}}
            <div>
                <p class="form-label dark:text-gray-300 mb-2">Komposisi Bahan</p>

                <div x-data="{ open: false }" class="relative mb-3">
                    <div class="relative">
                        <input wire:model.live.debounce.300ms="searchBahan"
                               @focus="open = true" @click.away="open = false"
                               type="text"
                               placeholder="Cari bahan obat (min. 2 karakter)..."
                               class="form-input pr-10 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200"/>
                        <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none">
                            <svg wire:loading wire:target="searchBahan" class="animate-spin h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                            </svg>
                        </div>
                    </div>

                    @if(strlen($searchBahan) >= 2)
                    <div x-show="open" x-transition
                         class="absolute z-50 w-full mt-1 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-600 rounded-xl shadow-lg max-h-52 overflow-y-auto">
                        @forelse($this->suggestionsBahan as $obat)
                        <button type="button"
                                wire:click="addBahan({{ $obat->id }}, '{{ $obat->kode }}', @js($obat->nama), @js($obat->satuan ?? ''))"
                                @click="open = false"
                                class="w-full flex items-center justify-between px-4 py-2 text-sm hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors text-left">
                            <div>
                                <span class="font-medium text-gray-900 dark:text-gray-100">{{ $obat->nama }}</span>
                                <span class="ml-2 text-xs text-gray-400 font-mono">{{ $obat->kode }}</span>
                            </div>
                            <span class="text-xs text-gray-500 ml-4">{{ $obat->satuan }}</span>
                        </button>
                        @empty
                        <div class="px-4 py-3 text-sm text-gray-400 text-center">Tidak ada obat ditemukan</div>
                        @endforelse
                    </div>
                    @endif
                </div>

                @if(count($cartBahan) > 0)
                <div class="border border-gray-200 dark:border-gray-600 rounded-xl overflow-hidden">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 dark:bg-gray-700/50">
                            <tr>
                                <th class="text-left px-4 py-2 text-xs font-medium text-gray-500 dark:text-gray-400">Bahan</th>
                                <th class="text-left px-4 py-2 text-xs font-medium text-gray-500 dark:text-gray-400 w-28">Jumlah</th>
                                <th class="w-10"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            @foreach($cartBahan as $idx => $bahan)
                            <tr wire:key="bahan-{{ $idx }}">
                                <td class="px-4 py-2">
                                    <p class="font-medium text-gray-900 dark:text-gray-100">{{ $bahan['nama'] }}</p>
                                    <p class="text-xs text-gray-400 font-mono">{{ $bahan['kode'] }}</p>
                                </td>
                                <td class="px-4 py-2">
                                    <div class="flex items-center gap-1">
                                        <input wire:model="cartBahan.{{ $idx }}.jumlah"
                                               type="number" min="0.1" step="0.1"
                                               class="form-input w-20 text-xs py-1 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200"/>
                                        <span class="text-xs text-gray-400">{{ $bahan['satuan'] }}</span>
                                    </div>
                                </td>
                                <td class="px-2 py-2">
                                    <button type="button" wire:click="removeBahan({{ $idx }})"
                                            class="text-red-400 hover:text-red-600 transition-colors">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                        </svg>
                                    </button>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @else
                <p class="text-sm text-gray-400 text-center py-4 border border-dashed border-gray-300 dark:border-gray-600 rounded-xl">
                    Belum ada bahan. Cari dan tambahkan bahan di atas.
                </p>
                @endif
            </div>

            <div class="flex justify-end">
                <button type="button" wire:click="submitRacikan"
                        class="btn-primary flex items-center gap-2"
                        wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="submitRacikan">
                        <svg class="w-4 h-4 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
                        </svg>
                        Simpan Racikan
                    </span>
                    <span wire:loading wire:target="submitRacikan" class="flex items-center gap-2">
                        <div class="spinner h-4 w-4 border-white border-t-transparent"></div>
                        Menyimpan...
                    </span>
                </button>
            </div>
        </div>
    </div>
    @endif
    @endif {{-- end !isLocked --}}

    {{-- ═══ RIWAYAT RESEP ═══ --}}
    @if($this->resep)
    <div class="space-y-4">

        {{-- Obat Jadi --}}
        @if($this->resep->itemResep->count() > 0)
        <div class="card">
            <div class="card-header flex items-center justify-between">
                <h3 class="text-sm font-semibold dark:text-white">Obat Jadi dalam Resep</h3>
                @if($this->isLocked)
                <span class="badge badge-success text-xs">Dikonfirmasi</span>
                @else
                <span class="badge badge-warning text-xs">Menunggu Farmasi</span>
                @endif
            </div>
            <div class="table-wrapper rounded-t-none">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Obat</th>
                            <th>Jumlah</th>
                            <th>Signa / Aturan Pakai</th>
                            <th>Harga</th>
                            @if(!$this->isLocked)<th class="w-16">Aksi</th>@endif
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($this->resep->itemResep as $item)
                        <tr wire:key="item-{{ $item->id }}">
                            <td>
                                <p class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $item->obat?->nama }}</p>
                                <p class="text-xs text-gray-400 font-mono">{{ $item->obat?->kode }}</p>
                            </td>
                            <td class="text-sm text-gray-700 dark:text-gray-300">
                                {{ $item->jumlah }} {{ $item->obat?->satuan }}
                            </td>
                            <td class="text-sm text-gray-600 dark:text-gray-400">{{ $item->aturan_pakai ?: '—' }}</td>
                            <td class="text-sm text-gray-700 dark:text-gray-300 font-mono">
                                Rp {{ number_format(($item->obat?->harga ?? 0) * $item->jumlah, 0, ',', '.') }}
                            </td>
                            @if(!$this->isLocked)
                            <td>
                                <x-confirm-button
                                    :action="'batalkanItem(' . $item->id . ')'"
                                    title="Hapus Item Resep?"
                                    :text="'Hapus ' . ($item->obat?->nama ?? 'item') . ' dari resep?'"
                                    confirm="Ya, Hapus"
                                    type="danger"
                                    class="btn-danger btn-xs">
                                    Hapus
                                </x-confirm-button>
                            </td>
                            @endif
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endif

        {{-- Racikan --}}
        @foreach($this->resep->racikan as $racikan)
        <div class="card" wire:key="racikan-{{ $racikan->id }}">
            <div class="card-header flex items-center justify-between">
                <div>
                    <h3 class="text-sm font-semibold dark:text-white">
                        {{ $racikan->nama_racikan }}
                        <span class="ml-2 badge badge-purple text-xs capitalize">{{ $racikan->metode }}</span>
                    </h3>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                        {{ $racikan->jumlah_sediaan }} sediaan
                        @if($racikan->aturan_pakai) · {{ $racikan->aturan_pakai }} @endif
                    </p>
                </div>
                @if(!$this->isLocked)
                <x-confirm-button
                    :action="'batalkanRacikan(' . $racikan->id . ')'"
                    title="Hapus Racikan?"
                    :text="'Hapus racikan ' . $racikan->nama_racikan . '?'"
                    confirm="Ya, Hapus"
                    type="danger"
                    class="btn-danger btn-xs">
                    Hapus
                </x-confirm-button>
                @endif
            </div>
            <div class="card-body p-0">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Bahan</th>
                            <th>Jumlah</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($racikan->bahanRacikan as $bahan)
                        <tr wire:key="bahan-{{ $bahan->id }}">
                            <td>
                                <p class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $bahan->obat?->nama }}</p>
                                <p class="text-xs text-gray-400 font-mono">{{ $bahan->obat?->kode }}</p>
                            </td>
                            <td class="text-sm text-gray-700 dark:text-gray-300">
                                {{ $bahan->jumlah }} {{ $bahan->satuan ?? $bahan->obat?->satuan }}
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endforeach

        @if($this->resep->itemResep->count() === 0 && $this->resep->racikan->count() === 0)
        <div class="card">
            <div class="card-body py-8">
                <div class="empty-state">
                    <p class="empty-state-text text-sm">Resep masih kosong</p>
                </div>
            </div>
        </div>
        @endif
    </div>
    @else
    <div class="card">
        <div class="card-body py-10">
            <div class="empty-state">
                <svg class="empty-state-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                          d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                <p class="empty-state-text text-sm">Belum ada resep untuk kunjungan ini</p>
                <p class="text-xs text-gray-400 mt-1">Tambahkan obat atau racikan menggunakan form di atas</p>
            </div>
        </div>
    </div>
    @endif
</div>
