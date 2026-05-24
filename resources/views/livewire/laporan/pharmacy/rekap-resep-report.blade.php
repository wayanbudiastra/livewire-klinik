<div class="space-y-5">
    <div class="page-header">
        <div>
            <h1 class="page-title">Rekap Resep</h1>
            <p class="page-subtitle">Rekap resep obat per periode</p>
        </div>
    </div>

    @include('components.laporan.filter-periode')

    @if($hasil)
    <div wire:loading.remove wire:target="generate">
        <div class="grid grid-cols-2 md:grid-cols-3 gap-4 mb-5">
            <div class="card p-4 text-center">
                <p class="text-2xl font-bold text-blue-600">{{ number_format($hasil['total_resep']) }}</p>
                <p class="text-xs text-gray-500 mt-1">Total Resep</p>
            </div>
            <div class="card p-4 text-center">
                <p class="text-2xl font-bold text-emerald-600">{{ number_format($hasil['total_item_obat']) }}</p>
                <p class="text-xs text-gray-500 mt-1">Total Item Obat</p>
            </div>
            <div class="card p-4 text-center">
                <p class="text-2xl font-bold text-purple-600">{{ $hasil['per_dokter']->count() }}</p>
                <p class="text-xs text-gray-500 mt-1">Dokter Meresepkan</p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
            <div class="card">
                <div class="card-header"><h3 class="text-sm font-semibold text-gray-700">Per Status Resep</h3></div>
                <div class="card-body p-0">
                    <table class="table">
                        <thead><tr><th>Status</th><th class="text-right">Jumlah</th></tr></thead>
                        <tbody>
                            @foreach($hasil['per_status'] as $status => $jumlah)
                            <tr>
                                <td class="capitalize">{{ $status }}</td>
                                <td class="text-right font-medium">{{ number_format($jumlah) }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card">
                <div class="card-header"><h3 class="text-sm font-semibold text-gray-700">Per Dokter</h3></div>
                <div class="card-body p-0">
                    <table class="table">
                        <thead><tr><th>Dokter</th><th class="text-right">Jumlah Resep</th></tr></thead>
                        <tbody>
                            @foreach($hasil['per_dokter'] as $dokter => $jumlah)
                            <tr>
                                <td>{{ $dokter }}</td>
                                <td class="text-right font-medium">{{ number_format($jumlah) }}</td>
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
