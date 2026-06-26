<div class="space-y-4">

    {{-- Filter --}}
    <div class="card">
        <div class="card-body">
            <div class="flex flex-wrap gap-3 items-end">
                <div class="form-group mb-0">
                    <label class="form-label">Tipe Transaksi</label>
                    <select wire:model.live="filterTipe" class="form-input dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200">
                        <option value="">Semua Tipe</option>
                        @foreach($this->tipeList as $tipe)
                        <option value="{{ $tipe }}">{{ $tipe }}</option>
                        @endforeach
                    </select>
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

    {{-- Action bar --}}
    @if(count($selected) > 0)
    <div class="rounded-xl bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 px-4 py-3 flex items-center justify-between">
        <p class="text-sm text-blue-800 dark:text-blue-200">
            <strong>{{ count($selected) }}</strong> baris terpilih — total Rp {{ number_format($this->totalNominalTerpilih, 0, ',', '.') }}
        </p>
        @can('akuntansi.jurnal.posting')
        <x-confirm-button action="postingTerpilih" title="Posting {{ count($selected) }} Baris Jurnal?"
            text="Tindakan ini tidak bisa diedit lagi setelah posting ke buku besar."
            icon="warning" type="danger" confirm="Ya, Posting"
            wire:loading.attr="disabled" wire:target="postingTerpilih" class="btn-primary">
            <span wire:loading.remove wire:target="postingTerpilih">Posting ke Jurnal Umum</span>
            <span wire:loading wire:target="postingTerpilih">Memposting...</span>
        </x-confirm-button>
        @endcan
    </div>
    @endif

    <div class="card">
        <div class="card-body p-0">
            <table class="table">
                <thead>
                    <tr>
                        <th class="w-8"><input type="checkbox" wire:model.live="selectAll" class="rounded" /></th>
                        <th>Tanggal</th>
                        <th>Tipe</th>
                        <th>Keterangan</th>
                        <th>Debit</th>
                        <th>Kredit</th>
                        <th class="text-right">Nominal</th>
                        @can('akuntansi.jurnal.posting')
                        <th class="text-center">Aksi</th>
                        @endcan
                    </tr>
                </thead>
                <tbody>
                    @forelse($this->rows as $row)
                    <tr wire:key="jp-{{ $row->id }}">
                        <td><input type="checkbox" wire:model.live="selected" value="{{ $row->id }}" class="rounded" /></td>
                        <td class="text-sm text-gray-500">{{ $row->tanggal_transaksi->format('d/m/Y') }}</td>
                        <td class="text-xs"><span class="badge badge-info">{{ $row->tipe_transaksi }}</span></td>
                        <td class="text-sm">{{ $row->keterangan }}</td>
                        <td class="font-mono text-xs">{{ $row->kode_akun_debit }}</td>
                        <td class="font-mono text-xs">{{ $row->kode_akun_kredit }}</td>
                        <td class="text-right text-sm font-medium">Rp {{ number_format($row->nominal, 0, ',', '.') }}</td>
                        @can('akuntansi.jurnal.posting')
                        <td class="text-center">
                            <button wire:click="konfirmasiAbaikan({{ $row->id }})" class="btn-xs btn-danger">Abaikan</button>
                        </td>
                        @endcan
                    </tr>
                    @empty
                    <tr><td colspan="8" class="empty-state py-10"><p class="empty-state-text">Tidak ada jurnal pending</p></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($this->rows->hasPages())
        <div class="card-body border-t border-gray-100 dark:border-gray-700">{{ $this->rows->links() }}</div>
        @endif
    </div>

    {{-- Modal Abaikan --}}
    @if($abaikanId)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" wire:click.self="$set('abaikanId', null)">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-xl w-full max-w-md p-6 space-y-4">
            <h3 class="text-base font-semibold dark:text-white">Abaikan Baris Jurnal</h3>
            <div class="form-group">
                <label class="form-label">Alasan (opsional)</label>
                <textarea wire:model="alasanAbaikan" rows="3" class="form-input dark:bg-gray-900 dark:border-gray-600 dark:text-gray-200"></textarea>
            </div>
            <div class="flex justify-end gap-2">
                <button wire:click="$set('abaikanId', null)" class="btn-secondary">Batal</button>
                <button wire:click="abaikan" class="btn-danger">Abaikan Jurnal Ini</button>
            </div>
        </div>
    </div>
    @endif

</div>
