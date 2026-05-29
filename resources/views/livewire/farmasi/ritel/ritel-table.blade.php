<div class="space-y-4">

    {{-- Filter & Search --}}
    <div class="card">
        <div class="card-body">
            <div class="flex flex-wrap gap-3 items-end">
                <div class="form-group mb-0">
                    <label class="form-label">Cari</label>
                    <input type="text" wire:model.live.debounce.400ms="search"
                        placeholder="Nama pembeli / nomor ritel..."
                        class="form-input w-56 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200" />
                </div>
                <div class="form-group mb-0">
                    <label class="form-label">Status</label>
                    <select wire:model.live="filterStatus"
                        class="form-input dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200">
                        <option value="">Semua Status</option>
                        <option value="draft">Draft</option>
                        <option value="menunggu_kasir">Menunggu Kasir</option>
                        <option value="dibayar">Dibayar</option>
                        <option value="selesai">Selesai</option>
                        <option value="dibatalkan">Dibatalkan</option>
                    </select>
                </div>
                <div class="form-group mb-0">
                    <label class="form-label">Dari</label>
                    <input type="date" wire:model.live="filterDari"
                        class="form-input dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200" />
                </div>
                <div class="form-group mb-0">
                    <label class="form-label">Sampai</label>
                    <input type="date" wire:model.live="filterSampai"
                        class="form-input dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200" />
                </div>
            </div>
        </div>
    </div>

    {{-- Table --}}
    <div class="card">
        <div class="card-body p-0">
            <table class="table">
                <thead>
                    <tr>
                        <th>Nomor Ritel</th>
                        <th>Nama Pembeli</th>
                        <th>Tanggal</th>
                        <th class="text-center">Jumlah Item</th>
                        <th class="text-right">Total</th>
                        <th class="text-center">Status</th>
                        <th>Apoteker</th>
                        <th class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($this->transaksis as $tr)
                    <tr wire:key="tr-{{ $tr->id }}">
                        <td class="font-mono text-sm text-[#0a3d62] dark:text-blue-400 font-medium">
                            {{ $tr->nomor_ritel }}
                        </td>
                        <td>
                            <p class="font-medium text-gray-900 dark:text-gray-100 text-sm">{{ $tr->nama_pembeli }}</p>
                            @if($tr->nomor_hp)
                            <p class="text-xs text-gray-400">{{ $tr->nomor_hp }}</p>
                            @endif
                        </td>
                        <td class="text-sm text-gray-500 dark:text-gray-400">
                            {{ $tr->created_at->format('d/m/Y H:i') }}
                        </td>
                        <td class="text-center text-sm text-gray-600 dark:text-gray-400">
                            {{ $tr->items_count ?? $tr->items->count() }}
                        </td>
                        <td class="text-right font-medium text-sm text-gray-800 dark:text-gray-200">
                            Rp {{ number_format($tr->total_harga, 0, ',', '.') }}
                        </td>
                        <td class="text-center">
                            @php
                            $statusClass = match($tr->status) {
                                'draft'          => 'badge-warning',
                                'menunggu_kasir' => 'badge-info',
                                'dibayar'        => 'badge-primary',
                                'selesai'        => 'badge-success',
                                'dibatalkan'     => 'badge-gray',
                                default          => 'badge-gray',
                            };
                            @endphp
                            <span class="badge {{ $statusClass }}">{{ $tr->status_label }}</span>
                        </td>
                        <td class="text-sm text-gray-500 dark:text-gray-400">
                            {{ $tr->apoteker?->nama ?? '-' }}
                        </td>
                        <td>
                            <div class="flex items-center justify-center gap-1">
                                @if($tr->status === 'draft')
                                <a href="{{ route('farmasi.ritel.edit', $tr->id) }}"
                                   class="btn-xs btn-secondary">Edit</a>
                                <button wire:click="batalkan({{ $tr->id }})" wire:loading.attr="disabled"
                                    class="btn-xs btn-danger"
                                    onclick="return confirm('Batalkan transaksi ini?')">Batalkan</button>

                                @elseif($tr->status === 'menunggu_kasir')
                                <a href="{{ route('farmasi.ritel.show', $tr->id) }}" class="btn-xs btn-secondary">Lihat</a>
                                <button wire:click="batalkan({{ $tr->id }})" wire:loading.attr="disabled"
                                    class="btn-xs btn-danger"
                                    onclick="return confirm('Batalkan transaksi ini?')">Batalkan</button>

                                @elseif($tr->status === 'dibayar')
                                <a href="{{ route('farmasi.ritel.show', $tr->id) }}" class="btn-xs btn-secondary">Lihat</a>
                                <a href="{{ route('farmasi.ritel.print', $tr->id) }}" target="_blank" class="btn-xs btn-secondary" title="Cetak Struk">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                                    </svg>
                                </a>
                                <button wire:click="serahkanObat({{ $tr->id }})" wire:loading.attr="disabled"
                                    class="btn-xs btn-primary"
                                    onclick="return confirm('Serahkan obat dan potong stok? Tindakan ini tidak bisa dibatalkan.')">
                                    Serahkan Obat
                                </button>

                                @else
                                <a href="{{ route('farmasi.ritel.show', $tr->id) }}" class="btn-xs btn-secondary">Lihat</a>
                                <a href="{{ route('farmasi.ritel.print', $tr->id) }}" target="_blank" class="btn-xs btn-secondary" title="Cetak Struk">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                                    </svg>
                                </a>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="empty-state py-10">
                            <p class="empty-state-text">Belum ada transaksi ritel</p>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($this->transaksis->hasPages())
        <div class="card-body border-t border-gray-100 dark:border-gray-700">
            {{ $this->transaksis->links() }}
        </div>
        @endif
    </div>

</div>
