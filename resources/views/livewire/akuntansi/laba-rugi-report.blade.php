<div class="space-y-4">

    <div class="card">
        <div class="card-body">
            <div class="flex flex-wrap gap-3 items-end">
                <div class="form-group mb-0">
                    <label class="form-label">Dari</label>
                    <input type="date" wire:model.live="dari" class="form-input dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200" />
                </div>
                <div class="form-group mb-0">
                    <label class="form-label">Sampai</label>
                    <input type="date" wire:model.live="sampai" class="form-input dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200" />
                </div>
            </div>
        </div>
    </div>

    <div class="card"
        x-data="{
            chart: null,
            labels: @js($this->trendYtd['labels']),
            pendapatan: @js($this->trendYtd['pendapatan']),
            labaBersih: @js($this->trendYtd['laba_bersih']),
            init() {
                this.$nextTick(() => this.renderChart());
            },
            renderChart() {
                if (!window.Chart || !this.$refs.trendCanvas) return;
                if (this.chart) { this.chart.destroy(); this.chart = null; }

                const ctx = this.$refs.trendCanvas.getContext('2d');
                this.chart = new window.Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: this.labels,
                        datasets: [
                            {
                                label: 'Pendapatan',
                                data: this.pendapatan,
                                borderColor: '#4f46e5',
                                backgroundColor: 'rgba(99, 102, 241, 0.1)',
                                borderWidth: 2,
                                tension: 0.3,
                                fill: true,
                                pointRadius: 3,
                            },
                            {
                                label: 'Laba Bersih',
                                data: this.labaBersih,
                                borderColor: '#059669',
                                backgroundColor: 'rgba(16, 185, 129, 0.1)',
                                borderWidth: 2,
                                tension: 0.3,
                                fill: true,
                                pointRadius: 3,
                            },
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        interaction: { mode: 'index', intersect: false },
                        plugins: {
                            legend: {
                                position: 'top',
                                labels: { font: { size: 12 }, padding: 12, boxWidth: 12, boxHeight: 12 }
                            },
                            tooltip: {
                                callbacks: {
                                    label: (c) => ' ' + c.dataset.label + ': Rp ' + c.raw.toLocaleString('id-ID')
                                }
                            }
                        },
                        scales: {
                            x: { grid: { display: false }, ticks: { font: { size: 11 } } },
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    font: { size: 11 },
                                    callback: (v) => 'Rp ' + (v / 1000) + 'rb'
                                },
                                grid: { color: 'rgba(0,0,0,0.05)' }
                            }
                        }
                    }
                });
            }
        }"
    >
        <div class="card-header">
            <h3 class="text-sm font-semibold dark:text-white">Pertumbuhan Pendapatan & Laba Bersih — Year to Date {{ $this->trendYtd['tahun'] }}</h3>
        </div>
        <div class="card-body">
            <div style="height: 280px; position: relative;">
                <canvas x-ref="trendCanvas"></canvas>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3 class="text-sm font-semibold dark:text-white">
                Laba Rugi — {{ \Carbon\Carbon::parse($dari)->format('d M Y') }} s/d {{ \Carbon\Carbon::parse($sampai)->format('d M Y') }}
            </h3>
        </div>
        <div class="card-body">
            <table class="table">
                <tbody>
                    <tr><td colspan="2" class="font-semibold text-sm text-gray-700 dark:text-gray-200 pt-0">PENDAPATAN</td></tr>
                    @forelse($this->hasil['pendapatan'] as $p)
                    <tr>
                        <td class="pl-6 text-sm text-gray-600 dark:text-gray-300">{{ $p['akun']->nama }}</td>
                        <td class="text-right text-sm">Rp {{ number_format($p['nominal'], 0, ',', '.') }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="2" class="pl-6 text-sm text-gray-400">Tidak ada pendapatan pada periode ini</td></tr>
                    @endforelse
                    <tr class="border-t border-gray-200 dark:border-gray-700">
                        <td class="text-sm font-semibold text-gray-700 dark:text-gray-200 py-2">Total Pendapatan</td>
                        <td class="text-right text-sm font-bold py-2">Rp {{ number_format($this->hasil['total_pendapatan'], 0, ',', '.') }}</td>
                    </tr>

                    <tr><td colspan="2" class="font-semibold text-sm text-gray-700 dark:text-gray-200 pt-6">BIAYA</td></tr>
                    @forelse($this->hasil['biaya'] as $b)
                    <tr>
                        <td class="pl-6 text-sm text-gray-600 dark:text-gray-300">{{ $b['akun']->nama }}</td>
                        <td class="text-right text-sm">Rp {{ number_format($b['nominal'], 0, ',', '.') }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="2" class="pl-6 text-sm text-gray-400">Tidak ada biaya pada periode ini</td></tr>
                    @endforelse
                    <tr class="border-t border-gray-200 dark:border-gray-700">
                        <td class="text-sm font-semibold text-gray-700 dark:text-gray-200 py-2">Total Biaya</td>
                        <td class="text-right text-sm font-bold py-2">Rp {{ number_format($this->hasil['total_biaya'], 0, ',', '.') }}</td>
                    </tr>

                    <tr class="border-t-2 border-gray-300 dark:border-gray-600">
                        <td class="text-base font-bold py-3 {{ $this->hasil['laba_rugi'] >= 0 ? 'text-emerald-600' : 'text-red-600' }}">
                            {{ $this->hasil['laba_rugi'] >= 0 ? 'LABA BERSIH' : 'RUGI BERSIH' }}
                        </td>
                        <td class="text-right text-base font-bold py-3 {{ $this->hasil['laba_rugi'] >= 0 ? 'text-emerald-600' : 'text-red-600' }}">
                            Rp {{ number_format(abs($this->hasil['laba_rugi']), 0, ',', '.') }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

</div>
