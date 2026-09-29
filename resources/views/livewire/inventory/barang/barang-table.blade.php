<div>
    <div class="mb-4 flex flex-col sm:flex-row gap-3 justify-between">
        <div class="flex flex-wrap gap-2">
            <div class="relative">
                <span class="absolute inset-y-0 left-3 flex items-center text-gray-400 pointer-events-none">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 11A6 6 0 1 1 5 11a6 6 0 0 1 12 0z"/></svg>
                </span>
                <input wire:model.live.debounce.400ms="search" type="text" placeholder="Nama / kode barang..."
                       class="form-input pl-9 w-64 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200"/>
            </div>
            <select wire:model.live="filterJenis" class="form-select w-44 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200">
                <option value="">Semua Jenis</option>
                <option value="obat">Obat</option>
                <option value="alkes">Alkes</option>
                <option value="bahan_habis_pakai">Bahan Habis Pakai</option>
                <option value="lainnya">Lainnya</option>
            </select>
            <select wire:model.live="filterStatus" class="form-select w-36 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200">
                <option value="aktif">Aktif</option>
                <option value="nonaktif">Non-Aktif</option>
                <option value="">Semua</option>
            </select>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('inventory.barang.template') }}" class="btn-secondary whitespace-nowrap">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                </svg>
                Download Template
            </a>
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
            <button wire:click="$dispatch('open-barang-create')" class="btn-primary whitespace-nowrap">+ Tambah Barang</button>
        </div>
    </div>

    {{-- Modal Upload/Impor Data --}}
    @if ($showImportModal)
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/50" wire:click="$set('showImportModal', false)"></div>
        <div class="relative z-10 w-full max-w-lg rounded-2xl bg-white shadow-2xl dark:bg-gray-800 dark:border dark:border-gray-700 animate-fade-in">
            <div class="modal-header">
                <h3 class="modal-title dark:text-white">
                    {{ $importMode === 'update' ? 'Update Data Barang' : 'Upload Data Baru Barang' }}
                </h3>
                <button wire:click="$set('showImportModal', false)" class="text-gray-400 hover:text-gray-600">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
            <div class="modal-body space-y-4">
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Isi file sesuai <a href="{{ route('inventory.barang.template') }}" class="text-primary-600 hover:underline">template XLS</a> ini.
                    @if ($importMode === 'update')
                        Baris dengan kode yang <strong>sudah terdaftar</strong> akan diupdate (stok TIDAK ikut berubah lewat impor). Kode yang belum ada akan dilewati.
                    @else
                        Baris dengan kode <strong>baru</strong> akan ditambahkan. Kode yang sudah terdaftar akan dilewati (tidak diubah).
                    @endif
                    Utk jenis Obat & Bahan Habis Pakai, Harga Jual dihitung otomatis dari HPR/Harga Modal (kolom Harga Jual di file diabaikan).
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
                    <p class="font-semibold">Dilewati:</p>
                    @foreach ($importResult['errors'] as $err)
                    <p>{{ $err }}</p>
                    @endforeach
                </div>
                @endif
                @if (!empty($importResult['warnings']))
                <div class="rounded-lg bg-orange-50 dark:bg-orange-900/30 px-4 py-3 text-xs text-orange-700 dark:text-orange-300 max-h-40 overflow-y-auto space-y-1">
                    <p class="font-semibold">Peringatan (tetap disimpan):</p>
                    @foreach ($importResult['warnings'] as $warn)
                    <p>{{ $warn }}</p>
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
            <thead><tr><th>Kode</th><th>Nama Barang</th><th>Jenis</th><th>Satuan</th><th>Stok</th><th>Stok Min</th><th>HPR (Rp)</th><th>Harga Jual (Rp)</th><th>Supplier Utama</th><th>Status</th><th>Aksi</th></tr></thead>
            <tbody>
                @forelse ($this->barang as $b)
                @php
                    $level = $b->level_stok;
                    $stokColor = match($level) { 'habis' => 'text-red-600 font-bold', 'kritis' => 'text-amber-600 font-bold', 'hampir_habis' => 'text-yellow-600', default => 'text-gray-800 dark:text-gray-200' };
                @endphp
                <tr wire:key="brg-{{ $b->id }}">
                    <td class="font-mono text-xs font-semibold text-gray-600 dark:text-gray-400">{{ $b->kode }}</td>
                    <td>
                        <p class="font-medium text-gray-900 dark:text-gray-100">{{ $b->nama }}</p>
                        @if($b->nama_generik)<p class="text-xs text-gray-400 italic">{{ $b->nama_generik }}</p>@endif
                    </td>
                    <td><span class="badge-gray">{{ ucfirst(str_replace('_',' ',$b->jenis)) }}</span></td>
                    <td class="text-sm text-gray-600 dark:text-gray-400">{{ $b->satuan }}</td>
                    <td class="text-sm {{ $stokColor }}">{{ number_format($b->stok) }}</td>
                    <td class="text-sm text-center text-gray-500">{{ $b->stok_minimum }}</td>
                    <td class="text-sm text-right text-gray-700 dark:text-gray-300">{{ number_format($b->harga_pokok, 0, ',', '.') }}</td>
                    <td class="text-sm text-right text-gray-700 dark:text-gray-300">{{ number_format($b->harga_jual, 0, ',', '.') }}</td>
                    <td class="text-xs text-gray-500">{{ $b->supplierUtama?->nama ?? '—' }}</td>
                    <td>
                        <x-confirm-button action="toggleAktif({{ $b->id }})"
                            title="{{ $b->is_active ? 'Nonaktifkan?' : 'Aktifkan?' }}" text="{{ $b->nama }}"
                            type="{{ $b->is_active ? 'danger' : 'success' }}"
                            confirm="{{ $b->is_active ? 'Ya, Nonaktifkan' : 'Ya, Aktifkan' }}"
                            @class(['inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium',
                                'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300' => $b->is_active,
                                'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300' => !$b->is_active])>
                            {{ $b->is_active ? 'Aktif' : 'Nonaktif' }}
                        </x-confirm-button>
                    </td>
                    <td><button wire:click="$dispatch('open-barang-edit', { id: {{ $b->id }} })" class="btn-warning btn-sm">Edit</button></td>
                </tr>
                @empty
                <tr><td colspan="11"><div class="empty-state"><p class="empty-state-text">Belum ada data barang</p></div></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4 flex items-center justify-between text-sm text-gray-500">
        @if($this->barang->total() > 0)
        <span>{{ $this->barang->total() }} item</span>
        {{ $this->barang->links() }}
        @endif
    </div>
</div>
