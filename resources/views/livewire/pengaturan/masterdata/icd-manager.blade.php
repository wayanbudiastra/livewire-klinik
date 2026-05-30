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
        <div class="card p-4">
            <p class="text-xs text-gray-400 uppercase tracking-wide">Bahasa Aktif</p>
            <span @class([
                'inline-flex items-center gap-1 mt-1 px-2.5 py-1 rounded-full text-xs font-semibold',
                'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300' => $this->stats['bahasa'] === 'id',
                'bg-purple-100 text-purple-700 dark:bg-purple-900/40 dark:text-purple-300' => $this->stats['bahasa'] === 'en',
            ])>
                {{ $this->stats['bahasa'] === 'id' ? '🇮🇩 Indonesia' : '🌐 International (EN)' }}
            </span>
        </div>
        <div class="card p-4 flex items-center gap-3">
            <div class="flex-1">
                <p class="text-xs text-gray-400 uppercase tracking-wide mb-1">Template CSV</p>
                <p class="text-xs text-gray-500 dark:text-gray-400">kode, nama, kategori</p>
            </div>
            <a href="{{ route('pengaturan.icd.template') }}"
               class="inline-flex items-center gap-1 px-3 py-2 text-sm font-medium rounded-lg border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700 flex-shrink-0">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
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
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
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
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l4-4m0 0l4 4m-4-4v12"/>
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
                        <th>Nama Diagnosis <span class="text-xs font-normal text-gray-400">({{ $this->stats['bahasa'] === 'id' ? 'Bahasa Indonesia' : 'English' }})</span></th>
                        <th>Kategori / Bab</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($this->icdList as $row)
                    <tr wire:key="icd-{{ $row->id }}">
                        <td class="font-mono text-sm font-semibold text-[#0a3d62] dark:text-blue-400">{{ $row->kode }}</td>
                        <td>
                            <p class="text-sm text-gray-800 dark:text-gray-200">{{ $row->nama }}</p>
                            @if($row->nama_en && $row->nama_id)
                            <p class="text-xs text-gray-400 mt-0.5 italic">
                                {{ $this->stats['bahasa'] === 'id' ? $row->nama_en : $row->nama_id }}
                            </p>
                            @endif
                        </td>
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

        {{-- ═══ SECTION 1: Import dari JSON bawaan ═══ --}}
        <div @class([
            'card border-2',
            'border-emerald-300 dark:border-emerald-700' => $this->jsonFileExists,
            'border-gray-200 dark:border-gray-700' => !$this->jsonFileExists,
        ])>
            <div class="card-header">
                <div>
                    <h3 class="text-sm font-semibold dark:text-white flex items-center gap-2">
                        <svg class="w-4 h-4 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        Import dari Data Bawaan (master_icd_x.json)
                    </h3>
                    <p class="text-xs text-gray-400 mt-0.5">
                        @if($this->jsonFileExists)
                            File ditemukan — 10.469 kode ICD-10 WHO versi Indonesia &amp; International
                        @else
                            File <code>master_icd_x.json</code> tidak ditemukan di root project
                        @endif
                    </p>
                </div>
                @if($this->jsonFileExists)
                <span class="badge badge-success">Tersedia</span>
                @else
                <span class="badge badge-gray">Tidak Ada</span>
                @endif
            </div>

            @if($this->jsonFileExists)
            <div class="card-body space-y-4">

                {{-- Pilih bahasa --}}
                <div class="form-group">
                    <label class="form-label">Pilih Bahasa yang Digunakan</label>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mt-1">

                        <label @class([
                            'flex items-start gap-3 p-3 rounded-xl border-2 cursor-pointer transition-colors',
                            'border-blue-500 bg-blue-50 dark:bg-blue-900/20' => $bahasaImport === 'id',
                            'border-gray-200 dark:border-gray-700 hover:border-gray-300' => $bahasaImport !== 'id',
                        ])>
                            <input type="radio" wire:model="bahasaImport" value="id" class="mt-0.5 text-blue-600" />
                            <div>
                                <p class="text-sm font-semibold text-gray-800 dark:text-gray-200">🇮🇩 Bahasa Indonesia</p>
                                <p class="text-xs text-gray-500 mt-0.5">
                                    Contoh: <em>"Demam tifoid"</em>
                                </p>
                            </div>
                        </label>

                        <label @class([
                            'flex items-start gap-3 p-3 rounded-xl border-2 cursor-pointer transition-colors',
                            'border-purple-500 bg-purple-50 dark:bg-purple-900/20' => $bahasaImport === 'en',
                            'border-gray-200 dark:border-gray-700 hover:border-gray-300' => $bahasaImport !== 'en',
                        ])>
                            <input type="radio" wire:model="bahasaImport" value="en" class="mt-0.5 text-purple-600" />
                            <div>
                                <p class="text-sm font-semibold text-gray-800 dark:text-gray-200">🌐 International (EN)</p>
                                <p class="text-xs text-gray-500 mt-0.5">
                                    Contoh: <em>"Typhoid fever"</em>
                                </p>
                            </div>
                        </label>

                        <label @class([
                            'flex items-start gap-3 p-3 rounded-xl border-2 cursor-pointer transition-colors',
                            'border-teal-500 bg-teal-50 dark:bg-teal-900/20' => $bahasaImport === 'both',
                            'border-gray-200 dark:border-gray-700 hover:border-gray-300' => $bahasaImport !== 'both',
                        ])>
                            <input type="radio" wire:model="bahasaImport" value="both" class="mt-0.5 text-teal-600" />
                            <div>
                                <p class="text-sm font-semibold text-gray-800 dark:text-gray-200">🔀 Simpan Keduanya</p>
                                <p class="text-xs text-gray-500 mt-0.5">
                                    Aktif: Indonesia — bisa ganti kapan saja tanpa re-import
                                </p>
                            </div>
                        </label>

                    </div>
                </div>

                @if(!empty($jsonResult))
                {{-- Hasil import JSON --}}
                <div class="rounded-xl border border-emerald-200 dark:border-emerald-800 bg-emerald-50 dark:bg-emerald-900/20 p-4 space-y-2">
                    <p class="text-sm font-semibold text-emerald-700 dark:text-emerald-300">✓ Import Berhasil</p>
                    <div class="grid grid-cols-3 gap-3 text-center text-sm">
                        <div>
                            <p class="text-xl font-bold text-emerald-600">{{ number_format($jsonResult['imported']) }}</p>
                            <p class="text-xs text-gray-500">Kode diimpor</p>
                        </div>
                        <div>
                            <p class="text-xl font-bold text-yellow-500">{{ $jsonResult['skipped'] }}</p>
                            <p class="text-xs text-gray-500">Dilewati</p>
                        </div>
                        <div>
                            <p class="text-sm font-semibold text-gray-600 dark:text-gray-300 mt-1">
                                {{ $jsonResult['bahasa'] === 'id' ? '🇮🇩 Indonesia' : '🌐 English' }}
                            </p>
                            <p class="text-xs text-gray-500">Bahasa aktif</p>
                        </div>
                    </div>
                </div>
                @endif

                @if($jsonError)
                <div class="alert alert-danger text-sm">{{ $jsonError }}</div>
                @endif

                <div class="flex items-center justify-between">
                    <p class="text-xs text-gray-400">
                        Data akan di-<em>upsert</em> (tambah/perbarui) — data lama tidak dihapus kecuali kodenya sama.
                    </p>
                    <button wire:click="importDariJson"
                        wire:loading.attr="disabled"
                        wire:target="importDariJson"
                        class="btn-primary px-6">
                        <span wire:loading.remove wire:target="importDariJson" class="flex items-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l4-4m0 0l4 4m-4-4v12"/>
                            </svg>
                            Mulai Import JSON
                        </span>
                        <span wire:loading wire:target="importDariJson" class="flex items-center gap-2">
                            <svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
                            </svg>
                            Mengimpor 10.469 kode...
                        </span>
                    </button>
                </div>
            </div>
            @else
            <div class="card-body">
                <p class="text-sm text-gray-500">
                    Letakkan file <code class="bg-gray-100 dark:bg-gray-700 px-1 rounded">master_icd_x.json</code>
                    di folder root project untuk menggunakan fitur ini.
                </p>
            </div>
            @endif
        </div>

        {{-- ═══ SECTION 2: Ganti Bahasa Tampilan ═══ --}}
        @if($this->stats['total'] > 0)
        <div class="card">
            <div class="card-header">
                <div>
                    <h3 class="text-sm font-semibold dark:text-white">Ganti Bahasa Tampilan</h3>
                    <p class="text-xs text-gray-400 mt-0.5">
                        Switch antara Indonesia ↔ International tanpa re-import
                        <span class="text-yellow-500">(hanya bekerja jika data sudah diimpor dengan mode "Simpan Keduanya")</span>
                    </p>
                </div>
                <span @class([
                    'badge',
                    'badge-info' => $this->stats['bahasa'] === 'id',
                    'badge-secondary' => $this->stats['bahasa'] === 'en',
                ])>
                    Aktif: {{ $this->stats['bahasa'] === 'id' ? 'Indonesia' : 'International' }}
                </span>
            </div>
            <div class="card-body flex items-center gap-3">
                <button wire:click="gantiBarasa('id')"
                    wire:loading.attr="disabled"
                    wire:target="gantiBarasa"
                    @class(['btn-primary', 'opacity-50 cursor-not-allowed' => $this->stats['bahasa'] === 'id'])
                    @if($this->stats['bahasa'] === 'id') disabled @endif>
                    <span wire:loading.remove wire:target="gantiBarasa">🇮🇩 Ganti ke Indonesia</span>
                    <span wire:loading wire:target="gantiBarasa" class="flex items-center gap-2">
                        <svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
                        </svg>
                        Mengubah...
                    </span>
                </button>
                <button wire:click="gantiBarasa('en')"
                    wire:loading.attr="disabled"
                    wire:target="gantiBarasa"
                    @class(['btn-secondary', 'opacity-50 cursor-not-allowed' => $this->stats['bahasa'] === 'en'])
                    @if($this->stats['bahasa'] === 'en') disabled @endif>
                    🌐 Ganti ke International (EN)
                </button>
                <p class="text-xs text-gray-400 ml-2">
                    Perubahan langsung berlaku di seluruh modul (pencarian diagnosa, SOAP Note, dsb.)
                </p>
            </div>
        </div>
        @endif

        {{-- ═══ SECTION 3: Divider ═══ --}}
        <div class="relative">
            <div class="absolute inset-0 flex items-center">
                <div class="w-full border-t border-gray-200 dark:border-gray-700"></div>
            </div>
            <div class="relative flex justify-center text-xs uppercase">
                <span class="bg-gray-50 dark:bg-gray-900 px-3 text-gray-400">atau import dari file sendiri</span>
            </div>
        </div>

        {{-- ═══ SECTION 4: Upload CSV/Excel ═══ --}}
        <div class="card">
            <div class="card-header">
                <div>
                    <h3 class="text-sm font-semibold dark:text-white">Import dari File CSV / Excel</h3>
                    <p class="text-xs text-gray-400 mt-0.5">Untuk data dari sumber lain (Kemenkes, BPJS, dll.)</p>
                </div>
            </div>
            <div class="card-body space-y-4">

                {{-- Petunjuk --}}
                <div class="rounded-xl border border-blue-200 dark:border-blue-800 bg-blue-50 dark:bg-blue-900/20 p-4 text-sm text-blue-800 dark:text-blue-200 space-y-1">
                    <p class="font-semibold">Format yang didukung: CSV (.csv) · Excel (.xlsx / .xls)</p>
                    <ul class="list-disc list-inside text-xs space-y-0.5 mt-1">
                        <li>Baris pertama adalah header kolom</li>
                        <li>Kolom wajib: <code class="bg-blue-100 dark:bg-blue-800 px-1 rounded">kode</code> dan <code class="bg-blue-100 dark:bg-blue-800 px-1 rounded">nama</code></li>
                        <li>Format kode: <strong>A00</strong>, <strong>A00.0</strong>, <strong>Z99.9</strong></li>
                    </ul>
                </div>

                @if($importState === 'idle')
                <div class="form-group">
                    <label class="form-label">Pilih File</label>
                    <input type="file" wire:model="uploadedFile" accept=".csv,.xlsx,.xls"
                        class="block w-full text-sm text-gray-500 dark:text-gray-400
                               file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0
                               file:text-sm file:font-medium file:bg-blue-50 file:text-blue-700
                               hover:file:bg-blue-100 dark:file:bg-blue-900/30 dark:file:text-blue-400" />
                    @error('uploadedFile') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                <div wire:loading wire:target="uploadedFile" class="flex items-center gap-2 text-sm text-blue-600 dark:text-blue-400">
                    <svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
                    </svg>
                    Membaca file...
                </div>
                @if($importError)
                <div class="alert alert-danger text-sm">{{ $importError }}</div>
                @endif
                @endif

                @if($importState === 'preview')
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
                            <span class="text-sm"><strong>Tambah/Perbarui</strong> — kode lama diperbarui</span>
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="radio" wire:model="importMode" value="replace" class="text-red-600" />
                            <span class="text-sm"><strong class="text-red-600">Ganti Semua</strong> — hapus lama, ganti baru</span>
                        </label>
                    </div>
                </div>

                {{-- Preview table --}}
                <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                    <p class="px-3 py-2 text-xs text-gray-400 border-b border-gray-200 dark:border-gray-700">Preview 10 baris pertama</p>
                    <table class="table text-xs">
                        <thead>
                            <tr>@foreach($detectedHeaders as $h)<th class="whitespace-nowrap">{{ $h }}</th>@endforeach</tr>
                        </thead>
                        <tbody>
                            @foreach($preview as $row)
                            <tr>@foreach($row as $cell)<td class="whitespace-nowrap max-w-xs truncate">{{ $cell }}</td>@endforeach</tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if($importError)
                <div class="alert alert-danger text-sm">{{ $importError }}</div>
                @endif

                <div class="flex items-center justify-between">
                    <button wire:click="resetImport" class="btn-secondary">← Ganti File</button>
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
                            Mengimpor...
                        </span>
                    </button>
                </div>
                @endif

                @if($importState === 'done')
                <div class="rounded-xl border border-emerald-200 dark:border-emerald-800 bg-emerald-50 dark:bg-emerald-900/20 p-4 space-y-3">
                    <p class="text-sm font-semibold text-emerald-700 dark:text-emerald-300">✓ Import Selesai</p>
                    <div class="grid grid-cols-3 gap-3 text-center">
                        <div>
                            <p class="text-xl font-bold text-emerald-600">{{ number_format($importResult['imported'] ?? 0) }}</p>
                            <p class="text-xs text-gray-500">Berhasil</p>
                        </div>
                        <div>
                            <p class="text-xl font-bold text-yellow-500">{{ $importResult['skipped'] ?? 0 }}</p>
                            <p class="text-xs text-gray-500">Dilewati</p>
                        </div>
                        <div>
                            <p class="text-xl font-bold text-red-500">{{ count($importResult['errors'] ?? []) }}</p>
                            <p class="text-xs text-gray-500">Error</p>
                        </div>
                    </div>
                    @if(!empty($importResult['errors']))
                    <div class="bg-red-50 dark:bg-red-900/20 rounded-lg p-3 space-y-1">
                        @foreach($importResult['errors'] as $err)
                        <p class="text-xs text-red-600 dark:text-red-400">• {{ $err }}</p>
                        @endforeach
                    </div>
                    @endif
                    <div class="flex gap-3">
                        <button wire:click="resetImport" class="btn-secondary">Import File Lain</button>
                        <button wire:click="setTab('browse')" class="btn-primary">Lihat Data</button>
                    </div>
                </div>
                @endif

            </div>
        </div>

    </div>
    @endif

</div>
