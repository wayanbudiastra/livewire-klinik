<div>
    <div class="mb-4 flex flex-col sm:flex-row gap-3 justify-between">
        <div class="relative">
            <span class="absolute inset-y-0 left-3 flex items-center text-gray-400 pointer-events-none">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 11A6 6 0 1 1 5 11a6 6 0 0 1 12 0z"/>
                </svg>
            </span>
            <input wire:model.live.debounce.400ms="search" type="text"
                   placeholder="Cari kode / nama..."
                   class="form-input pl-9 w-64 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200"/>
        </div>

        @can('masterdata.create')
        <button wire:click="$dispatch('open-penunjang-create', { kategori: '{{ $kategori }}' })"
                class="btn-primary whitespace-nowrap">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            Tambah {{ $kategori === 'lab' ? 'Item Lab' : 'Item Radiologi' }}
        </button>
        @endcan
    </div>

    <div class="table-wrapper">
        <table class="table">
            <thead>
                <tr>
                    <th>Kode</th>
                    <th>Nama Item</th>
                    <th>Tarif</th>
                    <th>Tarif BPJS</th>
                    <th>Waktu</th>
                    <th>Status</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($this->items as $item)
                <tr wire:key="p-{{ $item->id }}">
                    <td class="font-mono text-xs text-gray-500 dark:text-gray-400">{{ $item->kode }}</td>
                    <td>
                        <p class="font-medium text-gray-900 dark:text-gray-100">{{ $item->nama }}</p>
                        @if ($item->deskripsi)
                            <p class="text-xs text-gray-400">{{ $item->deskripsi }}</p>
                        @endif
                    </td>
                    <td>Rp {{ number_format($item->tarif, 0, ',', '.') }}</td>
                    <td class="text-gray-500">
                        {{ $item->tarif_bpjs ? 'Rp '.number_format($item->tarif_bpjs, 0, ',', '.') : '-' }}
                    </td>
                    <td class="text-xs text-gray-500">{{ $item->satuan_waktu ?? '-' }}</td>
                    <td>
                        @can('masterdata.edit')
                        <button
                            wire:click="toggleAktif({{ $item->id }})"
                            wire:confirm="{{ $item->is_active ? 'Nonaktifkan' : 'Aktifkan' }} item ini?"
                            @class([
                                'inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium transition-colors',
                                'bg-emerald-100 text-emerald-700 hover:bg-emerald-200 dark:bg-emerald-900/40 dark:text-emerald-300' => $item->is_active,
                                'bg-red-100 text-red-700 hover:bg-red-200 dark:bg-red-900/40 dark:text-red-300'                    => !$item->is_active,
                            ])>
                            <span class="h-1.5 w-1.5 rounded-full {{ $item->is_active ? 'bg-emerald-500' : 'bg-red-500' }}"></span>
                            {{ $item->is_active ? 'Aktif' : 'Nonaktif' }}
                        </button>
                        @else
                        <span @class(['badge', 'badge-success' => $item->is_active, 'badge-danger' => !$item->is_active])>
                            {{ $item->is_active ? 'Aktif' : 'Nonaktif' }}
                        </span>
                        @endcan
                    </td>
                    <td>
                        @can('masterdata.edit')
                        <button wire:click="$dispatch('open-penunjang-edit', { id: {{ $item->id }} })"
                                class="btn-info btn-sm">Edit</button>
                        @endcan
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7">
                        <div class="empty-state">
                            <p class="empty-state-text">Belum ada item {{ $kategori === 'lab' ? 'laboratorium' : 'radiologi' }}</p>
                        </div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $this->items->links() }}</div>
</div>
