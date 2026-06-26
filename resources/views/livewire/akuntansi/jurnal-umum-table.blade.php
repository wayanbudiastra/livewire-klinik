<div class="space-y-4">

    <div class="card">
        <div class="card-body">
            <div class="flex flex-wrap gap-3 items-end">
                <div class="form-group mb-0">
                    <label class="form-label">Cari</label>
                    <input type="text" wire:model.live.debounce.400ms="search"
                        placeholder="Nomor jurnal / keterangan..."
                        class="form-input w-56 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200" />
                </div>
                <div class="form-group mb-0">
                    <label class="form-label">Dari</label>
                    <input type="date" wire:model.live="filterDari" class="form-input dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200" />
                </div>
                <div class="form-group mb-0">
                    <label class="form-label">Sampai</label>
                    <input type="date" wire:model.live="filterSampai" class="form-input dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200" />
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body p-0">
            <table class="table">
                <thead>
                    <tr>
                        <th>Nomor Jurnal</th>
                        <th>Tanggal</th>
                        <th>Keterangan</th>
                        <th>Debit</th>
                        <th>Kredit</th>
                        <th class="text-right">Nominal</th>
                        <th>Diposting Oleh</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($this->rows as $row)
                    <tr wire:key="ju-{{ $row->id }}">
                        <td class="font-mono text-sm font-medium text-[#0a3d62] dark:text-blue-400">{{ $row->nomor_jurnal }}</td>
                        <td class="text-sm text-gray-500">{{ $row->tanggal->format('d/m/Y') }}</td>
                        <td class="text-sm">{{ $row->keterangan }}</td>
                        <td class="text-xs">
                            <span class="font-mono">{{ $row->kode_akun_debit }}</span>
                            <span class="text-gray-400">{{ $row->akunDebit?->nama }}</span>
                        </td>
                        <td class="text-xs">
                            <span class="font-mono">{{ $row->kode_akun_kredit }}</span>
                            <span class="text-gray-400">{{ $row->akunKredit?->nama }}</span>
                        </td>
                        <td class="text-right text-sm font-medium">Rp {{ number_format($row->nominal, 0, ',', '.') }}</td>
                        <td class="text-xs text-gray-500">{{ $row->petugas?->nama ?? '-' }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="7" class="empty-state py-10"><p class="empty-state-text">Belum ada jurnal yang diposting</p></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($this->rows->hasPages())
        <div class="card-body border-t border-gray-100 dark:border-gray-700">{{ $this->rows->links() }}</div>
        @endif
    </div>

</div>
