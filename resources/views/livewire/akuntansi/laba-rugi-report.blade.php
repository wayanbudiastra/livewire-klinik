<div class="space-y-4">

    <div class="card">
        <div class="card-body">
            <div class="flex flex-wrap gap-3 items-end">
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

    <div class="card">
        <div class="card-header">
            <h3 class="text-sm font-semibold dark:text-white">
                Laba Rugi — {{ \Carbon\Carbon::parse($dari)->format('d M Y') }} s/d {{ \Carbon\Carbon::parse($sampai)->format('d M Y') }}
            </h3>
        </div>
        <div class="card-body">
            <table class="table">
                <tbody>
                    <tr><td colspan="2" class="font-semibold text-sm text-gray-700 dark:text-gray-200 pt-0">PENDAPATAN</td></tr>
                    @forelse($this->hasil['pendapatan'] as $p)
                    <tr>
                        <td class="pl-6 text-sm text-gray-600 dark:text-gray-300">{{ $p['akun']->nama }}</td>
                        <td class="text-right text-sm">Rp {{ number_format($p['nominal'], 0, ',', '.') }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="2" class="pl-6 text-sm text-gray-400">Tidak ada pendapatan pada periode ini</td></tr>
                    @endforelse
                    <tr class="border-t border-gray-200 dark:border-gray-700">
                        <td class="text-sm font-semibold text-gray-700 dark:text-gray-200 py-2">Total Pendapatan</td>
                        <td class="text-right text-sm font-bold py-2">Rp {{ number_format($this->hasil['total_pendapatan'], 0, ',', '.') }}</td>
                    </tr>

                    <tr><td colspan="2" class="font-semibold text-sm text-gray-700 dark:text-gray-200 pt-6">BIAYA</td></tr>
                    @forelse($this->hasil['biaya'] as $b)
                    <tr>
                        <td class="pl-6 text-sm text-gray-600 dark:text-gray-300">{{ $b['akun']->nama }}</td>
                        <td class="text-right text-sm">Rp {{ number_format($b['nominal'], 0, ',', '.') }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="2" class="pl-6 text-sm text-gray-400">Tidak ada biaya pada periode ini</td></tr>
                    @endforelse
                    <tr class="border-t border-gray-200 dark:border-gray-700">
                        <td class="text-sm font-semibold text-gray-700 dark:text-gray-200 py-2">Total Biaya</td>
                        <td class="text-right text-sm font-bold py-2">Rp {{ number_format($this->hasil['total_biaya'], 0, ',', '.') }}</td>
                    </tr>

                    <tr class="border-t-2 border-gray-300 dark:border-gray-600">
                        <td class="text-base font-bold py-3 {{ $this->hasil['laba_rugi'] >= 0 ? 'text-emerald-600' : 'text-red-600' }}">
                            {{ $this->hasil['laba_rugi'] >= 0 ? 'LABA BERSIH' : 'RUGI BERSIH' }}
                        </td>
                        <td class="text-right text-base font-bold py-3 {{ $this->hasil['laba_rugi'] >= 0 ? 'text-emerald-600' : 'text-red-600' }}">
                            Rp {{ number_format(abs($this->hasil['laba_rugi']), 0, ',', '.') }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

</div>
