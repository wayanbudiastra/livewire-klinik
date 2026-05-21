<div>
    <div class="mb-4 flex flex-col sm:flex-row gap-3 justify-between">
        <div class="flex flex-wrap gap-2">
            <div class="relative">
                <span class="absolute inset-y-0 left-3 flex items-center text-gray-400 pointer-events-none">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 11A6 6 0 1 1 5 11a6 6 0 0 1 12 0z"/>
                    </svg>
                </span>
                <input wire:model.live.debounce.400ms="search" type="text"
                       placeholder="Cari peralatan..."
                       class="form-input pl-9 w-52 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200"/>
            </div>

            <select wire:model.live="filterStatus"
                    class="form-select w-36 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200">
                <option value="">Semua Status</option>
                <option value="tersedia">Tersedia</option>
                <option value="digunakan">Digunakan</option>
                <option value="maintenance">Maintenance</option>
                <option value="rusak">Rusak</option>
            </select>

            <select wire:model.live="filterAktif"
                    class="form-select w-32 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200">
                <option value="">Semua</option>
                <option value="1">Aktif</option>
                <option value="0">Nonaktif</option>
            </select>
        </div>

        @can('masterdata.create')
        <button wire:click="$dispatch('open-peralatan-create')" class="btn-primary whitespace-nowrap">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            Tambah Peralatan
        </button>
        @endcan
    </div>

    <div class="table-wrapper">
        <table class="table">
            <thead>
                <tr>
                    <th>Kode</th>
                    <th>Nama Peralatan</th>
                    <th>Merk / No. Seri</th>
                    <th>Status Kondisi</th>
                    <th>Aktif</th>
                    <th>Poli Terakhir</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($this->peralatan as $alat)
                <tr wire:key="a-{{ $alat->id }}">
                    <td class="font-mono text-xs text-gray-500 dark:text-gray-400">{{ $alat->kode }}</td>
                    <td>
                        <p class="font-medium text-gray-900 dark:text-gray-100">{{ $alat->nama }}</p>
                        @if ($alat->deskripsi)
                            <p class="text-xs text-gray-400">{{ $alat->deskripsi }}</p>
                        @endif
                    </td>
                    <td class="text-sm text-gray-600 dark:text-gray-400">
                        <p>{{ $alat->merk ?? '-' }}</p>
                        @if ($alat->nomor_seri)
                            <p class="text-xs font-mono text-gray-400">{{ $alat->nomor_seri }}</p>
                        @endif
                    </td>

                    {{-- Status Kondisi --}}
                    <td>
                        @can('masterdata.edit')
                        <select wire:change="updateStatus({{ $alat->id }}, $event.target.value)"
                                @class([
                                    'text-xs rounded-full px-2 py-1 font-medium border-0 focus:ring-1 cursor-pointer',
                                    'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300' => $alat->status === 'tersedia',
                                    'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300'           => $alat->status === 'digunakan',
                                    'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300'       => $alat->status === 'maintenance',
                                    'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300'               => $alat->status === 'rusak',
                                ])>
                            <option value="tersedia"    {{ $alat->status === 'tersedia'    ? 'selected' : '' }}>Tersedia</option>
                            <option value="digunakan"   {{ $alat->status === 'digunakan'   ? 'selected' : '' }}>Digunakan</option>
                            <option value="maintenance" {{ $alat->status === 'maintenance' ? 'selected' : '' }}>Maintenance</option>
                            <option value="rusak"       {{ $alat->status === 'rusak'       ? 'selected' : '' }}>Rusak</option>
                        </select>
                        @else
                        <span @class(['badge',
                            'badge-success' => $alat->status === 'tersedia',
                            'badge-info'    => $alat->status === 'digunakan',
                            'badge-warning' => $alat->status === 'maintenance',
                            'badge-danger'  => $alat->status === 'rusak',
                        ])>{{ ucfirst($alat->status) }}</span>
                        @endcan
                    </td>

                    {{-- Toggle Aktif/Nonaktif --}}
                    <td>
                        @can('masterdata.edit')
                        <button
                            wire:click="toggleAktif({{ $alat->id }})"
                            wire:confirm="{{ $alat->is_active ? 'Nonaktifkan' : 'Aktifkan' }} peralatan ini?"
                            @class([
                                'inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium transition-colors',
                                'bg-emerald-100 text-emerald-700 hover:bg-emerald-200 dark:bg-emerald-900/40 dark:text-emerald-300' => $alat->is_active,
                                'bg-red-100 text-red-700 hover:bg-red-200 dark:bg-red-900/40 dark:text-red-300'                    => !$alat->is_active,
                            ])>
                            <span class="h-1.5 w-1.5 rounded-full {{ $alat->is_active ? 'bg-emerald-500' : 'bg-red-500' }}"></span>
                            {{ $alat->is_active ? 'Aktif' : 'Nonaktif' }}
                        </button>
                        @else
                        <span @class(['badge', 'badge-success' => $alat->is_active, 'badge-danger' => !$alat->is_active])>
                            {{ $alat->is_active ? 'Aktif' : 'Nonaktif' }}
                        </span>
                        @endcan
                    </td>

                    <td class="text-sm text-gray-500">
                        {{ $alat->poliTerakhir ? $alat->poliTerakhir->nama : '-' }}
                    </td>

                    <td>
                        @can('masterdata.edit')
                        <button wire:click="$dispatch('open-peralatan-edit', { id: {{ $alat->id }} })"
                                class="btn-info btn-sm">Edit</button>
                        @endcan
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7">
                        <div class="empty-state">
                            <p class="empty-state-text">Belum ada peralatan medis</p>
                        </div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $this->peralatan->links() }}</div>
</div>
