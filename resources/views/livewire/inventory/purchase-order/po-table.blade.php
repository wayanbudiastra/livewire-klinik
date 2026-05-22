<div>
    <div class="mb-4 flex flex-col sm:flex-row gap-3 justify-between">
        <div class="flex flex-wrap gap-2">
            <input wire:model.live.debounce.400ms="search" type="text" placeholder="Nomor PO / supplier..." class="form-input w-64 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200"/>
            <select wire:model.live="filterStatus" class="form-select w-44 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200">
                <option value="">Semua Status</option>
                @foreach (\App\Models\PurchaseOrder::getStatusLabels() as $v => $l)
                    <option value="{{ $v }}">{{ $l }}</option>
                @endforeach
            </select>
        </div>
        <a href="{{ route('inventory.po.create') }}" class="btn-primary whitespace-nowrap">+ Buat PO</a>
    </div>

    <div class="table-wrapper">
        <table class="table">
            <thead><tr><th>Nomor PO</th><th>Supplier</th><th>Tanggal</th><th>Est. Tiba</th><th>Total Nilai</th><th>Status</th><th>Dibuat Oleh</th><th>Aksi</th></tr></thead>
            <tbody>
                @forelse ($this->po as $po)
                @php
                $statusMap=['draft'=>'badge-gray','dikirim'=>'badge-primary','sebagian'=>'badge-warning','selesai'=>'badge-success','dibatalkan'=>'badge-danger'];
                $sc=$statusMap[$po->status]??'badge-gray';
                @endphp
                <tr wire:key="po-{{ $po->id }}">
                    <td class="font-mono text-sm font-semibold text-[#0a3d62] dark:text-blue-400">{{ $po->nomor_po }}</td>
                    <td>
                        <p class="font-medium text-gray-900 dark:text-gray-100">{{ $po->supplier?->nama }}</p>
                        <p class="text-xs font-mono text-gray-400">{{ $po->supplier?->kode }}</p>
                    </td>
                    <td class="text-sm">{{ $po->tanggal_po->format('d/m/Y') }}</td>
                    <td class="text-sm text-gray-500">{{ $po->tanggal_kirim_estimasi?->format('d/m/Y') ?? '-' }}</td>
                    <td class="text-sm font-medium text-right">Rp {{ number_format($po->total_nilai,0,',','.') }}</td>
                    <td><span class="{{ $sc }}">{{ \App\Models\PurchaseOrder::getStatusLabels()[$po->status] }}</span></td>
                    <td class="text-xs text-gray-500">{{ $po->dibuatOleh?->nama ?? '-' }}</td>
                    <td>
                        <div class="flex items-center gap-1">
                            @if($po->status === 'draft')
                            <x-confirm-button action="approve({{ $po->id }})" title="Approve PO?" text="PO {{ $po->nomor_po }} akan dikirim ke supplier." confirm="Ya, Approve" type="primary" class="btn-primary btn-sm">Approve</x-confirm-button>
                            <x-confirm-button action="batalkan({{ $po->id }})" title="Batalkan PO?" text="PO {{ $po->nomor_po }} akan dibatalkan." confirm="Ya, Batalkan" type="danger" class="btn-danger btn-sm">Batalkan</x-confirm-button>
                            @elseif($po->status === 'dikirim')
                            <a href="{{ route('inventory.gr.create', ['po_id' => $po->id]) }}" class="btn-success btn-sm">Terima Barang</a>
                            @endif
                        </div>
                    </td>
                </tr>
                @empty
                <tr><td colspan="8"><div class="empty-state"><p class="empty-state-text">Belum ada Purchase Order</p></div></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $this->po->links() }}</div>
</div>
