<div class="space-y-5">
    {{-- Summary Cards --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="card p-4 flex items-center gap-4 border-l-4 border-red-500">
            <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-red-50 dark:bg-red-900/30 flex-shrink-0">
                <svg class="w-6 h-6 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
            </div>
            <div>
                <p class="text-2xl font-black text-red-600">{{ $this->summary['total_habis'] }}</p>
                <p class="text-sm text-gray-500 dark:text-gray-400">🔴 Stok Habis</p>
            </div>
        </div>
        <div class="card p-4 flex items-center gap-4 border-l-4 border-orange-500">
            <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-orange-50 dark:bg-orange-900/30 flex-shrink-0">
                <svg class="w-6 h-6 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <div>
                <p class="text-2xl font-black text-orange-600">{{ $this->summary['total_kritis'] }}</p>
                <p class="text-sm text-gray-500 dark:text-gray-400">🟠 Stok Kritis (≤ Min)</p>
            </div>
        </div>
        <div class="card p-4 flex items-center gap-4 border-l-4 border-amber-400">
            <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-amber-50 dark:bg-amber-900/30 flex-shrink-0">
                <svg class="w-6 h-6 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <div>
                <p class="text-2xl font-black text-amber-600">{{ $this->summary['total_hampir_habis'] }}</p>
                <p class="text-sm text-gray-500 dark:text-gray-400">🟡 Hampir Habis (≤1.5× Min)</p>
            </div>
        </div>
    </div>

    {{-- Toolbar --}}
    <div class="card">
        <div class="card-body">
            <div class="flex flex-col md:flex-row gap-3 justify-between">
                <div class="flex flex-wrap gap-2">
                    <div class="relative">
                        <span class="absolute inset-y-0 left-3 flex items-center text-gray-400 pointer-events-none">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 11A6 6 0 1 1 5 11a6 6 0 0 1 12 0z"/></svg>
                        </span>
                        <input wire:model.live.debounce.300ms="search" type="text" placeholder="Cari nama / kode..." class="form-input pl-9 w-64 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200"/>
                    </div>
                    <select wire:model.live="filterJenis" class="form-select w-44 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200">
                        <option value="">Semua Jenis</option>
                        <option value="obat">Obat</option>
                        <option value="alkes">Alkes</option>
                        <option value="bahan_habis_pakai">Bahan Habis Pakai</option>
                    </select>
                    <select wire:model.live="filterLevel" class="form-select w-44 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200">
                        <option value="">Semua Level</option>
                        <option value="habis">🔴 Habis</option>
                        <option value="kritis">🟠 Kritis</option>
                        <option value="hampir_habis">🟡 Hampir Habis</option>
                    </select>
                </div>
                <a href="{{ route('inventory.po.create') }}" class="btn-primary whitespace-nowrap">+ Buat PO</a>
            </div>
        </div>
    </div>

    {{-- Tabel --}}
    <div class="table-wrapper">
        <table class="table">
            <thead><tr><th>Kode</th><th>Nama Barang</th><th>Jenis</th><th class="text-center">Stok</th><th class="text-center">Min</th><th class="text-center">Level</th><th>Supplier Utama</th><th class="text-right">HPR (Rp)</th><th>Aksi</th></tr></thead>
            <tbody>
                @forelse($this->barangKritis as $b)
                @php
                    $level=$b->level_stok;
                    $rc=['habis'=>'bg-red-50/40 dark:bg-red-900/10','kritis'=>'bg-orange-50/40 dark:bg-orange-900/10','hampir_habis'=>''][$level]??'';
                    $lc=['habis'=>'badge-danger','kritis'=>'badge-warning','hampir_habis'=>'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300'][$level]??'badge-gray';
                    $ll=['habis'=>'🔴 Habis','kritis'=>'🟠 Kritis','hampir_habis'=>'🟡 Hampir Habis'][$level]??'Aman';
                    $sc=$b->stok===0?'text-red-600 font-black':($b->stok<=$b->stok_minimum?'text-orange-600 font-bold':'text-amber-600 font-semibold');
                @endphp
                <tr class="{{ $rc }}">
                    <td class="font-mono text-xs text-gray-500">{{ $b->kode }}</td>
                    <td class="font-medium text-gray-900 dark:text-gray-100">{{ $b->nama }}</td>
                    <td><span class="badge-gray">{{ ucfirst($b->jenis) }}</span></td>
                    <td class="text-center {{ $sc }}">{{ $b->stok }}</td>
                    <td class="text-center text-gray-500">{{ $b->stok_minimum }}</td>
                    <td class="text-center"><span class="{{ $lc }} inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium">{{ $ll }}</span></td>
                    <td class="text-sm text-gray-600 dark:text-gray-400">{{ $b->supplierUtama?->nama ?? '—' }}</td>
                    <td class="text-right text-sm">{{ number_format($b->harga_pokok,0,',','.') }}</td>
                    <td>
                        <a href="{{ route('inventory.po.create', ['barang_id' => $b->id]) }}" class="btn-primary btn-sm">Beli</a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="9">
                        <div class="empty-state py-12">
                            <svg class="empty-state-icon text-emerald-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            <p class="empty-state-text text-emerald-600 font-semibold">Semua stok aman!</p>
                        </div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $this->barangKritis->links() }}</div>
</div>
