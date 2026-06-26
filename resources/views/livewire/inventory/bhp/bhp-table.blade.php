<div>
    @if(session('success'))
    <div class="alert alert-success mb-4">{{ session('success') }}</div>
    @endif
    @if(session('error'))
    <div class="alert alert-danger mb-4">{{ session('error') }}</div>
    @endif

    <div class="page-header">
        <div>
            <h1 class="page-title">Pemakaian Bahan Habis Pakai</h1>
            <p class="page-subtitle">Pengeluaran BHP dari gudang untuk operasional klinik</p>
        </div>
        @can('obat.edit')
        <a href="{{ route('inventory.bhp.create') }}" class="btn-primary">+ Buat Dokumen BHP</a>
        @endcan
    </div>

    <div class="card">
        <div class="card-header flex flex-wrap gap-3">
            <input type="text" wire:model.live.debounce.300ms="search"
                class="form-input w-48" placeholder="Cari no. BHP..." />
            <select wire:model.live="filterStatus" class="form-input w-40">
                <option value="">Semua Status</option>
                <option value="draft">Draft</option>
                <option value="selesai">Selesai</option>
                <option value="dibatalkan">Dibatalkan</option>
            </select>
            <input type="date" wire:model.live="filterDari"   class="form-input w-36" title="Dari tanggal" />
            <input type="date" wire:model.live="filterSampai" class="form-input w-36" title="Sampai tanggal" />
        </div>
        <div class="card-body p-0">
            <table class="table">
                <thead>
                    <tr>
                        <th>No. BHP</th>
                        <th>Tanggal</th>
                        <th>Dicatat Oleh</th>
                        <th class="text-center">Jml Item</th>
                        <th class="text-right">Total Nilai</th>
                        <th>Catatan</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($dokumenBhp as $bhp)
                    <tr>
                        <td class="font-mono text-xs">{{ $bhp->nomor_bhp }}</td>
                        <td class="text-sm">{{ $bhp->tanggal_pemakaian->format('d/m/Y') }}</td>
                        <td class="text-sm">{{ $bhp->pencatat->nama ?? '-' }}</td>
                        <td class="text-center text-sm">{{ $bhp->items->count() }}</td>
                        <td class="text-right text-sm font-medium">
                            Rp {{ number_format($bhp->total_nilai, 0, ',', '.') }}
                        </td>
                        <td class="text-sm text-gray-500 max-w-xs truncate" title="{{ $bhp->catatan }}">
                            {{ $bhp->catatan ?: '-' }}
                        </td>
                        <td>
                            @php
                                $sc = match($bhp->status) {
                                    'draft'      => 'badge-warning',
                                    'selesai'    => 'badge-success',
                                    'dibatalkan' => 'badge-gray',
                                    default      => 'badge-gray',
                                };
                            @endphp
                            <span class="badge {{ $sc }}">{{ $bhp->status_label }}</span>
                        </td>
                        <td>
                            <div class="flex gap-1">
                                @if($bhp->status === 'draft')
                                <a href="{{ route('inventory.bhp.edit', $bhp->id) }}" class="btn-secondary btn-sm">Edit / Verifikasi</a>
                                @else
                                <a href="{{ route('inventory.bhp.edit', $bhp->id) }}" class="btn-secondary btn-sm">Lihat</a>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="text-center text-gray-400 py-8">Belum ada dokumen BHP.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($dokumenBhp->hasPages())
        <div class="card-footer">{{ $dokumenBhp->links() }}</div>
        @endif
    </div>
</div>
