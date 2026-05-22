<div>
    {{-- Toolbar --}}
    <div class="mb-4 flex flex-col sm:flex-row gap-3 justify-between">
        <div class="flex flex-wrap gap-2">
            <div class="relative">
                <span class="absolute inset-y-0 left-3 flex items-center text-gray-400 pointer-events-none">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 11A6 6 0 1 1 5 11a6 6 0 0 1 12 0z"/>
                    </svg>
                </span>
                <input wire:model.live.debounce.400ms="search" type="text"
                       placeholder="Nama, kode, barcode..."
                       class="form-input pl-9 w-64 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200"/>
            </div>
            <select wire:model.live="filterJenis"
                    class="form-select w-36 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200">
                <option value="">Semua Jenis</option>
                <option value="obat">Obat</option>
                <option value="alkes">Alkes</option>
            </select>
            <select wire:model.live="filterStatus"
                    class="form-select w-36 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200">
                <option value="aktif">Aktif</option>
                <option value="nonaktif">Non-Aktif</option>
                <option value="">Semua</option>
            </select>
            <label class="flex items-center gap-2 cursor-pointer text-sm text-gray-600 dark:text-gray-400">
                <input type="checkbox" wire:model.live="filterReorder" class="form-checkbox"/>
                🔔 Reorder saja
            </label>
        </div>

        @can('obat.create')
        <button wire:click="$dispatch('open-obat-create')" class="btn-primary whitespace-nowrap">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            Tambah Obat/Alkes
        </button>
        @endcan
    </div>

    <div wire:loading.delay class="mb-2 text-sm text-gray-400 flex items-center gap-2">
        <div class="spinner"></div> Memuat...
    </div>

    <div class="table-wrapper">
        <table class="table">
            <thead>
                <tr>
                    <th>Kode</th>
                    <th>Nama / Generik</th>
                    <th>Jenis</th>
                    <th>Satuan</th>
                    <th>Stok</th>
                    <th>Harga Umum</th>
                    <th>Harga BPJS</th>
                    <th>Status</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($this->obat as $o)
                <tr wire:key="obat-{{ $o->id }}">
                    <td>
                        <p class="font-mono text-xs font-semibold text-gray-700 dark:text-gray-300">{{ $o->kode }}</p>
                        @if ($o->barcode)
                        <p class="font-mono text-xs text-gray-400">{{ $o->barcode }}</p>
                        @endif
                    </td>
                    <td>
                        <p class="font-medium text-gray-900 dark:text-gray-100">{{ $o->nama }}</p>
                        @if ($o->generik)
                        <p class="text-xs text-gray-400 italic">{{ $o->generik }}</p>
                        @endif
                    </td>
                    <td>
                        <span @class([
                            'badge',
                            'badge-primary' => $o->jenis_barang === 'obat',
                            'badge-info'    => $o->jenis_barang === 'alkes',
                        ])>{{ ucfirst($o->jenis_barang) }}</span>
                        @if ($o->is_paten)
                        <span class="badge-warning ml-1">Paten</span>
                        @endif
                    </td>
                    <td class="text-sm text-gray-600 dark:text-gray-400">
                        {{ $o->satuan }}
                        @if ($o->satuanBesar && $o->satuanKecil)
                        <p class="text-xs text-gray-400">
                            {{ $o->satuanBesar->nama }} = {{ $o->konversi }} {{ $o->satuanKecil->nama }}
                        </p>
                        @endif
                    </td>
                    <td>
                        <p class="font-semibold text-sm {{ $o->stok <= 0 ? 'text-red-600' : ($o->stok <= 10 ? 'text-amber-600' : 'text-gray-800 dark:text-gray-200') }}">
                            {{ number_format($o->stok) }}
                        </p>
                        @if ($o->stok <= 10 && $o->stok > 0)
                        <span class="text-xs text-amber-500">Hampir habis</span>
                        @elseif ($o->stok <= 0)
                        <span class="text-xs text-red-500">Habis</span>
                        @endif
                    </td>
                    <td class="text-sm text-gray-700 dark:text-gray-300">
                        Rp {{ number_format($o->harga, 0, ',', '.') }}
                    </td>
                    <td class="text-sm text-gray-500">
                        {{ $o->harga_bpjs ? 'Rp '.number_format($o->harga_bpjs, 0, ',', '.') : '-' }}
                    </td>
                    <td>
                        @can('obat.edit')
                        <x-confirm-button
                            action="toggleAktif({{ $o->id }})"
                            title="{{ $o->is_active ? 'Nonaktifkan Obat?' : 'Aktifkan Obat?' }}"
                            text="{{ $o->nama }}"
                            icon="{{ $o->is_active ? 'warning' : 'question' }}"
                            confirm="{{ $o->is_active ? 'Ya, Nonaktifkan' : 'Ya, Aktifkan' }}"
                            type="{{ $o->is_active ? 'danger' : 'success' }}"
                            @class([
                                'inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium transition-colors',
                                'bg-emerald-100 text-emerald-700 hover:bg-emerald-200 dark:bg-emerald-900/40 dark:text-emerald-300' => $o->is_active,
                                'bg-red-100 text-red-700 hover:bg-red-200 dark:bg-red-900/40 dark:text-red-300' => !$o->is_active,
                            ])>
                            <span class="h-1.5 w-1.5 rounded-full {{ $o->is_active ? 'bg-emerald-500' : 'bg-red-500' }}"></span>
                            {{ $o->is_active ? 'Aktif' : 'Nonaktif' }}
                        </x-confirm-button>
                        @endcan
                    </td>
                    <td>
                        @can('obat.edit')
                        <button wire:click="$dispatch('open-obat-edit', { id: {{ $o->id }} })"
                                class="btn-warning btn-sm">Edit</button>
                        @endcan
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="9">
                        <div class="empty-state">
                            <svg class="empty-state-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                      d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"/>
                            </svg>
                            <p class="empty-state-text">Tidak ada data obat/alkes</p>
                        </div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4 flex items-center justify-between text-sm text-gray-500 dark:text-gray-400">
        @if ($this->obat->total() > 0)
        <span>{{ $this->obat->total() }} item ditemukan</span>
        {{ $this->obat->links() }}
        @endif
    </div>
</div>
