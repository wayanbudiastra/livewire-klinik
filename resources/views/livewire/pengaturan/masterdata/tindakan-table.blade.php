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
                       placeholder="Cari kode / nama tindakan..."
                       class="form-input pl-9 w-64 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200"/>
            </div>
            <select wire:model.live="filterPoli"
                    class="form-select w-44 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200">
                <option value="">Semua Poli</option>
                @foreach (\App\Models\Poli::aktif()->orderBy('nama')->get() as $p)
                    <option value="{{ $p->id }}">{{ $p->nama }}</option>
                @endforeach
            </select>
        </div>

        @can('masterdata.create')
        <button wire:click="$dispatch('open-tindakan-create')" class="btn-primary whitespace-nowrap">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            Tambah Tindakan
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
                    <th>Nama Tindakan</th>
                    <th>Tarif</th>
                    <th>Tarif BPJS</th>
                    <th>Poli</th>
                    <th>Status</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($this->tindakan as $item)
                <tr wire:key="t-{{ $item->id }}">
                    <td class="font-mono text-xs text-gray-500 dark:text-gray-400">{{ $item->kode }}</td>
                    <td>
                        <p class="font-medium text-gray-900 dark:text-gray-100">{{ $item->nama }}</p>
                        @if ($item->deskripsi)
                            <p class="text-xs text-gray-400">{{ $item->deskripsi }}</p>
                        @endif
                    </td>
                    <td class="text-sm">Rp {{ number_format($item->tarif, 0, ',', '.') }}</td>
                    <td class="text-sm text-gray-500">
                        {{ $item->tarif_bpjs ? 'Rp '.number_format($item->tarif_bpjs, 0, ',', '.') : '-' }}
                    </td>
                    <td>
                        <div class="flex flex-wrap gap-1">
                            @foreach ($item->poli->take(3) as $poli)
                                <span class="badge-primary">{{ $poli->kode }}</span>
                            @endforeach
                            @if ($item->poli->count() > 3)
                                <span class="badge-gray">+{{ $item->poli->count() - 3 }}</span>
                            @endif
                            @if ($item->poli->isEmpty())
                                <span class="badge-danger">Belum dipetakan</span>
                            @endif
                        </div>
                    </td>
                    <td>
                        <button wire:click="toggleAktif({{ $item->id }})"
                                wire:confirm="{{ $item->is_active ? 'Nonaktifkan' : 'Aktifkan' }} tindakan ini?"
                                @class([
                                    'inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium transition-colors',
                                    'bg-emerald-100 text-emerald-700 hover:bg-emerald-200 dark:bg-emerald-900/40 dark:text-emerald-300' => $item->is_active,
                                    'bg-red-100 text-red-700 hover:bg-red-200 dark:bg-red-900/40 dark:text-red-300' => !$item->is_active,
                                ])>
                            <span class="h-1.5 w-1.5 rounded-full {{ $item->is_active ? 'bg-emerald-500' : 'bg-red-500' }}"></span>
                            {{ $item->is_active ? 'Aktif' : 'Nonaktif' }}
                        </button>
                    </td>
                    <td>
                        <div class="flex items-center gap-1">
                            @can('masterdata.edit')
                            <button wire:click="$dispatch('open-tindakan-edit', { id: {{ $item->id }} })"
                                    class="btn-info btn-sm">Edit</button>
                            @endcan
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7">
                        <div class="empty-state">
                            <svg class="empty-state-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                      d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                            </svg>
                            <p class="empty-state-text">Belum ada tindakan</p>
                        </div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $this->tindakan->links() }}</div>
</div>
