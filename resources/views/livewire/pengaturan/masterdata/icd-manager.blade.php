<div class="space-y-5">

    {{-- Stats Bar --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="card p-4">
            <p class="text-xs text-gray-400 uppercase tracking-wide">Total Kode ICD-10</p>
            <p class="text-2xl font-bold text-[#0a3d62] dark:text-blue-400 mt-1">
                {{ number_format($this->stats['total']) }}
            </p>
        </div>
        <div class="card p-4">
            <p class="text-xs text-gray-400 uppercase tracking-wide">Terakhir Diperbarui</p>
            <p class="text-sm font-semibold text-gray-700 dark:text-gray-300 mt-1">
                {{ $this->stats['updated_at'] }}
            </p>
        </div>
        <div class="card p-4 md:col-span-2 flex items-center gap-3">
            <div class="flex-1">
                <p class="text-xs text-gray-400 uppercase tracking-wide mb-1">Unduh Template CSV</p>
                <p class="text-xs text-gray-500 dark:text-gray-400">Format: kode, nama, kategori</p>
            </div>
            <a href="{{ route('pengaturan.icd.template') }}"
               class="inline-flex items-center gap-1.5 px-3 py-2 text-sm font-medium rounded-lg border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700 flex-shrink-0">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                </svg>
                Template
            </a>
        </div>
    </div>

    {{-- Tab Navigation --}}
    <div class="flex border-b border-gray-200 dark:border-gray-700">
        <button wire:click="setTab('browse')"
            @class([
                'flex items-center gap-2 px-5 py-2.5 text-sm font-medium border-b-2 -mb-px transition-colors',
                'border-[#0a3d62] text-[#0a3d62] dark:text-blue-400 dark:border-blue-400' => $tab === 'browse',
                'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200' => $tab !== 'browse',
            ])>
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
            </svg>
            Cari &amp; Lihat Data
        </button>
        <button wire:click="setTab('import')"
            @class([
                'flex items-center gap-2 px-5 py-2.5 text-sm font-medium border-b-2 -mb-px transition-colors',
                'border-[#0a3d62] text-[#0a3d62] dark:text-blue-400 dark:border-blue-400' => $tab === 'import',
                'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200' => $tab !== 'import',
            ])>
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l4-4m0 0l4 4m-4-4v12"/>
            </svg>
            Import Data
        </button>
    </div>

    {{-- ── TAB: BROWSE ── --}}
    @if($tab === 'browse')
    <div class="card">
        <div class="card-header">
            <h3 class="text-sm font-semibold dark:text-white">Data ICD-10</h3>
            <div class="w-64">
                <input type="text" wire:model.live.debounce.300ms="search"
                    placeholder="Cari kode / nama / kategori..."
                    class="form-input text-sm dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200" />
            </div>
        </div>
        <div class="card-body p-0">
            <table class="table">
                <thead>
                    <tr>
                        <th style="width:100px">Kode</th>
                        <th>Nama Diagnosis</th>
                        <th>Kategori / Bab</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($this->icdList as $row)
                    <tr wire:key="icd-{{ $row->id }}">
                        <td class="font-mono text-sm font-semibold text-[#0a3d62] dark:text-blue-400">
                            {{ $row->kode }}
                        </td>
                        <td class="text-sm text-gray-800 dark:text-gray-200">{{ $row->nama }}</td>
                        <td class="text-xs text-gray-500 dark:text-gray-400">{{ $row->kategori ?? '—' }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="3" class="empty-state py-10">
                            <p class="empty-state-text">{{ $search ? 'Kode / diagnosis tidak ditemukan' : 'Belum ada data ICD-10' }}</p>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($this->icdList->hasPages())
        <div class="card-body border-t border-gray-100 dark:border-gray-700">
            {{ $this->icdList->links() }}
        </div>
        @endif
    </div>
    @endif

    {{-- ── TAB: IMPORT ── --}}
    @if($tab === 'import')
    <div class="space-y-5">

        {{-- Petunjuk Format --}}
        <div class="rounded-xl border border-blue-200 dark:border-blue-800 bg-blue-50 dark:bg-blue-900/20 p-4 text-sm text-blue-800 dark:text-blue-200 space-y-2">
            <p class="font-semibold flex items-center gap-1.5">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                Format File yang Didukung
            </p>
            <ul class="list-disc list-inside space-y-1 text-xs">
                <li><strong>CSV</strong> (.csv) — kolom dipisah koma (,) atau titik koma (;), baris pertama adalah header</li>
                <li><strong>Excel</strong> (.xlsx / .xls) — sheet pertama digunakan, baris pertama adalah header</li>
                <li>Kolom wajib: <code class="bg-blue-100 dark:bg-blue-800 px-1 rounded">kode</code> dan <code class="bg-blue-100 dark:bg-blue-800 px-1 rounded">nama</code></li>
                <li>Kolom opsional: <code class="bg-blue-100 dark:bg-blue-800 px-1 rounded">kategori</code> (Bab / Chapter ICD-10)</li>
                <li>Format kode: huruf kapital + 2 digit angka, opsional titik + sub-kode. Contoh: <strong>A00</strong>, <strong>A00.0</strong>, <strong>Z99.9</strong></li>
            </ul>
        </div>

        @if($importState === 'idle')
        {{-- Upload Form --}}
        <div class="card">
            <div class="card-header">
                <h3 class="text-sm font-semibold dark:text-white">Unggah File ICD-10</h3>
            </div>
            <div class="card-body space-y-4">
                <div class="form-group">
                    <label class="form-label">File CSV / Excel <span class="text-red-500">*</span></label>
                    <input type="file" wire:model="uploadedFile"
                        accept=".csv,.xlsx,.xls"
                        class="block w-full text-sm text-gray-500 dark:text-gray-400
                               file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0
                               file:text-sm file:font-medium file:bg-blue-50 file:text-blue-700
                               hover:file:bg-blue-100 dark:file:bg-blue-900/30 dark:file:text-blue-400" />
                    @error('uploadedFile') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                <div wire:loading wire:target="uploadedFile"
                    class="flex items-center gap-2 text-sm text-blue-600 dark:text-blue-400">
                    <svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
                    </svg>
                    Membaca file...
                </div>
                @if($importError)
                <div class="alert alert-danger text-sm">{{ $importError }}</div>
                @endif
            </div>
        </div>
        @endif

        @if($importState === 'preview')
        {{-- Column Mapping & Mode --}}
        <div class="card">
            <div class="card-header">
                <h3 class="text-sm font-semibold dark:text-white">Pemetaan Kolom</h3>
                <span class="badge badge-success">File terbaca</span>
            </div>
            <div class="card-body space-y-5">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="form-group">
                        <label class="form-label">Kolom <strong>Kode ICD</strong> <span class="text-red-500">*</span></label>
                        <select wire:model="kodeCol" class="form-input dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200">
                            <option value="">-- pilih kolom --</option>
                            @foreach($detectedHeaders as $i => $h)
                            <option value="{{ $i }}">{{ $h }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Kolom <strong>Nama Diagnosis</strong> <span class="text-red-500">*</span></label>
                        <select wire:model="namaCol" class="form-input dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200">
                            <option value="">-- pilih kolom --</option>
                            @foreach($detectedHeaders as $i => $h)
                            <option value="{{ $i }}">{{ $h }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Kolom <strong>Kategori</strong> <span class="text-gray-400">(opsional)</span></label>
                        <select wire:model="kategoriCol" class="form-input dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200">
                            <option value="">-- kosongkan jika tidak ada --</option>
                            @foreach($detectedHeaders as $i => $h)
                            <option value="{{ $i }}">{{ $h }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Mode Import</label>
                    <div class="flex gap-4">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="radio" wire:model="importMode" value="upsert" class="text-blue-600" />
                            <span class="text-sm">
                                <strong>Tambah / Perbarui</strong>
                                <span class="text-gray-400 dark:text-gray-500"> — kode baru ditambahkan, kode lama diperbarui</span>
                            </span>
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="radio" wire:model="importMode" value="replace" class="text-red-600" />
                            <span class="text-sm">
                                <strong class="text-red-600">Ganti Semua</strong>
                                <span class="text-gray-400 dark:text-gray-500"> — hapus seluruh data lama, ganti dengan file baru</span>
                            </span>
                        </label>
                    </div>
                </div>

                @if($importError)
                <div class="alert alert-danger text-sm">{{ $importError }}</div>
                @endif
            </div>
        </div>

        {{-- Preview Table --}}
        <div class="card">
            <div class="card-header">
                <h3 class="text-sm font-semibold dark:text-white">Preview (10 baris pertama)</h3>
            </div>
            <div class="card-body p-0 overflow-x-auto">
                <table class="table text-xs">
                    <thead>
                        <tr>
                            @foreach($detectedHeaders as $h)
                            <th class="whitespace-nowrap">{{ $h }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($preview as $row)
                        <tr>
                            @foreach($row as $cell)
                            <td class="whitespace-nowrap max-w-xs truncate">{{ $cell }}</td>
                            @endforeach
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Action Buttons --}}
        <div class="flex items-center justify-between">
            <button wire:click="resetImport" class="btn-secondary">
                ← Ganti File
            </button>
            <button wire:click="doImport" wire:loading.attr="disabled" wire:target="doImport"
                @class(['btn-primary px-8', 'btn-danger' => $importMode === 'replace'])>
                <span wire:loading.remove wire:target="doImport">
                    @if($importMode === 'replace') Ganti Semua & Import @else Mulai Import @endif
                </span>
                <span wire:loading wire:target="doImport" class="flex items-center gap-2">
                    <svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
                    </svg>
                    Mengimpor data...
                </span>
            </button>
        </div>
        @endif

        @if($importState === 'done')
        {{-- Hasil Import --}}
        <div class="card">
            <div class="card-header">
                <h3 class="text-sm font-semibold dark:text-white">Hasil Import</h3>
                <span class="badge badge-success">Selesai</span>
            </div>
            <div class="card-body space-y-4">
                <div class="grid grid-cols-3 gap-4 text-center">
                    <div class="rounded-xl bg-emerald-50 dark:bg-emerald-900/20 p-4">
                        <p class="text-2xl font-bold text-emerald-600 dark:text-emerald-400">
                            {{ number_format($importResult['imported'] ?? 0) }}
                        </p>
                        <p class="text-xs text-gray-500 mt-1">Berhasil diimpor</p>
                    </div>
                    <div class="rounded-xl bg-yellow-50 dark:bg-yellow-900/20 p-4">
                        <p class="text-2xl font-bold text-yellow-600 dark:text-yellow-400">
                            {{ number_format($importResult['skipped'] ?? 0) }}
                        </p>
                        <p class="text-xs text-gray-500 mt-1">Dilewati (kosong / format salah)</p>
                    </div>
                    <div class="rounded-xl bg-red-50 dark:bg-red-900/20 p-4">
                        <p class="text-2xl font-bold text-red-600 dark:text-red-400">
                            {{ count($importResult['errors'] ?? []) }}
                        </p>
                        <p class="text-xs text-gray-500 mt-1">Error validasi</p>
                    </div>
                </div>

                @if(!empty($importResult['errors']))
                <div class="rounded-lg border border-red-200 dark:border-red-800 bg-red-50 dark:bg-red-900/20 p-3">
                    <p class="text-xs font-semibold text-red-700 dark:text-red-300 mb-2">Detail Error:</p>
                    @foreach($importResult['errors'] as $err)
                    <p class="text-xs text-red-600 dark:text-red-400">• {{ $err }}</p>
                    @endforeach
                </div>
                @endif

                <div class="flex gap-3">
                    <button wire:click="resetImport" class="btn-secondary">
                        Import File Lain
                    </button>
                    <button wire:click="setTab('browse')" class="btn-primary">
                        Lihat Data ICD-10
                    </button>
                </div>
            </div>
        </div>
        @endif
    </div>
    @endif

</div>
