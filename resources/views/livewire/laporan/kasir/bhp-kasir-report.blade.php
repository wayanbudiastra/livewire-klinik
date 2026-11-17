<div class="space-y-5">
    <div class="page-header">
        <div>
            <h1 class="page-title">Laporan BHP Kasir</h1>
            <p class="page-subtitle">BHP yang diinput kasir pada tagihan pasien, per periode</p>
        </div>
    </div>

    @include('components.laporan.filter-periode')

    @if($hasil)
    <div wire:loading.remove wire:target="generate">
        <div class="grid grid-cols-2 gap-4 mb-5">
            <div class="card p-4 text-center">
                <p class="text-2xl font-bold text-indigo-600">{{ number_format($hasil['total_baris']) }}</p>
                <p class="text-xs text-gray-500 mt-1">Jumlah Baris</p>
            </div>
            <div class="card p-4 text-center">
                <p class="text-2xl font-bold text-emerald-600">{{ number_format($hasil['total_qty']) }}</p>
                <p class="text-xs text-gray-500 mt-1">Total Qty</p>
            </div>
        </div>

        @if($hasil['terpotong'])
        <p class="mb-3 text-xs text-amber-600">
            Menampilkan {{ number_format($hasil['batas']) }} baris terbaru dari {{ number_format($hasil['total_baris']) }}. Persempit periode untuk melihat sisanya.
        </p>
        @endif

        <div class="card">
            <div class="card-header"><h3 class="text-sm font-semibold text-gray-700">Detail BHP Kasir</h3></div>
            <div class="card-body p-0 overflow-x-auto">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Tanggal</th>
                            <th>No. RM</th>
                            <th>Pasien</th>
                            <th>Barang</th>
                            <th class="text-right">Qty</th>
                            <th>Dicatat Oleh</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($hasil['detail'] as $row)
                        <tr>
                            <td class="text-xs text-gray-500">{{ $row['tanggal'] }}</td>
                            <td class="text-xs font-mono text-gray-500">{{ $row['nomor_rm'] }}</td>
                            <td class="text-xs">{{ $row['pasien'] }}</td>
                            <td class="text-xs">{{ $row['barang'] }}</td>
                            <td class="text-xs text-right font-semibold">{{ number_format($row['qty']) }} {{ $row['satuan'] }}</td>
                            <td class="text-xs">{{ $row['kasir'] }}</td>
                            <td class="text-xs {{ $row['status'] === 'Menunggu lunas' ? 'text-amber-600' : 'text-emerald-700' }}">{{ $row['status'] }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="7" class="text-center text-gray-400 py-4">Tidak ada BHP kasir pada periode ini</td></tr>
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
