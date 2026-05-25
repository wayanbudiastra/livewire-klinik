<div class="space-y-5">
    <div class="page-header">
        <div>
            <h1 class="page-title">Laporan Cancel Bill</h1>
            <p class="page-subtitle">Rekap pembatalan tagihan per periode</p>
        </div>
    </div>

    @include('components.laporan.filter-periode')

    @if($hasil)
    <div wire:loading.remove wire:target="generate">
        <div class="grid grid-cols-2 gap-4 mb-5">
            <div class="card p-4 text-center">
                <p class="text-2xl font-bold text-red-600">{{ number_format($hasil['total_batal']) }}</p>
                <p class="text-xs text-gray-500 mt-1">Total Pembatalan</p>
            </div>
            <div class="card p-4 text-center">
                <p class="text-2xl font-bold text-amber-600">Rp {{ number_format($hasil['total_nilai_batal'], 0, ',', '.') }}</p>
                <p class="text-xs text-gray-500 mt-1">Total Nilai Dibatalkan</p>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h3 class="text-sm font-semibold text-gray-700">Detail Pembatalan</h3></div>
            <div class="card-body p-0 overflow-x-auto">
                <table class="table">
                    <thead>
                        <tr>
                            <th>No. Invoice</th>
                            <th>Tgl Transaksi</th>
                            <th>Tgl Batal</th>
                            <th>Pasien</th>
                            <th>No. RM</th>
                            <th class="text-right">Nilai</th>
                            <th>Alasan</th>
                            <th>Dibatalkan Oleh</th>
                            <th>Diverifikasi Oleh</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($hasil['detail'] as $row)
                        <tr>
                            <td class="text-xs font-mono">{{ $row['nomor_invoice'] }}</td>
                            <td class="text-xs text-gray-500">{{ $row['tanggal_transaksi'] }}</td>
                            <td class="text-xs font-medium text-red-600">{{ $row['tanggal_batal'] ?? '-' }}</td>
                            <td class="text-xs">{{ $row['pasien'] }}</td>
                            <td class="text-xs font-mono text-gray-500">{{ $row['nomor_rm'] }}</td>
                            <td class="text-xs text-right font-semibold">Rp {{ number_format($row['nilai'], 0, ',', '.') }}</td>
                            <td class="text-xs text-gray-600 max-w-xs">{{ $row['alasan'] ?? '-' }}</td>
                            <td class="text-xs">{{ $row['dibatalkan_oleh'] }}</td>
                            <td class="text-xs font-semibold text-indigo-700">{{ $row['diverifikasi_oleh'] }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="9" class="text-center text-gray-400 py-4">Tidak ada data pembatalan pada periode ini</td></tr>
                        @endforelse
                    </tbody>
                    @if(count($hasil['detail']) > 0)
                    <tfoot>
                        <tr class="font-semibold bg-gray-50">
                            <td colspan="5" class="text-right text-xs">Total</td>
                            <td class="text-right text-xs">Rp {{ number_format($hasil['total_nilai_batal'], 0, ',', '.') }}</td>
                            <td colspan="3"></td>
                        </tr>
                    </tfoot>
                    @endif
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
