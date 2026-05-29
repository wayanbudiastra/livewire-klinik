<div class="space-y-5">
    <div class="page-header">
        <div>
            <h1 class="page-title">Laporan Transaksi Kasir</h1>
            <p class="page-subtitle">Rekap transaksi pembayaran per periode</p>
        </div>
    </div>

    @include('components.laporan.filter-periode')

    @if($hasil)
    <div wire:loading.remove wire:target="generate">
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-5">
            <div class="card p-4 text-center">
                <p class="text-2xl font-bold text-blue-600">{{ number_format($hasil['total_transaksi']) }}</p>
                <p class="text-xs text-gray-500 mt-1">Total Transaksi</p>
            </div>
            <div class="card p-4 text-center">
                <p class="text-2xl font-bold text-emerald-600">Rp {{ number_format($hasil['total_nilai'], 0, ',', '.') }}</p>
                <p class="text-xs text-gray-500 mt-1">Total Nilai</p>
            </div>
            <div class="card p-4 text-center">
                <p class="text-2xl font-bold text-orange-500">{{ number_format($hasil['jumlah_ritel']) }}</p>
                <p class="text-xs text-gray-500 mt-1">Transaksi Ritel</p>
            </div>
            <div class="card p-4 text-center">
                <p class="text-2xl font-bold text-orange-600">Rp {{ number_format($hasil['total_ritel'], 0, ',', '.') }}</p>
                <p class="text-xs text-gray-500 mt-1">Total Ritel</p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
            <div class="card">
                <div class="card-header"><h3 class="text-sm font-semibold text-gray-700">Per Metode Pembayaran</h3></div>
                <div class="card-body p-0">
                    <table class="table">
                        <thead><tr><th>Metode</th><th class="text-right">Transaksi</th><th class="text-right">Total</th></tr></thead>
                        <tbody>
                            @foreach($hasil['per_metode'] as $metode => $data)
                            <tr>
                                <td class="capitalize font-medium">{{ $metode }}</td>
                                <td class="text-right">{{ number_format($data['jumlah_transaksi']) }}</td>
                                <td class="text-right font-medium">Rp {{ number_format($data['total'], 0, ',', '.') }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card">
                <div class="card-header"><h3 class="text-sm font-semibold text-gray-700">Per Kasir</h3></div>
                <div class="card-body p-0">
                    <table class="table">
                        <thead><tr><th>Kasir</th><th class="text-right">Transaksi</th><th class="text-right">Total</th></tr></thead>
                        <tbody>
                            @foreach($hasil['per_kasir'] as $kasir => $data)
                            <tr>
                                <td class="font-medium">{{ $kasir }}</td>
                                <td class="text-right">{{ number_format($data['count']) }}</td>
                                <td class="text-right font-medium">Rp {{ number_format($data['total'], 0, ',', '.') }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    @else
    <div class="card">
        <div class="card-body">
            <div class="empty-state">
                <svg class="empty-state-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                          d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                <p class="empty-state-text">Pilih periode dan klik "Tampilkan" untuk melihat laporan</p>
            </div>
        </div>
    </div>
    @endif
</div>
