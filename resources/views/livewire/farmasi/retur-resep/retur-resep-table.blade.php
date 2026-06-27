<div class="space-y-4">

    <div class="flex flex-wrap items-end justify-between gap-3">
        <div class="flex flex-wrap gap-3">
            <div>
                <label class="block text-xs font-medium text-gray-600">Dari Tanggal</label>
                <input type="date" wire:model.live="filterDari" class="mt-1 form-input w-40 text-sm" />
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600">Sampai Tanggal</label>
                <input type="date" wire:model.live="filterSampai" class="mt-1 form-input w-40 text-sm" />
            </div>
        </div>
        @can('obat.edit')
        <a href="{{ route('farmasi.retur-resep.create') }}" class="btn-primary">+ Buat Retur Resep</a>
        @endcan
    </div>

    <div class="card">
        <div class="card-body p-0">
            <table class="table">
                <thead>
                    <tr>
                        <th>No. Retur</th>
                        <th>Pasien</th>
                        <th>Tanggal</th>
                        <th>Item Diretur</th>
                        <th class="text-right">Nilai</th>
                        <th>Metode</th>
                        <th>Diproses Oleh</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($this->rows as $r)
                    <tr wire:key="retur-resep-{{ $r->id }}">
                        <td class="font-mono text-xs">{{ $r->nomor_retur }}</td>
                        <td class="text-sm">
                            {{ $r->kunjungan->pasien->nama ?? '-' }}
                            <span class="text-gray-400 text-xs">({{ $r->kunjungan->pasien->nomor_rm ?? '-' }})</span>
                        </td>
                        <td class="text-sm">{{ $r->tanggal_retur->format('d/m/Y') }}</td>
                        <td class="text-xs text-gray-500">{{ $r->items->pluck('barang.nama')->implode(', ') }}</td>
                        <td class="text-right text-sm font-medium">Rp {{ number_format($r->total_nilai_retur, 0, ',', '.') }}</td>
                        <td>
                            <span class="badge badge-gray">{{ ucfirst($r->metode_pengembalian) }}</span>
                        </td>
                        <td class="text-sm text-gray-500">{{ $r->diprosesOleh->nama ?? '-' }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="7" class="empty-state py-10"><p class="empty-state-text">Belum ada retur resep pada rentang ini</p></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($this->rows->hasPages())
        <div class="card-footer">{{ $this->rows->links() }}</div>
        @endif
    </div>

</div>
