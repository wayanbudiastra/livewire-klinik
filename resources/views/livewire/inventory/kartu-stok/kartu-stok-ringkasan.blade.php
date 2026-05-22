<div class="space-y-5">

    {{-- Filter Periode --}}
    <div class="card">
        <div class="card-body">
            <div class="flex flex-wrap gap-3 items-end">
                <div class="form-group">
                    <label class="form-label dark:text-gray-300">Dari</label>
                    <input wire:model.live="tanggalMulai" type="date"
                           class="form-input dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200"/>
                </div>
                <div class="form-group">
                    <label class="form-label dark:text-gray-300">Sampai</label>
                    <input wire:model.live="tanggalAkhir" type="date"
                           class="form-input dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200"/>
                </div>
                <div class="form-group flex-1" style="min-width: 200px;">
                    <label class="form-label dark:text-gray-300">Cari Barang</label>
                    <input wire:model.live.debounce.400ms="search" type="text"
                           placeholder="Nama / kode..."
                           class="form-input dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200"/>
                </div>
            </div>
        </div>
    </div>

    {{-- Tabel Ringkasan --}}
    <div class="table-wrapper">
        <table class="table">
            <thead>
                <tr>
                    <th>Kode</th>
                    <th>Nama Barang</th>
                    <th>Satuan</th>
                    <th class="text-center text-emerald-600">Total Masuk</th>
                    <th class="text-center text-red-600">Total Keluar</th>
                    <th class="text-center">Transaksi</th>
                    <th class="text-center">Stok Saat Ini</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($this->ringkasan as $r)
                <tr wire:key="ring-{{ $r->barang_id }}">
                    <td class="font-mono text-xs text-gray-500">{{ $r->barang?->kode }}</td>
                    <td class="font-medium text-gray-900 dark:text-gray-100">{{ $r->barang?->nama }}</td>
                    <td class="text-sm text-gray-500">{{ $r->barang?->satuan }}</td>
                    <td class="text-center font-semibold text-emerald-600">+{{ number_format($r->total_masuk) }}</td>
                    <td class="text-center font-semibold text-red-600">-{{ number_format($r->total_keluar) }}</td>
                    <td class="text-center">
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300">
                            {{ $r->total_transaksi }}
                        </span>
                    </td>
                    <td class="text-center">
                        <span @class(['font-bold text-sm',
                            'text-red-600'   => $r->barang?->stok === 0,
                            'text-amber-600' => $r->barang && $r->barang->stok <= $r->barang->stok_minimum && $r->barang->stok > 0,
                            'text-gray-800 dark:text-gray-200' => $r->barang && $r->barang->stok > $r->barang->stok_minimum,
                        ])>{{ $r->barang?->stok }}</span>
                    </td>
                    <td>
                        <a href="{{ route('inventory.kartu-stok.index', ['q' => $r->barang?->kode, 'tab' => 'kartu']) }}"
                           class="btn-info btn-sm">Lihat Kartu</a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="8">
                        <div class="empty-state py-8">
                            <p class="empty-state-text">Tidak ada mutasi dalam periode ini</p>
                        </div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

</div>
