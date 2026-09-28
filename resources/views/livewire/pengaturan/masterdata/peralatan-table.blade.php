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

        <div class="flex flex-wrap gap-2">
            @can('masterdata.view')
            <a href="{{ route('pengaturan.masterdata.peralatan.template') }}" class="btn-secondary whitespace-nowrap">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                </svg>
                Download Template
            </a>
            @endcan
            @can('masterdata.create')
            <button wire:click="openImportModal('baru')" class="btn-secondary whitespace-nowrap">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M9 19l3-3m0 0l3 3m-3-3v-9"/>
                </svg>
                Upload Data Baru
            </button>
            <button wire:click="openImportModal('update')" class="btn-secondary whitespace-nowrap">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                </svg>
                Update Data
            </button>
            <button wire:click="$dispatch('open-peralatan-create')" class="btn-primary whitespace-nowrap">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Tambah Peralatan
            </button>
            @endcan
        </div>
    </div>

    {{-- Modal Upload/Impor Data --}}
    @if ($showImportModal)
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/50" wire:click="$set('showImportModal', false)"></div>
        <div class="relative z-10 w-full max-w-lg rounded-2xl bg-white shadow-2xl dark:bg-gray-800 dark:border dark:border-gray-700 animate-fade-in">
            <div class="modal-header">
                <h3 class="modal-title dark:text-white">
                    {{ $importMode === 'update' ? 'Update Data Peralatan Medis' : 'Upload Data Baru Peralatan Medis' }}
                </h3>
                <button wire:click="$set('showImportModal', false)" class="text-gray-400 hover:text-gray-600">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
            <div class="modal-body space-y-4">
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Isi file sesuai <a href="{{ route('pengaturan.masterdata.peralatan.template') }}" class="text-primary-600 hover:underline">template XLS</a> ini.
                    @if ($importMode === 'update')
                        Baris dengan kode yang <strong>sudah terdaftar</strong> akan diupdate. Kode yang belum ada akan dilewati.
                    @else
                        Baris dengan kode <strong>baru</strong> akan ditambahkan. Kode yang sudah terdaftar akan dilewati (tidak diubah).
                    @endif
                </p>

                <div class="form-group">
                    <input type="file" wire:model="importFile" accept=".xlsx,.xls,.csv"
                           class="form-input dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200"/>
                    @error('importFile') <p class="form-error">{{ $message }}</p> @enderror
                </div>

                <div wire:loading wire:target="importFile" class="text-sm text-gray-400 flex items-center gap-2">
                    <div class="spinner"></div> Membaca file...
                </div>

                @if ($importError)
                <div class="rounded-lg bg-red-50 dark:bg-red-900/30 px-4 py-3 text-sm text-red-700 dark:text-red-300">
                    {{ $importError }}
                </div>
                @endif

                @if ($importState === 'preview')
                <div class="rounded-lg bg-blue-50 dark:bg-blue-900/30 px-4 py-3 text-sm text-blue-700 dark:text-blue-300">
                    {{ $previewRowCount }} baris data siap diimpor.
                </div>
                @endif

                @if ($importState === 'done' && $importResult)
                <div class="rounded-lg bg-emerald-50 dark:bg-emerald-900/30 px-4 py-3 text-sm text-emerald-700 dark:text-emerald-300 space-y-1">
                    <p>{{ $importResult['imported'] }} baris baru ditambahkan.</p>
                    <p>{{ $importResult['updated'] }} baris diupdate.</p>
                    @if ($importResult['skipped'] > 0)
                    <p>{{ $importResult['skipped'] }} baris dilewati.</p>
                    @endif
                </div>
                @if (!empty($importResult['errors']))
                <div class="rounded-lg bg-amber-50 dark:bg-amber-900/30 px-4 py-3 text-xs text-amber-700 dark:text-amber-300 max-h-40 overflow-y-auto space-y-1">
                    @foreach ($importResult['errors'] as $err)
                    <p>{{ $err }}</p>
                    @endforeach
                </div>
                @endif
                @endif
            </div>
            <div class="modal-footer">
                @if ($importState === 'done')
                <button wire:click="$set('showImportModal', false)" class="btn-primary">Tutup</button>
                @else
                <button wire:click="$set('showImportModal', false)" class="btn-secondary">Batal</button>
                <button wire:click="doImport" wire:loading.attr="disabled" wire:target="doImport"
                        class="btn-primary" @disabled($importState !== 'preview')>
                    Impor Sekarang
                </button>
                @endif
            </div>
        </div>
    </div>
    @endif

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
                        <x-confirm-button
                            action="toggleAktif({{ $alat->id }})"
                            title="{{ $alat->is_active ? 'Nonaktifkan Peralatan?' : 'Aktifkan Peralatan?' }}"
                            text="{{ $alat->nama }}"
                            icon="{{ $alat->is_active ? 'warning' : 'question' }}"
                            confirm="{{ $alat->is_active ? 'Ya, Nonaktifkan' : 'Ya, Aktifkan' }}"
                            type="{{ $alat->is_active ? 'danger' : 'success' }}"
                            @class([
                                'inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium transition-colors',
                                'bg-emerald-100 text-emerald-700 hover:bg-emerald-200 dark:bg-emerald-900/40 dark:text-emerald-300' => $alat->is_active,
                                'bg-red-100 text-red-700 hover:bg-red-200 dark:bg-red-900/40 dark:text-red-300'                    => !$alat->is_active,
                            ])>
                            <span class="h-1.5 w-1.5 rounded-full {{ $alat->is_active ? 'bg-emerald-500' : 'bg-red-500' }}"></span>
                            {{ $alat->is_active ? 'Aktif' : 'Nonaktif' }}
                        </x-confirm-button>
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
