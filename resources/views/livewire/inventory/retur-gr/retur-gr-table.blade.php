<div class="space-y-4">

    <div class="flex flex-wrap items-end justify-between gap-3">
        <div class="flex flex-wrap gap-3">
            <input type="text" wire:model.live.debounce.300ms="search"
                class="form-input w-56" placeholder="Cari no. retur..." />
            <select wire:model.live="filterStatus" class="form-input w-40">
                <option value="">Semua Status</option>
                <option value="draft">Draft</option>
                <option value="diverifikasi">Diverifikasi</option>
                <option value="dibatalkan">Dibatalkan</option>
            </select>
        </div>
        @can('obat.edit')
        <a href="{{ route('inventory.retur-gr.create') }}" class="btn-primary">+ Buat Retur</a>
        @endcan
    </div>

    <div class="card">
        <div class="card-body p-0">
            <table class="table">
                <thead>
                    <tr>
                        <th>No. Retur</th>
                        <th>GR Asal</th>
                        <th>Supplier</th>
                        <th>Tanggal</th>
                        <th class="text-right">Total Nilai</th>
                        <th>Status</th>
                        <th>Dibuat Oleh</th>
                        <th class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($this->rows as $r)
                    <tr wire:key="retur-gr-{{ $r->id }}">
                        <td class="font-mono text-xs">{{ $r->nomor_retur }}</td>
                        <td class="text-sm">{{ $r->goodsReceipt->nomor_gr }}</td>
                        <td class="text-sm">{{ $r->supplier->nama }}</td>
                        <td class="text-sm">{{ $r->tanggal_retur->format('d/m/Y') }}</td>
                        <td class="text-right text-sm font-medium">Rp {{ number_format($r->total_nilai, 0, ',', '.') }}</td>
                        <td>
                            @php
                                $sc = match($r->status) {
                                    'draft'        => 'badge-warning',
                                    'diverifikasi' => 'badge-success',
                                    'dibatalkan'   => 'badge-gray',
                                    default        => 'badge-gray',
                                };
                            @endphp
                            <span class="badge {{ $sc }}">{{ ucfirst($r->status) }}</span>
                        </td>
                        <td class="text-sm text-gray-500">{{ $r->dibuatOleh->nama ?? '-' }}</td>
                        <td class="text-center">
                            @can('obat.edit')
                            @if($r->status === 'draft')
                            <div class="flex items-center justify-center gap-1">
                                <x-confirm-button action="verifikasi({{ $r->id }})" title="Verifikasi Retur Ini?"
                                    text="Stok akan berkurang dan hutang dagang dikoreksi."
                                    icon="warning" type="danger" confirm="Ya, Verifikasi"
                                    class="btn-xs btn-primary">
                                    Verifikasi
                                </x-confirm-button>
                                <x-confirm-button action="batalkan({{ $r->id }})" title="Batalkan Draft Ini?"
                                    icon="warning" type="danger" confirm="Ya, Batalkan"
                                    class="btn-xs btn-secondary">
                                    Batalkan
                                </x-confirm-button>
                            </div>
                            @endif
                            @endcan
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="8" class="empty-state py-10"><p class="empty-state-text">Belum ada retur ke supplier</p></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($this->rows->hasPages())
        <div class="card-footer">{{ $this->rows->links() }}</div>
        @endif
    </div>

</div>
