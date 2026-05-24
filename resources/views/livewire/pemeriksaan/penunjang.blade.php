<div>
    {{-- ═══ TAB NAVIGATOR ═══ --}}
    <div class="flex gap-1 mb-4 border-b border-gray-200 dark:border-gray-700">
        <button type="button" wire:click="$set('activeTab', 'lab')"
                @class([
                    'px-5 py-2.5 text-sm font-medium border-b-2 -mb-px transition-colors flex items-center gap-2',
                    'border-[#0a3d62] text-[#0a3d62] dark:border-blue-400 dark:text-blue-400' => $activeTab === 'lab',
                    'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200' => $activeTab !== 'lab',
                ])>
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"/>
            </svg>
            Laboratorium
            @if(count($cartLab) > 0)
            <span class="inline-flex items-center justify-center w-5 h-5 text-xs rounded-full bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300 font-bold">{{ count($cartLab) }}</span>
            @endif
        </button>

        <button type="button" wire:click="$set('activeTab', 'radiologi')"
                @class([
                    'px-5 py-2.5 text-sm font-medium border-b-2 -mb-px transition-colors flex items-center gap-2',
                    'border-[#0a3d62] text-[#0a3d62] dark:border-blue-400 dark:text-blue-400' => $activeTab === 'radiologi',
                    'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200' => $activeTab !== 'radiologi',
                ])>
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M9 3H5a2 2 0 00-2 2v4m6-6h10a2 2 0 012 2v4M9 3v18m0 0h10a2 2 0 002-2V9M9 21H5a2 2 0 01-2-2V9m0 0h18"/>
            </svg>
            Radiologi
            @if(count($cartRad) > 0)
            <span class="inline-flex items-center justify-center w-5 h-5 text-xs rounded-full bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300 font-bold">{{ count($cartRad) }}</span>
            @endif
        </button>
    </div>

    {{-- ═══════════════════════════════════════════════════
         TAB: LABORATORIUM
    ════════════════════════════════════════════════════ --}}
    @if($activeTab === 'lab')

    {{-- Search & Autocomplete --}}
    <div class="card mb-4">
        <div class="card-header">
            <h3 class="text-sm font-semibold dark:text-white">Tambah Permintaan Laboratorium</h3>
        </div>
        <div class="card-body">
            <div x-data="{ open: false }" class="relative">
                <label class="form-label dark:text-gray-300">Cari Pemeriksaan Lab</label>
                <div class="relative">
                    <input wire:model.live.debounce.300ms="searchLab"
                           @focus="open = true"
                           @click.away="open = false"
                           type="text"
                           placeholder="Ketik nama atau kode (min. 2 karakter)..."
                           class="form-input pr-10 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200"/>
                    <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none">
                        <svg wire:loading wire:target="searchLab" class="animate-spin h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                        </svg>
                    </div>
                </div>

                {{-- Dropdown Suggestions --}}
                @if(strlen($searchLab) >= 2)
                <div x-show="open" x-transition
                     class="absolute z-50 w-full mt-1 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-600 rounded-xl shadow-lg max-h-60 overflow-y-auto">
                    @forelse($this->suggestionsLab as $item)
                    <button type="button"
                            wire:click="addToCartLab({{ $item->id }}, '{{ $item->kode }}', @js($item->nama), '{{ $item->tarif }}')"
                            @click="open = false"
                            class="w-full flex items-center justify-between px-4 py-2.5 text-sm hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors text-left">
                        <div>
                            <span class="font-medium text-gray-900 dark:text-gray-100">{{ $item->nama }}</span>
                            <span class="ml-2 text-xs text-gray-400 font-mono">{{ $item->kode }}</span>
                        </div>
                        <span class="text-xs text-blue-600 dark:text-blue-400 font-semibold ml-4 flex-shrink-0">
                            Rp {{ number_format($item->tarif, 0, ',', '.') }}
                        </span>
                    </button>
                    @empty
                    <div class="px-4 py-3 text-sm text-gray-400 text-center">Tidak ada item ditemukan</div>
                    @endforelse
                </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Cart: Daftar Order Lab (belum submit) --}}
    @if(count($cartLab) > 0)
    <div class="card mb-4">
        <div class="card-header flex items-center justify-between">
            <h3 class="text-sm font-semibold dark:text-white">
                Daftar Order Lab
                <span class="ml-1 text-xs font-normal text-gray-400">(belum terkirim)</span>
            </h3>
            <span class="text-xs text-amber-600 dark:text-amber-400 font-medium flex items-center gap-1">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                </svg>
                Klik "Kirim Order" untuk mengirim ke unit Lab
            </span>
        </div>
        <div class="card-body p-0">
            <div class="divide-y divide-gray-100 dark:divide-gray-700">
                @foreach($cartLab as $idx => $item)
                <div wire:key="cart-lab-{{ $idx }}" class="flex flex-col sm:flex-row gap-3 items-start sm:items-center px-4 py-3">
                    {{-- Info item --}}
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $item['nama'] }}</p>
                        <p class="text-xs text-gray-400 font-mono">{{ $item['kode'] }} · Rp {{ number_format($item['tarif'], 0, ',', '.') }}</p>
                    </div>

                    {{-- Prioritas --}}
                    <div class="flex items-center gap-2">
                        <span class="text-xs text-gray-500 dark:text-gray-400 flex-shrink-0">Prioritas:</span>
                        <label class="inline-flex items-center gap-1 text-sm cursor-pointer">
                            <input type="radio" wire:model="cartLab.{{ $idx }}.prioritas" value="normal"
                                   class="text-blue-600">
                            <span class="text-gray-700 dark:text-gray-300">Normal</span>
                        </label>
                        <label class="inline-flex items-center gap-1 text-sm cursor-pointer">
                            <input type="radio" wire:model="cartLab.{{ $idx }}.prioritas" value="cito"
                                   class="text-red-600">
                            <span class="text-red-600 font-semibold">CITO</span>
                        </label>
                    </div>

                    {{-- Catatan --}}
                    <div class="w-full sm:w-48">
                        <input wire:model="cartLab.{{ $idx }}.catatan"
                               type="text"
                               placeholder="Catatan klinis..."
                               class="form-input text-xs py-1.5 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200"/>
                    </div>

                    {{-- Hapus --}}
                    <button type="button" wire:click="removeFromCartLab({{ $idx }})"
                            class="text-red-400 hover:text-red-600 flex-shrink-0 transition-colors">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                        </svg>
                    </button>
                </div>
                @endforeach
            </div>

            {{-- Total & Submit --}}
            <div class="px-4 py-3 bg-gray-50 dark:bg-gray-700/50 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 border-t border-gray-100 dark:border-gray-700">
                @php
                    $totalLab = collect($cartLab)->sum(fn ($i) => (float) $i['tarif']);
                @endphp
                <div class="text-sm text-gray-600 dark:text-gray-400">
                    <strong class="text-gray-900 dark:text-gray-100">{{ count($cartLab) }}</strong> item ·
                    Total estimasi: <strong class="text-blue-700 dark:text-blue-400">Rp {{ number_format($totalLab, 0, ',', '.') }}</strong>
                </div>
                <button type="button" wire:click="submitLab"
                        class="btn-primary flex items-center gap-2"
                        wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="submitLab">
                        <svg class="w-4 h-4 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
                        </svg>
                        Kirim Order Lab
                    </span>
                    <span wire:loading wire:target="submitLab" class="flex items-center gap-2">
                        <div class="spinner h-4 w-4 border-white border-t-transparent"></div>
                        Mengirim...
                    </span>
                </button>
            </div>
        </div>
    </div>
    @endif

    {{-- Riwayat Order Lab --}}
    <div class="card">
        <div class="card-header">
            <h3 class="text-sm font-semibold dark:text-white">Riwayat Order Laboratorium</h3>
        </div>
        <div class="table-wrapper rounded-t-none">
            <table class="table">
                <thead>
                    <tr>
                        <th>Pemeriksaan</th>
                        <th>Prioritas</th>
                        <th>Catatan</th>
                        <th>Tarif</th>
                        <th>Status</th>
                        <th>Hasil</th>
                        <th class="w-24">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($this->ordersLab as $order)
                    <tr wire:key="order-lab-{{ $order->id }}">
                        <td>
                            <p class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $order->itemPenunjang?->nama }}</p>
                            <p class="text-xs text-gray-400 font-mono">{{ $order->itemPenunjang?->kode }} · {{ $order->created_at->format('d/m/Y H:i') }}</p>
                        </td>
                        <td>
                            @if($order->prioritas === 'cito')
                            <span class="badge badge-danger text-xs font-bold">CITO</span>
                            @else
                            <span class="badge badge-gray text-xs">Normal</span>
                            @endif
                        </td>
                        <td class="text-sm text-gray-600 dark:text-gray-400 max-w-xs truncate">{{ $order->catatan ?: '—' }}</td>
                        <td class="text-sm text-gray-700 dark:text-gray-300 font-mono">
                            Rp {{ number_format($order->itemPenunjang?->tarif ?? 0, 0, ',', '.') }}
                        </td>
                        <td>
                            @php
                                $badge = match($order->status) {
                                    'dipesan'    => ['label' => 'Dipesan',    'class' => 'badge-warning'],
                                    'diproses'   => ['label' => 'Diproses',   'class' => 'badge-primary'],
                                    'selesai'    => ['label' => 'Selesai',    'class' => 'badge-success'],
                                    'dibatalkan' => ['label' => 'Dibatalkan', 'class' => 'badge-danger'],
                                    default      => ['label' => ucfirst($order->status), 'class' => 'badge-gray'],
                                };
                            @endphp
                            <span class="badge {{ $badge['class'] }} text-xs">{{ $badge['label'] }}</span>
                        </td>
                        <td>
                            @if($order->hasil_url)
                            <a href="{{ $order->hasil_url }}" target="_blank"
                               class="text-blue-600 hover:underline text-xs font-medium">Lihat Hasil</a>
                            @else
                            <span class="text-xs text-gray-400">—</span>
                            @endif
                        </td>
                        <td>
                            @if($order->status === 'dipesan')
                            <x-confirm-button
                                :action="'batalkan(' . $order->id . ')'"
                                title="Batalkan Order?"
                                text="Order {{ $order->itemPenunjang?->nama }} akan dibatalkan."
                                confirm="Ya, Batalkan"
                                type="danger"
                                class="btn-danger btn-xs">
                                Batal
                            </x-confirm-button>
                            @else
                            <span class="text-xs text-gray-300">—</span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7">
                            <div class="empty-state py-8">
                                <p class="empty-state-text text-sm">Belum ada order laboratorium</p>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- ═══════════════════════════════════════════════════
         TAB: RADIOLOGI
    ════════════════════════════════════════════════════ --}}
    @elseif($activeTab === 'radiologi')

    {{-- Search & Autocomplete --}}
    <div class="card mb-4">
        <div class="card-header">
            <h3 class="text-sm font-semibold dark:text-white">Tambah Permintaan Radiologi</h3>
        </div>
        <div class="card-body">
            <div x-data="{ open: false }" class="relative">
                <label class="form-label dark:text-gray-300">Cari Tindakan Radiologi</label>
                <div class="relative">
                    <input wire:model.live.debounce.300ms="searchRad"
                           @focus="open = true"
                           @click.away="open = false"
                           type="text"
                           placeholder="Ketik nama atau kode (min. 2 karakter)..."
                           class="form-input pr-10 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200"/>
                    <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none">
                        <svg wire:loading wire:target="searchRad" class="animate-spin h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                        </svg>
                    </div>
                </div>

                @if(strlen($searchRad) >= 2)
                <div x-show="open" x-transition
                     class="absolute z-50 w-full mt-1 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-600 rounded-xl shadow-lg max-h-60 overflow-y-auto">
                    @forelse($this->suggestionsRad as $item)
                    <button type="button"
                            wire:click="addToCartRad({{ $item->id }}, '{{ $item->kode }}', @js($item->nama), '{{ $item->tarif }}')"
                            @click="open = false"
                            class="w-full flex items-center justify-between px-4 py-2.5 text-sm hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors text-left">
                        <div>
                            <span class="font-medium text-gray-900 dark:text-gray-100">{{ $item->nama }}</span>
                            <span class="ml-2 text-xs text-gray-400 font-mono">{{ $item->kode }}</span>
                        </div>
                        <span class="text-xs text-blue-600 dark:text-blue-400 font-semibold ml-4 flex-shrink-0">
                            Rp {{ number_format($item->tarif, 0, ',', '.') }}
                        </span>
                    </button>
                    @empty
                    <div class="px-4 py-3 text-sm text-gray-400 text-center">Tidak ada item ditemukan</div>
                    @endforelse
                </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Cart: Daftar Order Radiologi (belum submit) --}}
    @if(count($cartRad) > 0)
    <div class="card mb-4">
        <div class="card-header flex items-center justify-between">
            <h3 class="text-sm font-semibold dark:text-white">
                Daftar Order Radiologi
                <span class="ml-1 text-xs font-normal text-gray-400">(belum terkirim)</span>
            </h3>
            <span class="text-xs text-amber-600 dark:text-amber-400 font-medium flex items-center gap-1">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                </svg>
                Klik "Kirim Order" untuk mengirim ke unit Radiologi
            </span>
        </div>
        <div class="card-body p-0">
            <div class="divide-y divide-gray-100 dark:divide-gray-700">
                @foreach($cartRad as $idx => $item)
                <div wire:key="cart-rad-{{ $idx }}" class="flex flex-col sm:flex-row gap-3 items-start sm:items-center px-4 py-3">
                    {{-- Info item --}}
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $item['nama'] }}</p>
                        <p class="text-xs text-gray-400 font-mono">{{ $item['kode'] }} · Rp {{ number_format($item['tarif'], 0, ',', '.') }}</p>
                    </div>

                    {{-- Lokasi Tubuh --}}
                    <div class="w-full sm:w-40">
                        <input wire:model="cartRad.{{ $idx }}.lokasi_tubuh"
                               type="text"
                               placeholder="Lokasi tubuh..."
                               class="form-input text-xs py-1.5 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200"/>
                    </div>

                    {{-- Indikasi Klinis --}}
                    <div class="w-full sm:w-56">
                        <input wire:model="cartRad.{{ $idx }}.indikasi_klinis"
                               type="text"
                               placeholder="Indikasi klinis..."
                               class="form-input text-xs py-1.5 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200"/>
                    </div>

                    {{-- Hapus --}}
                    <button type="button" wire:click="removeFromCartRad({{ $idx }})"
                            class="text-red-400 hover:text-red-600 flex-shrink-0 transition-colors">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                        </svg>
                    </button>
                </div>
                @endforeach
            </div>

            {{-- Total & Submit --}}
            <div class="px-4 py-3 bg-gray-50 dark:bg-gray-700/50 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 border-t border-gray-100 dark:border-gray-700">
                @php
                    $totalRad = collect($cartRad)->sum(fn ($i) => (float) $i['tarif']);
                @endphp
                <div class="text-sm text-gray-600 dark:text-gray-400">
                    <strong class="text-gray-900 dark:text-gray-100">{{ count($cartRad) }}</strong> item ·
                    Total estimasi: <strong class="text-blue-700 dark:text-blue-400">Rp {{ number_format($totalRad, 0, ',', '.') }}</strong>
                </div>
                <button type="button" wire:click="submitRad"
                        class="btn-primary flex items-center gap-2"
                        wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="submitRad">
                        <svg class="w-4 h-4 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
                        </svg>
                        Kirim Order Radiologi
                    </span>
                    <span wire:loading wire:target="submitRad" class="flex items-center gap-2">
                        <div class="spinner h-4 w-4 border-white border-t-transparent"></div>
                        Mengirim...
                    </span>
                </button>
            </div>
        </div>
    </div>
    @endif

    {{-- Riwayat Order Radiologi --}}
    <div class="card">
        <div class="card-header">
            <h3 class="text-sm font-semibold dark:text-white">Riwayat Order Radiologi</h3>
        </div>
        <div class="table-wrapper rounded-t-none">
            <table class="table">
                <thead>
                    <tr>
                        <th>Tindakan</th>
                        <th>Lokasi Tubuh</th>
                        <th>Indikasi Klinis</th>
                        <th>Tarif</th>
                        <th>Status</th>
                        <th>Hasil</th>
                        <th class="w-24">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($this->ordersRad as $order)
                    <tr wire:key="order-rad-{{ $order->id }}">
                        <td>
                            <p class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $order->itemPenunjang?->nama }}</p>
                            <p class="text-xs text-gray-400 font-mono">{{ $order->itemPenunjang?->kode }} · {{ $order->created_at->format('d/m/Y H:i') }}</p>
                        </td>
                        <td class="text-sm text-gray-600 dark:text-gray-400">{{ $order->lokasi_tubuh ?: '—' }}</td>
                        <td class="text-sm text-gray-600 dark:text-gray-400 max-w-xs truncate">{{ $order->indikasi_klinis ?: '—' }}</td>
                        <td class="text-sm text-gray-700 dark:text-gray-300 font-mono">
                            Rp {{ number_format($order->itemPenunjang?->tarif ?? 0, 0, ',', '.') }}
                        </td>
                        <td>
                            @php
                                $badge = match($order->status) {
                                    'dipesan'    => ['label' => 'Dipesan',    'class' => 'badge-warning'],
                                    'diproses'   => ['label' => 'Diproses',   'class' => 'badge-primary'],
                                    'selesai'    => ['label' => 'Selesai',    'class' => 'badge-success'],
                                    'dibatalkan' => ['label' => 'Dibatalkan', 'class' => 'badge-danger'],
                                    default      => ['label' => ucfirst($order->status), 'class' => 'badge-gray'],
                                };
                            @endphp
                            <span class="badge {{ $badge['class'] }} text-xs">{{ $badge['label'] }}</span>
                        </td>
                        <td>
                            @if($order->hasil_url)
                            <a href="{{ $order->hasil_url }}" target="_blank"
                               class="text-blue-600 hover:underline text-xs font-medium">Lihat Hasil</a>
                            @else
                            <span class="text-xs text-gray-400">—</span>
                            @endif
                        </td>
                        <td>
                            @if($order->status === 'dipesan')
                            <x-confirm-button
                                :action="'batalkan(' . $order->id . ')'"
                                title="Batalkan Order?"
                                text="Order {{ $order->itemPenunjang?->nama }} akan dibatalkan."
                                confirm="Ya, Batalkan"
                                type="danger"
                                class="btn-danger btn-xs">
                                Batal
                            </x-confirm-button>
                            @else
                            <span class="text-xs text-gray-300">—</span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7">
                            <div class="empty-state py-8">
                                <p class="empty-state-text text-sm">Belum ada order radiologi</p>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @endif
</div>
