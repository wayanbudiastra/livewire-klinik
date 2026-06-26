<div class="space-y-4">

    <div class="card">
        <div class="card-body">
            <div class="form-group mb-0 w-56">
                <label class="form-label">Per Tanggal</label>
                <input type="date" wire:model.live="sampai" class="form-input dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200" />
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3 class="text-sm font-semibold dark:text-white">Neraca Saldo per {{ \Carbon\Carbon::parse($sampai)->format('d M Y') }}</h3>
            @if($this->hasil['seimbang'])
            <span class="badge badge-success">Seimbang</span>
            @else
            <span class="badge badge-danger">Tidak Seimbang!</span>
            @endif
        </div>
        <div class="card-body p-0">
            <table class="table">
                <thead>
                    <tr>
                        <th>Kode</th>
                        <th>Nama Akun</th>
                        <th>Golongan</th>
                        <th class="text-right">Debit</th>
                        <th class="text-right">Kredit</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($this->hasil['baris'] as $b)
                    <tr>
                        <td class="font-mono text-sm font-semibold text-[#0a3d62] dark:text-blue-400">{{ $b['akun']->kode }}</td>
                        <td class="text-sm">{{ $b['akun']->nama }}</td>
                        <td class="text-xs text-gray-500 capitalize">{{ $b['akun']->golongan }}</td>
                        <td class="text-right text-sm">{{ $b['debit'] > 0 ? 'Rp ' . number_format($b['debit'], 0, ',', '.') : '-' }}</td>
                        <td class="text-right text-sm">{{ $b['kredit'] > 0 ? 'Rp ' . number_format($b['kredit'], 0, ',', '.') : '-' }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="5" class="empty-state py-10"><p class="empty-state-text">Belum ada saldo akun</p></td></tr>
                    @endforelse
                </tbody>
                <tfoot>
                    <tr class="bg-gray-50 dark:bg-gray-800/50">
                        <td colspan="3" class="text-sm font-semibold text-gray-700 dark:text-gray-200 py-3 px-4">TOTAL</td>
                        <td class="text-right text-sm font-bold py-3 px-4">Rp {{ number_format($this->hasil['total_debit'], 0, ',', '.') }}</td>
                        <td class="text-right text-sm font-bold py-3 px-4">Rp {{ number_format($this->hasil['total_kredit'], 0, ',', '.') }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

</div>
