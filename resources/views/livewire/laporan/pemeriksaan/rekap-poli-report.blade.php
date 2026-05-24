<div class="space-y-5">
    <div class="page-header">
        <div>
            <h1 class="page-title">Rekap per Poli</h1>
            <p class="page-subtitle">Ranking poli berdasarkan jumlah kunjungan</p>
        </div>
    </div>

    <x-laporan.filter-periode />

    @if($hasil)
    <div wire:loading.remove wire:target="generate">
        <div class="card">
            <div class="card-header"><h3 class="text-sm font-semibold text-gray-700">Kunjungan per Poli</h3></div>
            <div class="card-body p-0">
                <table class="table">
                    <thead><tr><th>Ranking</th><th>Poli</th><th class="text-right">Total Kunjungan</th></tr></thead>
                    <tbody>
                        @foreach($hasil as $i => $row)
                        <tr>
                            <td class="font-bold text-gray-500">{{ $i + 1 }}</td>
                            <td class="font-medium">{{ $row['poli'] }}</td>
                            <td class="text-right font-medium">{{ number_format($row['total']) }}</td>
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
