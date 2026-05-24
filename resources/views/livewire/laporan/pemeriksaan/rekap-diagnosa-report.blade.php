<div class="space-y-5">
    <div class="page-header">
        <div>
            <h1 class="page-title">Rekap Data Diagnosa</h1>
            <p class="page-subtitle">10 Besar Penyakit berdasarkan kode ICD-10</p>
        </div>
    </div>

    <x-laporan.filter-periode />

    @if($hasil)
    <div wire:loading.remove wire:target="generate">
        <div class="grid grid-cols-2 gap-4 mb-5">
            <div class="card p-4 text-center">
                <p class="text-2xl font-bold text-blue-600">{{ number_format($hasil['total_diagnosa']) }}</p>
                <p class="text-xs text-gray-500 mt-1">Total Diagnosa</p>
            </div>
            <div class="card p-4 text-center">
                <p class="text-2xl font-bold text-purple-600">{{ number_format($hasil['jumlah_jenis']) }}</p>
                <p class="text-xs text-gray-500 mt-1">Jenis Diagnosa</p>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h3 class="text-sm font-semibold text-gray-700">10 Besar Penyakit</h3></div>
            <div class="card-body p-0">
                <table class="table">
                    <thead>
                        <tr><th>Ranking</th><th>Kode ICD</th><th class="text-right">Jumlah Kasus</th><th class="text-right">Persentase</th></tr>
                    </thead>
                    <tbody>
                        @php $rank = 1; @endphp
                        @foreach($hasil['sepuluh_besar'] as $kode => $jumlah)
                        <tr>
                            <td class="font-bold text-gray-500">{{ $rank++ }}</td>
                            <td class="font-mono font-semibold">{{ $kode }}</td>
                            <td class="text-right font-medium">{{ number_format($jumlah) }}</td>
                            <td class="text-right text-gray-500">
                                {{ $hasil['total_diagnosa'] > 0 ? round($jumlah / $hasil['total_diagnosa'] * 100, 1) : 0 }}%
                            </td>
                        </tr>
                        @endforeach
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
