<div class="space-y-5">
    <div class="page-header">
        <div>
            <h1 class="page-title">Laporan Sumber Informasi Pasien</h1>
            <p class="page-subtitle">Analisis channel akuisisi pasien baru</p>
        </div>
    </div>

    @include('components.laporan.filter-periode')

    @if($hasil)
    <div wire:loading.remove wire:target="generate">

        {{-- Summary Cards --}}
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-5">
            <div class="card p-4 text-center">
                <p class="text-2xl font-bold text-blue-600">{{ number_format($hasil['total_pasien_baru']) }}</p>
                <p class="text-xs text-gray-500 mt-1">Total Pasien Baru</p>
            </div>
            <div class="card p-4 text-center">
                <p class="text-2xl font-bold text-emerald-600">
                    {{ $hasil['per_sumber']->keys()->first() ?? '-' }}
                </p>
                <p class="text-xs text-gray-500 mt-1">Sumber Teratas</p>
            </div>
            <div class="card p-4 text-center">
                <p class="text-2xl font-bold text-amber-600">{{ number_format($hasil['tidak_tercatat']) }}</p>
                <p class="text-xs text-gray-500 mt-1">Tidak Tercatat</p>
            </div>
        </div>

        @if($hasil['total_pasien_baru'] > 0)

        {{-- Distribusi per Sumber --}}
        <div class="card mb-5">
            <div class="card-header">
                <h3 class="text-sm font-semibold text-gray-700">Distribusi per Sumber</h3>
            </div>
            <div class="card-body space-y-3">
                @foreach($hasil['per_sumber'] as $nama => $data)
                <div>
                    <div class="flex items-center justify-between text-sm mb-1">
                        <span class="font-medium text-gray-700">
                            {{ $data['icon'] }} {{ $nama }}
                        </span>
                        <span class="text-gray-500">
                            {{ number_format($data['jumlah']) }} pasien &middot; {{ $data['persen'] }}%
                        </span>
                    </div>
                    <div class="w-full bg-gray-100 rounded-full h-2">
                        <div class="bg-blue-500 h-2 rounded-full transition-all"
                             style="width: {{ $data['persen'] }}%"></div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>

        {{-- Tabel ringkas --}}
        <div class="card mb-5">
            <div class="card-header">
                <h3 class="text-sm font-semibold text-gray-700">Tabel Rekap Sumber</h3>
            </div>
            <div class="card-body p-0">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Sumber</th>
                            <th>Kategori</th>
                            <th class="text-right">Jumlah Pasien</th>
                            <th class="text-right">Persentase</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($hasil['per_sumber'] as $nama => $data)
                        <tr>
                            <td class="font-medium">{{ $data['icon'] }} {{ $nama }}</td>
                            <td>
                                <span class="badge badge-gray text-xs">
                                    {{ ucfirst(str_replace('_', ' ', $data['kategori'])) }}
                                </span>
                            </td>
                            <td class="text-right font-medium">{{ number_format($data['jumlah']) }}</td>
                            <td class="text-right text-gray-500">{{ $data['persen'] }}%</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Detail keterangan "Lainnya" --}}
        @if($hasil['detail_lainnya']->count() > 0)
        <div class="card">
            <div class="card-header">
                <h3 class="text-sm font-semibold text-gray-700">Rincian Sumber "Lainnya"</h3>
            </div>
            <div class="card-body p-0">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Keterangan</th>
                            <th class="text-right">Jumlah Pasien</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($hasil['detail_lainnya'] as $ket => $jml)
                        <tr>
                            <td>{{ $ket }}</td>
                            <td class="text-right font-medium">{{ $jml }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endif

        @else
        <div class="card">
            <div class="card-body text-center text-gray-500 py-6">
                Tidak ada pasien baru pada periode ini.
            </div>
        </div>
        @endif

    </div>

    <div wire:loading wire:target="generate" class="card p-8 text-center">
        <div class="inline-flex items-center gap-2 text-gray-500">
            <div class="spinner w-5 h-5"></div>
            <span>Memuat data...</span>
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
