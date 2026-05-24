<div class="space-y-5">
    <div class="page-header">
        <div>
            <h1 class="page-title">Laporan Deposit Pasien</h1>
            <p class="page-subtitle">Rekap transaksi deposit (topup, pemakaian, refund)</p>
        </div>
    </div>

    @include('components.laporan.filter-periode')

    @if($hasil)
    <div wire:loading.remove wire:target="generate">
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-5">
            <div class="card p-4 text-center">
                <p class="text-2xl font-bold text-emerald-600">Rp {{ number_format($hasil['total_topup'], 0, ',', '.') }}</p>
                <p class="text-xs text-gray-500 mt-1">Total Topup</p>
            </div>
            <div class="card p-4 text-center">
                <p class="text-2xl font-bold text-blue-600">Rp {{ number_format($hasil['total_pemakaian'], 0, ',', '.') }}</p>
                <p class="text-xs text-gray-500 mt-1">Total Pemakaian</p>
            </div>
            <div class="card p-4 text-center">
                <p class="text-2xl font-bold text-amber-600">Rp {{ number_format($hasil['total_refund'], 0, ',', '.') }}</p>
                <p class="text-xs text-gray-500 mt-1">Total Refund</p>
            </div>
            <div class="card p-4 text-center">
                <p class="text-2xl font-bold text-purple-600">Rp {{ number_format($hasil['total_saldo_aktif'], 0, ',', '.') }}</p>
                <p class="text-xs text-gray-500 mt-1">Saldo Aktif Seluruh Pasien</p>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h3 class="text-sm font-semibold text-gray-700">Detail Transaksi Deposit</h3></div>
            <div class="card-body p-0">
                <table class="table">
                    <thead>
                        <tr><th>Tanggal</th><th>No. Transaksi</th><th>Pasien</th><th>No. RM</th><th>Tipe</th><th class="text-right">Jumlah</th><th class="text-right">Saldo Sesudah</th></tr>
                    </thead>
                    <tbody>
                        @forelse($hasil['detail'] as $row)
                        <tr>
                            <td class="text-sm">{{ $row['tanggal'] }}</td>
                            <td class="text-sm font-mono">{{ $row['nomor'] }}</td>
                            <td class="text-sm">{{ $row['pasien'] }}</td>
                            <td class="text-sm font-mono">{{ $row['nomor_rm'] }}</td>
                            <td><span class="badge {{ $row['tipe'] === 'topup' ? 'badge-success' : ($row['tipe'] === 'refund' ? 'badge-warning' : 'badge-info') }}">{{ ucfirst($row['tipe']) }}</span></td>
                            <td class="text-right font-medium">Rp {{ number_format($row['jumlah'], 0, ',', '.') }}</td>
                            <td class="text-right text-gray-600">Rp {{ number_format($row['saldo_sesudah'], 0, ',', '.') }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="7" class="text-center text-gray-400 py-4">Tidak ada data</td></tr>
                        @endforelse
                    </tbody>
                </table>
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
