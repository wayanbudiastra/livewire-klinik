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

        <div class="flex flex-wrap gap-2">
            @can('masterdata.view')
            <a href="{{ route('pengaturan.masterdata.tindakan.template') }}" class="btn-secondary whitespace-nowrap">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                </svg>
                Download Template
            </a>
            @endcan
            @can('masterdata.create')
            <button wire:click="openImportModal" class="btn-secondary whitespace-nowrap">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M9 19l3-3m0 0l3 3m-3-3v-9"/>
                </svg>
                Upload Data
            </button>
            <button wire:click="$dispatch('open-tindakan-create')" class="btn-primary whitespace-nowrap">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Tambah Tindakan
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
                <h3 class="modal-title dark:text-white">Upload Data Tindakan</h3>
                <button wire:click="$set('showImportModal', false)" class="text-gray-400 hover:text-gray-600">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
            <div class="modal-body space-y-4">
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Isi file sesuai <a href="{{ route('pengaturan.masterdata.tindakan.template') }}" class="text-primary-600 hover:underline">template XLS</a> ini.
                    Kode yang sudah ada akan diupdate, kode baru akan ditambahkan.
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
                    <th>Tarif WNA</th>
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
                    <td class="text-sm text-gray-500">
                        {{ $item->tarif_wna ? 'Rp '.number_format($item->tarif_wna, 0, ',', '.') : '-' }}
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
                        <x-confirm-button
                            action="toggleAktif({{ $item->id }})"
                            title="{{ $item->is_active ? 'Nonaktifkan Tindakan?' : 'Aktifkan Tindakan?' }}"
                            text="{{ $item->nama }}"
                            icon="{{ $item->is_active ? 'warning' : 'question' }}"
                            confirm="{{ $item->is_active ? 'Ya, Nonaktifkan' : 'Ya, Aktifkan' }}"
                            type="{{ $item->is_active ? 'danger' : 'success' }}"
                            @class([
                                'inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium transition-colors',
                                'bg-emerald-100 text-emerald-700 hover:bg-emerald-200 dark:bg-emerald-900/40 dark:text-emerald-300' => $item->is_active,
                                'bg-red-100 text-red-700 hover:bg-red-200 dark:bg-red-900/40 dark:text-red-300' => !$item->is_active,
                            ])>
                            <span class="h-1.5 w-1.5 rounded-full {{ $item->is_active ? 'bg-emerald-500' : 'bg-red-500' }}"></span>
                            {{ $item->is_active ? 'Aktif' : 'Nonaktif' }}
                        </x-confirm-button>
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
                    <td colspan="8">
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
