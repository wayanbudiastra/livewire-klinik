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
        <button wire:click="$dispatch('open-barang-create')" class="btn-primary whitespace-nowrap">+ Tambah Barang</button>
    </div>

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
