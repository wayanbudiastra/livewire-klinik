@push('scripts')
@once
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
@endonce
@endpush

<div class="space-y-5">
    <div class="page-header">
        <div>
            <h1 class="page-title">Rekap Data Diagnosa</h1>
            <p class="page-subtitle">{{ $topN }} Besar Penyakit berdasarkan kode ICD-10</p>
        </div>
    </div>

    @include('components.laporan.filter-periode')

    @if($hasil)
    <div
        wire:loading.remove wire:target="generate,updatedTopN"
        x-data="{
            chartType: 'bar',
            chart: null,
            labels: @js(array_keys($hasil['n_besar'])),
            values: @js(array_values($hasil['n_besar'])),
            palette: [
                '#3b82f6','#10b981','#8b5cf6','#f59e0b','#ef4444',
                '#ec4899','#0ea5e9','#a855f7','#22c55e','#fb923c',
                '#6366f1','#f43f5e','#14b8a6','#eab308','#64748b',
                '#06b6d4','#d946ef','#84cc16','#f97316','#2563eb',
                '#7c3aed','#059669','#dc2626','#db2777','#0284c7',
                '#9333ea','#16a34a','#ea580c','#4f46e5','#0891b2',
                '#c026d3','#65a30d','#e11d48','#7c3aed','#0d9488',
                '#ca8a04','#475569','#0369a1','#6d28d9','#15803d',
                '#b91c1c','#be185d','#0e7490','#7e22ce','#166534',
                '#c2410c','#4338ca','#0c4a6e','#581c87','#14532d',
            ],
            init() {
                this.\$nextTick(() => this.renderChart());
            },
            renderChart() {
                if (!window.Chart || !this.\$refs.chartCanvas || this.labels.length === 0) return;
                if (this.chart) { this.chart.destroy(); this.chart = null; }

                const ctx = this.\$refs.chartCanvas.getContext('2d');
                const bg  = this.labels.map((_, i) => this.palette[i % this.palette.length]);

                const isBar = this.chartType === 'bar';

                this.chart = new Chart(ctx, {
                    type: isBar ? 'bar' : 'pie',
                    data: {
                        labels: this.labels,
                        datasets: [{
                            label: 'Jumlah Kasus',
                            data: this.values,
                            backgroundColor: bg,
                            borderColor: isBar ? bg : '#fff',
                            borderWidth: isBar ? 0 : 2,
                            borderRadius: isBar ? 5 : 0,
                        }]
                    },
                    options: isBar ? {
                        indexAxis: 'y',
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                callbacks: {
                                    label: (c) => '  ' + c.parsed.x.toLocaleString('id-ID') + ' kasus'
                                }
                            }
                        },
                        scales: {
                            x: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.05)' }, ticks: { font: { size: 11 } } },
                            y: { grid: { display: false }, ticks: { font: { size: 11 } } }
                        }
                    } : {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'right',
                                labels: { font: { size: 11 }, padding: 14, boxWidth: 14 }
                            },
                            tooltip: {
                                callbacks: {
                                    label: (c) => '  ' + c.parsed.toLocaleString('id-ID') + ' kasus'
                                }
                            }
                        }
                    }
                });
            },
            switchType(type) {
                this.chartType = type;
                this.\$nextTick(() => this.renderChart());
            }
        }"
        x-on:diagnosa-chart-update.window="
            labels = $event.detail.labels;
            values = $event.detail.values;
            $nextTick(() => renderChart());
        "
    >
        {{-- Stats --}}
        <div class="grid grid-cols-2 gap-4">
            <div class="card p-4 text-center">
                <p class="text-2xl font-bold text-blue-600">{{ number_format($hasil['total_diagnosa']) }}</p>
                <p class="text-xs text-gray-500 mt-1">Total Diagnosa</p>
            </div>
            <div class="card p-4 text-center">
                <p class="text-2xl font-bold text-purple-600">{{ number_format($hasil['jumlah_jenis']) }}</p>
                <p class="text-xs text-gray-500 mt-1">Jenis Diagnosa</p>
            </div>
        </div>

        {{-- Controls: Top N + Chart Type --}}
        <div class="card p-4">
            <div class="flex flex-wrap items-center gap-4">
                {{-- Top N --}}
                <div class="flex items-center gap-2">
                    <span class="text-sm font-medium text-gray-600">Tampilkan:</span>
                    @foreach([10, 20, 50] as $n)
                    <button
                        wire:click="$set('topN', {{ $n }})"
                        @class([
                            'px-3 py-1.5 rounded-lg text-sm font-medium transition-colors',
                            'bg-blue-600 text-white shadow-sm' => $topN === $n,
                            'bg-gray-100 text-gray-600 hover:bg-gray-200' => $topN !== $n,
                        ])
                    >Top {{ $n }}</button>
                    @endforeach
                </div>

                <div class="h-5 w-px bg-gray-200"></div>

                {{-- Chart Type --}}
                <div class="flex items-center gap-2">
                    <span class="text-sm font-medium text-gray-600">Grafik:</span>
                    <button
                        @click="switchType('bar')"
                        :class="chartType === 'bar'
                            ? 'bg-indigo-600 text-white shadow-sm'
                            : 'bg-gray-100 text-gray-600 hover:bg-gray-200'"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-sm font-medium transition-colors"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                        </svg>
                        Bar
                    </button>
                    <button
                        @click="switchType('pie')"
                        :class="chartType === 'pie'
                            ? 'bg-indigo-600 text-white shadow-sm'
                            : 'bg-gray-100 text-gray-600 hover:bg-gray-200'"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-sm font-medium transition-colors"
                    >
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 17.93V13H4.07A8.015 8.015 0 0 1 11 4.07V11h6.93A8.015 8.015 0 0 1 13 19.93V13H6.07z" opacity="0"/>
                            <path d="M11 2.07V11H2.07C2.56 6.08 6.08 2.56 11 2.07zM13 2.07C17.92 2.56 21.44 6.08 21.93 11H13V2.07zM11 13v8.93C6.08 21.44 2.56 17.92 2.07 13H11zM13 21.93V13h8.93C21.44 17.92 17.92 21.44 13 21.93z"/>
                        </svg>
                        Pie
                    </button>
                </div>
            </div>
        </div>

        {{-- Chart --}}
        <div class="card p-4">
            <div
                :style="chartType === 'bar'
                    ? 'height: ' + Math.max(320, labels.length * 36) + 'px'
                    : 'height: 420px'"
            >
                <canvas x-ref="chartCanvas"></canvas>
            </div>
        </div>

        {{-- Table --}}
        <div class="card">
            <div class="card-header">
                <h3 class="text-sm font-semibold text-gray-700">{{ $topN }} Besar Penyakit</h3>
            </div>
            <div class="card-body p-0">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Ranking</th>
                            <th>Kode ICD</th>
                            <th class="text-right">Jumlah Kasus</th>
                            <th class="text-right">Persentase</th>
                            <th>Proporsi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php $rank = 1; @endphp
                        @foreach($hasil['n_besar'] as $kode => $jumlah)
                        @php
                            $pct = $hasil['total_diagnosa'] > 0
                                ? round($jumlah / $hasil['total_diagnosa'] * 100, 1)
                                : 0;
                        @endphp
                        <tr>
                            <td>
                                <span @class([
                                    'inline-flex items-center justify-center w-6 h-6 rounded-full text-xs font-bold',
                                    'bg-yellow-100 text-yellow-700' => $rank === 1,
                                    'bg-gray-100 text-gray-600'     => $rank === 2,
                                    'bg-orange-100 text-orange-600' => $rank === 3,
                                    'text-gray-400 font-medium'     => $rank > 3,
                                ])>{{ $rank++ }}</span>
                            </td>
                            <td class="font-mono font-semibold">{{ $kode }}</td>
                            <td class="text-right font-medium">{{ number_format($jumlah) }}</td>
                            <td class="text-right text-gray-500">{{ $pct }}%</td>
                            <td class="w-36">
                                <div class="flex items-center gap-2">
                                    <div class="flex-1 h-2 rounded-full bg-gray-100 overflow-hidden">
                                        <div class="h-full rounded-full bg-blue-500" style="width: {{ $pct }}%"></div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div wire:loading wire:target="generate,updatedTopN" class="card p-8 text-center">
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
