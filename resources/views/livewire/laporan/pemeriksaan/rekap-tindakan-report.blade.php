<div class="space-y-5">
    <div class="page-header">
        <div>
            <h1 class="page-title">Rekap Tindakan</h1>
            <p class="page-subtitle">Rekap tindakan medis per periode</p>
        </div>
    </div>

    <x-laporan.filter-periode />

    @if($hasil)
    <div wire:loading.remove wire:target="generate">
        <div class="card p-4 text-center mb-5 w-48">
            <p class="text-2xl font-bold text-blue-600">{{ number_format($hasil['total_tindakan']) }}</p>
            <p class="text-xs text-gray-500 mt-1">Total Tindakan</p>
        </div>

        <div class="card">
            <div class="card-header"><h3 class="text-sm font-semibold text-gray-700">Rekap per Jenis Tindakan</h3></div>
            <div class="card-body p-0">
                <table class="table">
                    <thead>
                        <tr><th>Tindakan</th><th class="text-right">Jumlah</th><th class="text-right">Total Tarif</th></tr>
                    </thead>
                    <tbody>
                        @foreach($hasil['per_tindakan'] as $nama => $data)
                        <tr>
                            <td class="font-medium">{{ $nama ?? 'N/A' }}</td>
                            <td class="text-right">{{ number_format($data['jumlah']) }}</td>
                            <td class="text-right">Rp {{ number_format($data['total_tarif'], 0, ',', '.') }}</td>
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
