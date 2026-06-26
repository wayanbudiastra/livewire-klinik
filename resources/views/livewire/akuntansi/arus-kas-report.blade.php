<div class="space-y-4">

    <div class="card">
        <div class="card-body flex flex-wrap items-end gap-4">
            <div class="form-group mb-0 w-44">
                <label class="form-label">Dari Tanggal</label>
                <input type="date" wire:model.live="dari" class="form-input dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200" />
            </div>
            <div class="form-group mb-0 w-44">
                <label class="form-label">Sampai Tanggal</label>
                <input type="date" wire:model.live="sampai" class="form-input dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200" />
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3 class="text-sm font-semibold dark:text-white">
                Laporan Arus Kas (Metode Langsung)
                — {{ \Carbon\Carbon::parse($dari)->format('d M Y') }} s/d {{ \Carbon\Carbon::parse($sampai)->format('d M Y') }}
            </h3>
            @if($this->hasil['cocok_neraca'])
            <span class="badge badge-success">Cocok dengan Neraca</span>
            @else
            <span class="badge badge-danger">Tidak Cocok!</span>
            @endif
        </div>
        <div class="card-body p-0">
            <table class="table">
                <tbody>
                    <tr class="bg-gray-50 dark:bg-gray-800/50"><td colspan="2" class="font-bold text-sm py-2 px-4">AKTIVITAS OPERASI</td></tr>
                    @forelse($this->hasil['operasi'] as $r)
                    <tr>
                        <td class="text-sm pl-8">{{ $r['akun']?->nama ?? '(tidak diketahui)' }}</td>
                        <td class="text-right text-sm @if($r['nominal'] < 0) text-red-600 @endif">
                            {{ $r['nominal'] < 0 ? '(' : '' }}Rp {{ number_format(abs($r['nominal']), 0, ',', '.') }}{{ $r['nominal'] < 0 ? ')' : '' }}
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="2" class="text-xs text-gray-400 pl-8 pb-1">- tidak ada aktivitas -</td></tr>
                    @endforelse
                    <tr class="border-t">
                        <td class="text-sm font-medium pl-4">Kas Bersih dari Aktivitas Operasi</td>
                        <td class="text-right text-sm font-medium">Rp {{ number_format($this->hasil['total_operasi'], 0, ',', '.') }}</td>
                    </tr>

                    <tr class="bg-gray-50 dark:bg-gray-800/50"><td colspan="2" class="font-bold text-sm py-2 px-4 pt-4">AKTIVITAS INVESTASI</td></tr>
                    @forelse($this->hasil['investasi'] as $r)
                    <tr><td class="text-sm pl-8">{{ $r['akun']?->nama ?? '(tidak diketahui)' }}</td><td class="text-right text-sm">Rp {{ number_format($r['nominal'], 0, ',', '.') }}</td></tr>
                    @empty
                    <tr><td colspan="2" class="text-xs text-gray-400 pl-8 pb-1">- belum ada transaksi aset tetap -</td></tr>
                    @endforelse
                    <tr class="border-t">
                        <td class="text-sm font-medium pl-4">Kas Bersih dari Aktivitas Investasi</td>
                        <td class="text-right text-sm font-medium">Rp {{ number_format($this->hasil['total_investasi'], 0, ',', '.') }}</td>
                    </tr>

                    <tr class="bg-gray-50 dark:bg-gray-800/50"><td colspan="2" class="font-bold text-sm py-2 px-4 pt-4">AKTIVITAS PENDANAAN</td></tr>
                    @forelse($this->hasil['pendanaan'] as $r)
                    <tr><td class="text-sm pl-8">{{ $r['akun']?->nama ?? '(tidak diketahui)' }}</td><td class="text-right text-sm">Rp {{ number_format($r['nominal'], 0, ',', '.') }}</td></tr>
                    @empty
                    <tr><td colspan="2" class="text-xs text-gray-400 pl-8 pb-1">- tidak ada aktivitas -</td></tr>
                    @endforelse
                    <tr class="border-t">
                        <td class="text-sm font-medium pl-4">Kas Bersih dari Aktivitas Pendanaan</td>
                        <td class="text-right text-sm font-medium">Rp {{ number_format($this->hasil['total_pendanaan'], 0, ',', '.') }}</td>
                    </tr>
                </tbody>
                <tfoot>
                    <tr class="bg-gray-100 dark:bg-gray-700/50 border-t-2">
                        <td class="text-sm font-bold py-2.5 px-4">KENAIKAN (PENURUNAN) KAS BERSIH</td>
                        <td class="text-right text-sm font-bold py-2.5 px-4">Rp {{ number_format($this->hasil['kenaikan_bersih'], 0, ',', '.') }}</td>
                    </tr>
                    <tr>
                        <td class="text-sm py-2 px-4">Saldo Kas & Bank Awal Periode</td>
                        <td class="text-right text-sm py-2 px-4">Rp {{ number_format($this->hasil['saldo_awal'], 0, ',', '.') }}</td>
                    </tr>
                    <tr class="border-t-2">
                        <td class="text-sm font-bold py-2.5 px-4">Saldo Kas & Bank Akhir Periode</td>
                        <td class="text-right text-sm font-bold py-2.5 px-4">Rp {{ number_format($this->hasil['saldo_akhir'], 0, ',', '.') }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

</div>
