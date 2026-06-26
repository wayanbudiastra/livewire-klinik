<div class="space-y-4">

    {{-- ═══ FORM TINDAKAN ═══ --}}
    <div class="card">
        <div class="card-header flex items-center gap-2">
            <svg class="w-4 h-4 text-[#0a3d62] dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/>
            </svg>
            <h3 class="text-sm font-semibold dark:text-white">Tambah Tindakan / Prosedur</h3>
            @if($this->kunjungan?->poli)
            <span class="badge badge-primary text-xs ml-auto">Poli: {{ $this->kunjungan->poli->nama }}</span>
            @endif
        </div>
        <div class="card-body space-y-3">

            {{-- Cari tindakan --}}
            <div x-data="{ open: false }" class="relative">
                <label class="form-label dark:text-gray-300">Nama Tindakan <span class="text-red-500">*</span></label>
                @if($tindakanId)
                <div class="flex items-center gap-2 p-2 bg-blue-50 dark:bg-blue-900/30 rounded-lg border border-blue-200 dark:border-blue-700">
                    <svg class="w-4 h-4 text-blue-600 dark:text-blue-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    <span class="text-sm font-medium text-blue-700 dark:text-blue-300 flex-1">{{ $tindakanNama }}</span>
                    <span class="text-xs text-blue-500 font-mono">Rp {{ number_format($tindakanTarif, 0, ',', '.') }}</span>
                    <button type="button" wire:click="$set('tindakanId', null)" class="text-blue-400 hover:text-blue-600 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
                @else
                <div class="relative">
                    <input wire:model.live.debounce.300ms="searchTindakan"
                           @focus="open = true" @click.away="open = false"
                           type="text"
                           placeholder="Ketik nama atau kode tindakan (min. 2 karakter)..."
                           class="form-input pr-10 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200"/>
                    <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none">
                        <svg wire:loading wire:target="searchTindakan" class="animate-spin h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                        </svg>
                    </div>
                </div>

                @if(strlen($searchTindakan) >= 2)
                <div x-show="open" x-transition
                     class="absolute z-50 w-full mt-1 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-600 rounded-xl shadow-lg max-h-60 overflow-y-auto">
                    @forelse($this->suggestionsTindakan as $t)
                    <button type="button"
                            wire:click="pilihTindakan({{ $t->id }}, @js($t->nama), '{{ $t->tarif }}')"
                            @click="open = false"
                            class="w-full flex items-center justify-between px-4 py-2.5 text-sm hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors text-left">
                        <div>
                            <span class="font-medium text-gray-900 dark:text-gray-100">{{ $t->nama }}</span>
                            <span class="ml-2 text-xs text-gray-400 font-mono">{{ $t->kode }}</span>
                            @if($t->kategori)
                            <span class="ml-2 badge badge-gray text-xs">{{ $t->kategori }}</span>
                            @endif
                        </div>
                        <span class="text-xs text-blue-600 dark:text-blue-400 font-semibold ml-4 flex-shrink-0">
                            Rp {{ number_format($t->tarif, 0, ',', '.') }}
                        </span>
                    </button>
                    @empty
                    <div class="px-4 py-3 text-sm text-gray-400 text-center">
                        Tidak ada tindakan ditemukan
                        @if($this->kunjungan?->poli)
                        <span class="block text-xs mt-1">Pastikan tindakan sudah di-mapping ke {{ $this->kunjungan->poli->nama }}</span>
                        @endif
                    </div>
                    @endforelse
                </div>
                @endif
                @endif
            </div>

            {{-- Pelaksana, Jumlah, Waktu --}}
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div class="form-group">
                    <label class="form-label dark:text-gray-300">Pelaksana</label>
                    <select wire:model="pelaksanaId"
                            class="form-select dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200">
                        <option value="">— Pilih Pelaksana —</option>
                        @foreach($this->pelaksanaOptions as $user)
                        <option value="{{ $user->id }}">{{ $user->nama }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label dark:text-gray-300">Jumlah</label>
                    <input wire:model="jumlahTindakan" type="number" min="1"
                           class="form-input dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200"/>
                </div>
                <div class="form-group">
                    <label class="form-label dark:text-gray-300">Tanggal & Jam</label>
                    <input wire:model="waktuTindakan" type="datetime-local"
                           class="form-input dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200"/>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label dark:text-gray-300">Catatan (opsional)</label>
                <input wire:model="catatanTindakan" type="text"
                       placeholder="Catatan tambahan..."
                       class="form-input dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200"/>
            </div>

            <div class="flex justify-end">
                <button type="button" wire:click="simpanTindakan"
                        class="btn-primary flex items-center gap-2"
                        wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="simpanTindakan">
                        <svg class="w-4 h-4 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                        </svg>
                        Tambah Tindakan
                    </span>
                    <span wire:loading wire:target="simpanTindakan" class="flex items-center gap-2">
                        <div class="spinner h-4 w-4 border-white border-t-transparent"></div>
                        Menyimpan...
                    </span>
                </button>
            </div>
        </div>
    </div>

    {{-- ═══ FORM ALKES / BMHP ═══ --}}
    <div class="card">
        <div class="card-header flex items-center gap-2">
            <svg class="w-4 h-4 text-purple-600 dark:text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"/>
            </svg>
            <h3 class="text-sm font-semibold dark:text-white">Tambah Pemakaian Alkes / BMHP</h3>
        </div>
        <div class="card-body space-y-3">

            {{-- Cari Alkes --}}
            <div x-data="{ open: false }" class="relative">
                <label class="form-label dark:text-gray-300">Nama Alat / BMHP <span class="text-red-500">*</span></label>
                @if($alkesId)
                <div class="flex items-center gap-2 p-2 bg-purple-50 dark:bg-purple-900/30 rounded-lg border border-purple-200 dark:border-purple-700">
                    <svg class="w-4 h-4 text-purple-600 dark:text-purple-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    <span class="text-sm font-medium text-purple-700 dark:text-purple-300 flex-1">{{ $alkesNama }}</span>
                    <span class="text-xs text-purple-500">{{ $alkesSatuan }}</span>
                    <button type="button" wire:click="$set('alkesId', null)" class="text-purple-400 hover:text-purple-600 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
                @else
                <div class="relative">
                    <input wire:model.live.debounce.300ms="searchAlkes"
                           @focus="open = true" @click.away="open = false"
                           type="text"
                           placeholder="Ketik nama atau kode alkes/BMHP (min. 2 karakter)..."
                           class="form-input pr-10 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200"/>
                    <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none">
                        <svg wire:loading wire:target="searchAlkes" class="animate-spin h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                        </svg>
                    </div>
                </div>

                @if(strlen($searchAlkes) >= 2)
                <div x-show="open" x-transition
                     class="absolute z-50 w-full mt-1 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-600 rounded-xl shadow-lg max-h-60 overflow-y-auto">
                    @forelse($this->suggestionsAlkes as $alkes)
                    <button type="button"
                            wire:click="pilihAlkes({{ $alkes->id }}, @js($alkes->nama), @js($alkes->satuan ?? ''))"
                            @click="open = false"
                            class="w-full flex items-center justify-between px-4 py-2.5 text-sm hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors text-left">
                        <div>
                            <span class="font-medium text-gray-900 dark:text-gray-100">{{ $alkes->nama }}</span>
                            <span class="ml-2 text-xs text-gray-400 font-mono">{{ $alkes->kode }}</span>
                        </div>
                        <div class="flex items-center gap-3 ml-4 flex-shrink-0">
                            <span class="text-xs text-gray-500">Stok: {{ $alkes->stok }}</span>
                            <span class="text-xs text-purple-600 dark:text-purple-400">{{ $alkes->satuan }}</span>
                        </div>
                    </button>
                    @empty
                    <div class="px-4 py-3 text-sm text-gray-400 text-center">Tidak ada alkes/BMHP ditemukan (aktif)</div>
                    @endforelse
                </div>
                @endif
                @endif
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div class="form-group">
                    <label class="form-label dark:text-gray-300">Jumlah (Qty)</label>
                    <div class="flex items-center gap-2">
                        <input wire:model="jumlahAlkes" type="number" min="1"
                               class="form-input dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200"/>
                        @if($alkesSatuan)
                        <span class="text-sm text-gray-500 dark:text-gray-400 flex-shrink-0">{{ $alkesSatuan }}</span>
                        @endif
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label dark:text-gray-300">Catatan (opsional)</label>
                    <input wire:model="catatanAlkes" type="text"
                           placeholder="Catatan penggunaan..."
                           class="form-input dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200"/>
                </div>
            </div>

            <div class="flex justify-end">
                <button type="button" wire:click="simpanAlkes"
                        class="btn-secondary flex items-center gap-2 !border-purple-400 !text-purple-700 hover:!bg-purple-50 dark:!text-purple-300 dark:hover:!bg-purple-900/30"
                        wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="simpanAlkes">
                        <svg class="w-4 h-4 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                        </svg>
                        Catat Pemakaian
                    </span>
                    <span wire:loading wire:target="simpanAlkes" class="flex items-center gap-2">
                        <div class="spinner h-4 w-4 border-current border-t-transparent"></div>
                        Menyimpan...
                    </span>
                </button>
            </div>
        </div>
    </div>

    {{-- ═══ MONITORING TABLE ═══ --}}
    <div class="card">
        <div class="card-header">
            <h3 class="text-sm font-semibold dark:text-white">Monitoring Prosedur & Pemakaian Alat</h3>
        </div>
        <div class="table-wrapper rounded-t-none">
            <table class="table">
                <thead>
                    <tr>
                        <th class="w-12">No</th>
                        <th class="w-24">Jam</th>
                        <th>Item (Tindakan / Alat)</th>
                        <th class="w-28">Kategori</th>
                        <th>Pelaksana</th>
                        <th class="w-16 text-center">Qty</th>
                        <th class="w-16">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @php
                        $tindakanRows = $this->riwayatTindakan->map(fn($t) => [
                            'type'       => 'tindakan',
                            'id'         => $t->id,
                            'waktu'      => $t->waktu_tindakan ?? $t->created_at,
                            'nama'       => $t->masterTindakan?->nama ?? '—',
                            'kode'       => $t->masterTindakan?->kode ?? '',
                            'kategori'   => $t->masterTindakan?->kategori ?? 'Tindakan',
                            'pelaksana'  => $t->pelaksana?->nama ?? '—',
                            'jumlah'     => $t->jumlah,
                            'tarif'      => $t->masterTindakan?->tarif ?? 0,
                            'catatan'    => $t->catatan,
                        ]);

                        $alkesRows = $this->riwayatAlkes->map(fn($a) => [
                            'type'      => 'alkes',
                            'id'        => $a->id,
                            'waktu'     => $a->created_at,
                            'nama'      => $a->barang?->nama ?? '—',
                            'kode'      => $a->barang?->kode ?? '',
                            'kategori'  => 'Alkes/BMHP',
                            'pelaksana' => '—',
                            'jumlah'    => $a->jumlah,
                            'tarif'     => $a->barang?->harga_jual ?? 0,
                            'catatan'   => $a->catatan,
                        ]);

                        $rows = $tindakanRows->concat($alkesRows)
                            ->sortByDesc('waktu')
                            ->values();
                    @endphp

                    @forelse($rows as $i => $row)
                    <tr wire:key="row-{{ $row['type'] }}-{{ $row['id'] }}">
                        <td class="text-xs text-gray-500 dark:text-gray-400 text-center">{{ $i + 1 }}</td>
                        <td class="text-xs font-mono text-gray-500 dark:text-gray-400">
                            {{ \Carbon\Carbon::parse($row['waktu'])->format('H:i') }}
                        </td>
                        <td>
                            <p class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $row['nama'] }}</p>
                            <p class="text-xs text-gray-400 font-mono">{{ $row['kode'] }}</p>
                            @if($row['catatan'])
                            <p class="text-xs text-gray-400 italic">{{ $row['catatan'] }}</p>
                            @endif
                        </td>
                        <td>
                            @if($row['type'] === 'tindakan')
                            <span class="badge badge-primary text-xs">{{ $row['kategori'] ?: 'Tindakan' }}</span>
                            @else
                            <span class="badge badge-purple text-xs">Alkes/BMHP</span>
                            @endif
                        </td>
                        <td class="text-sm text-gray-600 dark:text-gray-400">{{ $row['pelaksana'] }}</td>
                        <td class="text-center text-sm font-medium text-gray-700 dark:text-gray-300">
                            {{ $row['jumlah'] }}
                        </td>
                        <td>
                            @if($row['type'] === 'tindakan')
                            <x-confirm-button
                                :action="'hapusTindakan(' . $row['id'] . ')'"
                                title="Hapus Tindakan?"
                                :text="'Hapus ' . $row['nama'] . ' dari daftar tindakan?'"
                                confirm="Ya, Hapus"
                                type="danger"
                                class="btn-danger btn-xs">
                                Hapus
                            </x-confirm-button>
                            @else
                            <x-confirm-button
                                :action="'hapusAlkes(' . $row['id'] . ')'"
                                title="Hapus Pemakaian?"
                                :text="'Hapus pemakaian ' . $row['nama'] . '?'"
                                confirm="Ya, Hapus"
                                type="danger"
                                class="btn-danger btn-xs">
                                Hapus
                            </x-confirm-button>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7">
                            <div class="empty-state py-8">
                                <p class="empty-state-text text-sm">Belum ada tindakan atau pemakaian alat tercatat</p>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>

                @if($rows->count() > 0)
                <tfoot class="bg-gray-50 dark:bg-gray-700/50">
                    <tr>
                        <td colspan="5" class="text-right text-xs font-semibold text-gray-500 dark:text-gray-400 px-4 py-2">
                            Total Estimasi Biaya:
                        </td>
                        <td colspan="2" class="text-sm font-bold text-blue-700 dark:text-blue-400 px-4 py-2 font-mono">
                            Rp {{ number_format($rows->sum(fn($r) => ($r['tarif'] ?? 0) * $r['jumlah']), 0, ',', '.') }}
                        </td>
                    </tr>
                </tfoot>
                @endif
            </table>
        </div>
    </div>

</div>
