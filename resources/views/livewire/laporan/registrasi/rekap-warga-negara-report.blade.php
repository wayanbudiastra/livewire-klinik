<div class="space-y-5">
    <div class="page-header">
        <div>
            <h1 class="page-title">Rekap Warga Negara (WNI/WNA)</h1>
            <p class="page-subtitle">Breakdown pasien berdasarkan kewarganegaraan</p>
        </div>
    </div>

    @include('components.laporan.filter-periode')

    @if($hasil)
    <div wire:loading.remove wire:target="generate">
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-5">
            <div class="card p-4 text-center">
                <p class="text-2xl font-bold text-blue-600">{{ number_format($hasil['total_wni']) }}</p>
                <p class="text-xs text-gray-500 mt-1">Pasien WNI</p>
            </div>
            <div class="card p-4 text-center">
                <p class="text-2xl font-bold text-emerald-600">{{ number_format($hasil['total_wna']) }}</p>
                <p class="text-xs text-gray-500 mt-1">Pasien WNA</p>
            </div>
            <div class="card p-4 text-center">
                <p class="text-2xl font-bold text-purple-600">{{ number_format($hasil['kunjungan_wni']) }}</p>
                <p class="text-xs text-gray-500 mt-1">Kunjungan WNI</p>
            </div>
            <div class="card p-4 text-center">
                <p class="text-2xl font-bold text-amber-600">{{ number_format($hasil['kunjungan_wna']) }}</p>
                <p class="text-xs text-gray-500 mt-1">Kunjungan WNA</p>
            </div>
        </div>

        {{-- Pie Chart WNI vs WNA --}}
        <div
            class="card p-4 mb-5"
            x-data="{
                chart: null,
                wni: @js($hasil['kunjungan_wni']),
                wna: @js($hasil['kunjungan_wna']),
                init() {
                    this.$nextTick(() => this.renderChart());
                },
                renderChart() {
                    if (!window.Chart || !this.$refs.wnaCanvas) return;
                    if (this.chart) { this.chart.destroy(); this.chart = null; }

                    const total = this.wni + this.wna;
                    const pctWni = total > 0 ? ((this.wni / total) * 100).toFixed(1) : 0;
                    const pctWna = total > 0 ? ((this.wna / total) * 100).toFixed(1) : 0;

                    const ctx = this.$refs.wnaCanvas.getContext('2d');
                    this.chart = new Chart(ctx, {
                        type: 'doughnut',
                        data: {
                            labels: [
                                'WNI (' + pctWni + '%)',
                                'WNA (' + pctWna + '%)',
                            ],
                            datasets: [{
                                data: [this.wni || 0.001, this.wna || 0],
                                backgroundColor: ['#3b82f6', '#10b981'],
                                borderColor: ['#2563eb', '#059669'],
                                borderWidth: 2,
                                hoverOffset: 8,
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            cutout: '60%',
                            plugins: {
                                legend: {
                                    position: 'right',
                                    labels: { font: { size: 13 }, padding: 16, boxWidth: 16 }
                                },
                                tooltip: {
                                    callbacks: {
                                        label: (c) => '  ' + c.label + ': ' + (c.raw === 0.001 ? 0 : c.raw).toLocaleString('id-ID') + ' kunjungan'
                                    }
                                }
                            }
                        }
                    });
                }
            }"
            x-on:wna-chart-update.window="
                wni = $event.detail.kunjungan_wni;
                wna = $event.detail.kunjungan_wna;
                $nextTick(() => renderChart());
            "
        >
            <h3 class="text-sm font-semibold text-gray-700 mb-3">Perbandingan Kunjungan WNI vs WNA</h3>
            <div style="height: 260px;">
                <canvas x-ref="wnaCanvas"></canvas>
            </div>
        </div>

        @if($hasil['total_wna'] > 0)
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
            <div class="card">
                <div class="card-header"><h3 class="text-sm font-semibold text-gray-700">WNA per Negara Asal</h3></div>
                <div class="card-body p-0">
                    <table class="table">
                        <thead><tr><th>Negara</th><th class="text-right">Jumlah Pasien</th></tr></thead>
                        <tbody>
                            @foreach($hasil['wna_per_negara'] as $negara => $jumlah)
                            <tr>
                                <td>{{ $negara ?? 'Tidak diketahui' }}</td>
                                <td class="text-right font-medium">{{ number_format($jumlah) }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card">
                <div class="card-header"><h3 class="text-sm font-semibold text-gray-700">Detail Pasien WNA</h3></div>
                <div class="card-body p-0">
                    <table class="table">
                        <thead><tr><th>No. RM</th><th>Nama</th><th>No. Paspor</th><th>Negara</th></tr></thead>
                        <tbody>
                            @foreach($hasil['detail_wna'] as $wna)
                            <tr>
                                <td class="text-sm font-mono">{{ $wna['nomor_rm'] }}</td>
                                <td class="text-sm">{{ $wna['nama'] }}</td>
                                <td class="text-sm font-mono">{{ $wna['no_paspor'] ?? '-' }}</td>
                                <td class="text-sm">{{ $wna['negara_asal'] ?? '-' }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        @else
        <div class="card">
            <div class="card-body text-center text-gray-500 py-8">
                Tidak ada pasien WNA pada periode ini.
            </div>
        </div>
        @endif
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
