<div class="space-y-5">
    <div class="page-header">
        <div>
            <h1 class="page-title">Laporan Kunjungan Pasien</h1>
            <p class="page-subtitle">Rekap kunjungan pasien per periode</p>
        </div>
    </div>

    @include('components.laporan.filter-periode')

    @if($hasil)
    <div wire:loading.remove wire:target="generate">
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-5">
            <div class="card p-4 text-center">
                <p class="text-2xl font-bold text-blue-600">{{ number_format($hasil['total_kunjungan']) }}</p>
                <p class="text-xs text-gray-500 mt-1">Total Kunjungan</p>
            </div>
            <div class="card p-4 text-center">
                <p class="text-2xl font-bold text-emerald-600">{{ number_format($hasil['pasien_baru']) }}</p>
                <p class="text-xs text-gray-500 mt-1">Pasien Baru</p>
            </div>
            <div class="card p-4 text-center">
                <p class="text-2xl font-bold text-purple-600">{{ number_format($hasil['pasien_lama']) }}</p>
                <p class="text-xs text-gray-500 mt-1">Pasien Lama</p>
            </div>
            <div class="card p-4 text-center">
                <p class="text-2xl font-bold text-amber-600">{{ $hasil['per_poli']->count() }}</p>
                <p class="text-xs text-gray-500 mt-1">Poli Aktif</p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
            <div class="card">
                <div class="card-header"><h3 class="text-sm font-semibold text-gray-700">Kunjungan per Poli</h3></div>
                <div class="card-body p-0">
                    <table class="table">
                        <thead><tr><th>Poli</th><th class="text-right">Jumlah</th></tr></thead>
                        <tbody>
                            @foreach($hasil['per_poli'] as $poli => $jumlah)
                            <tr>
                                <td>{{ $poli ?? 'Tanpa Poli' }}</td>
                                <td class="text-right font-medium">{{ number_format($jumlah) }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card">
                <div class="card-header"><h3 class="text-sm font-semibold text-gray-700">Per Tipe Pembayaran</h3></div>
                <div class="card-body p-0">
                    <table class="table">
                        <thead><tr><th>Tipe</th><th class="text-right">Jumlah</th></tr></thead>
                        <tbody>
                            @foreach($hasil['per_tipe_bayar'] as $tipe => $jumlah)
                            <tr>
                                <td class="capitalize">{{ $tipe ?? 'Umum' }}</td>
                                <td class="text-right font-medium">{{ number_format($jumlah) }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card mt-5">
            <div class="card-header"><h3 class="text-sm font-semibold text-gray-700">Detail Kunjungan</h3></div>
            <div class="card-body p-0">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Tanggal</th><th>No. Antrean</th><th>No. RM</th><th>Nama Pasien</th>
                            <th>Poli</th><th>Dokter</th><th>Tipe Bayar</th><th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($hasil['detail'] as $k)
                        <tr>
                            <td class="text-sm">{{ \Carbon\Carbon::parse($k->tanggal)->format('d/m/Y') }}</td>
                            <td class="text-sm">{{ $k->nomor_antrean ?? '-' }}</td>
                            <td class="text-sm font-mono">{{ $k->pasien->nomor_rm ?? '-' }}</td>
                            <td class="text-sm">{{ $k->pasien->nama ?? '-' }}</td>
                            <td class="text-sm">{{ $k->poli?->nama ?? '-' }}</td>
                            <td class="text-sm">{{ $k->dokter?->user->nama ?? '-' }}</td>
                            <td class="text-sm capitalize">{{ $k->tipe_pembayaran ?? 'Umum' }}</td>
                            <td class="text-sm capitalize">{{ $k->status }}</td>
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
