<div class="space-y-5">
    <div class="page-header">
        <div>
            <h1 class="page-title">Laporan Appointment</h1>
            <p class="page-subtitle">Rekap jadwal appointment dan rasio kehadiran</p>
        </div>
    </div>

    @include('components.laporan.filter-periode')

    @if($hasil)
    <div wire:loading.remove wire:target="generate">
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-5">
            <div class="card p-4 text-center">
                <p class="text-2xl font-bold text-blue-600">{{ number_format($hasil['total']) }}</p>
                <p class="text-xs text-gray-500 mt-1">Total Appointment</p>
            </div>
            <div class="card p-4 text-center">
                <p class="text-2xl font-bold text-emerald-600">{{ number_format($hasil['hadir']) }}</p>
                <p class="text-xs text-gray-500 mt-1">Hadir</p>
            </div>
            <div class="card p-4 text-center">
                <p class="text-2xl font-bold text-red-600">{{ number_format($hasil['tidak_hadir']) }}</p>
                <p class="text-xs text-gray-500 mt-1">Tidak Hadir</p>
            </div>
            <div class="card p-4 text-center">
                <p class="text-2xl font-bold text-amber-600">{{ $hasil['rasio_hadir'] }}%</p>
                <p class="text-xs text-gray-500 mt-1">Rasio Kehadiran</p>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h3 class="text-sm font-semibold text-gray-700">Detail Appointment</h3></div>
            <div class="card-body p-0">
                <table class="table">
                    <thead>
                        <tr><th>Tanggal</th><th>Kode Booking</th><th>Pasien</th><th>Dokter</th><th>Poli</th><th>Status</th></tr>
                    </thead>
                    <tbody>
                        @foreach($hasil['detail'] as $a)
                        <tr>
                            <td class="text-sm">{{ \Carbon\Carbon::parse($a->tanggal_appointment)->format('d/m/Y') }}</td>
                            <td class="text-sm font-mono">{{ $a->kode_booking }}</td>
                            <td class="text-sm">{{ $a->pasien?->nama ?? '-' }}</td>
                            <td class="text-sm">{{ $a->dokter?->user?->nama ?? '-' }}</td>
                            <td class="text-sm">{{ $a->poli?->nama ?? '-' }}</td>
                            <td class="text-sm capitalize">{{ $a->status }}</td>
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
