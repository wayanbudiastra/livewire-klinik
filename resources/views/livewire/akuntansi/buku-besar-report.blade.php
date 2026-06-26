<div class="space-y-4">

    <div class="card">
        <div class="card-body">
            <div class="flex flex-wrap gap-3 items-end">
                <div class="form-group mb-0 w-64">
                    <label class="form-label">Akun</label>
                    <select wire:model.live="kodeAkun" class="form-input dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200">
                        @foreach($this->akunOptions as $akun)
                        <option value="{{ $akun->kode }}">{{ $akun->kode }} — {{ $akun->nama }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group mb-0">
                    <label class="form-label">Dari</label>
                    <input type="date" wire:model.live="dari" class="form-input dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200" />
                </div>
                <div class="form-group mb-0">
                    <label class="form-label">Sampai</label>
                    <input type="date" wire:model.live="sampai" class="form-input dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200" />
                </div>
            </div>
        </div>
    </div>

    @if($this->hasil)
    <div class="card">
        <div class="card-header">
            <h3 class="text-sm font-semibold dark:text-white">
                {{ $this->hasil['akun']->kode }} — {{ $this->hasil['akun']->nama }}
            </h3>
            <span class="text-xs text-gray-400 capitalize">Tipe normal: {{ $this->hasil['akun']->tipe_normal }}</span>
        </div>
        <div class="card-body p-0">
            <table class="table">
                <thead>
                    <tr>
                        <th>Tanggal</th>
                        <th>Nomor Jurnal</th>
                        <th>Keterangan</th>
                        <th class="text-right">Debit</th>
                        <th class="text-right">Kredit</th>
                        <th class="text-right">Saldo</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="bg-gray-50 dark:bg-gray-800/50">
                        <td colspan="5" class="text-sm font-medium text-gray-600 dark:text-gray-300">Saldo Awal</td>
                        <td class="text-right text-sm font-semibold">Rp {{ number_format($this->hasil['saldo_awal'], 0, ',', '.') }}</td>
                    </tr>
                    @forelse($this->hasil['baris'] as $b)
                    <tr>
                        <td class="text-sm text-gray-500">{{ \Carbon\Carbon::parse($b['tanggal'])->format('d/m/Y') }}</td>
                        <td class="font-mono text-xs">{{ $b['nomor'] }}</td>
                        <td class="text-sm">{{ $b['keterangan'] }}</td>
                        <td class="text-right text-sm">{{ $b['debit'] > 0 ? 'Rp ' . number_format($b['debit'], 0, ',', '.') : '-' }}</td>
                        <td class="text-right text-sm">{{ $b['kredit'] > 0 ? 'Rp ' . number_format($b['kredit'], 0, ',', '.') : '-' }}</td>
                        <td class="text-right text-sm font-medium">Rp {{ number_format($b['saldo'], 0, ',', '.') }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="6" class="empty-state py-8"><p class="empty-state-text">Tidak ada transaksi pada periode ini</p></td></tr>
                    @endforelse
                    <tr class="bg-gray-50 dark:bg-gray-800/50">
                        <td colspan="5" class="text-sm font-semibold text-gray-700 dark:text-gray-200">Saldo Akhir</td>
                        <td class="text-right text-sm font-bold">Rp {{ number_format($this->hasil['saldo_akhir'], 0, ',', '.') }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
    @endif

</div>
