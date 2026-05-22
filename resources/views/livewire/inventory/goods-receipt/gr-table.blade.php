<div>
    <div class="mb-4 flex flex-col sm:flex-row gap-3 justify-between">
        <div class="flex flex-wrap gap-2">
            <input wire:model.live.debounce.400ms="search" type="text" placeholder="Nomor GR..." class="form-input w-52 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200"/>
            <select wire:model.live="filterStatus" class="form-select w-44 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200">
                <option value="">Semua Status</option>
                <option value="draft">Draft</option>
                <option value="diverifikasi">Diverifikasi</option>
                <option value="dibatalkan">Dibatalkan</option>
            </select>
        </div>
        <a href="{{ route('inventory.gr.create') }}" class="btn-primary whitespace-nowrap">+ Penerimaan Baru</a>
    </div>

    <div class="table-wrapper">
        <table class="table">
            <thead><tr><th>Nomor GR</th><th>Supplier</th><th>Tgl Terima</th><th>No. Faktur</th><th>Total Nilai</th><th>Status</th><th>Diterima Oleh</th><th>Aksi</th></tr></thead>
            <tbody>
                @forelse ($this->gr as $gr)
                @php $sc=['draft'=>'badge-gray','diverifikasi'=>'badge-success','dibatalkan'=>'badge-danger'][$gr->status]??'badge-gray'; @endphp
                <tr wire:key="gr-{{ $gr->id }}">
                    <td class="font-mono text-sm font-semibold text-emerald-700 dark:text-emerald-400">{{ $gr->nomor_gr }}</td>
                    <td class="font-medium text-gray-900 dark:text-gray-100">{{ $gr->supplier?->nama }}</td>
                    <td class="text-sm">{{ $gr->tanggal_terima->format('d/m/Y') }}</td>
                    <td class="text-sm text-gray-500 font-mono">{{ $gr->nomor_faktur_supplier ?? '-' }}</td>
                    <td class="text-sm font-medium text-right">Rp {{ number_format($gr->total_nilai,0,',','.') }}</td>
                    <td><span class="{{ $sc }}">{{ ucfirst($gr->status) }}</span></td>
                    <td class="text-xs text-gray-500">{{ $gr->diterimaOleh?->nama ?? '-' }}</td>
                    <td>
                        <div class="flex items-center gap-1">
                            @if($gr->status === 'draft')
                            <x-confirm-button action="verifikasi({{ $gr->id }})" title="Verifikasi GR?" text="Stok dan HPR semua item akan diperbarui." confirm="Ya, Verifikasi" type="primary" class="btn-primary btn-sm">Verifikasi</x-confirm-button>
                            <x-confirm-button action="batalkan({{ $gr->id }})" title="Batalkan GR?" text="GR {{ $gr->nomor_gr }} akan dibatalkan." confirm="Ya, Batalkan" type="danger" class="btn-danger btn-sm">Batalkan</x-confirm-button>
                            @endif
                        </div>
                    </td>
                </tr>
                @empty
                <tr><td colspan="8"><div class="empty-state"><p class="empty-state-text">Belum ada Goods Receipt</p></div></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $this->gr->links() }}</div>
</div>
