<div wire:poll.30s="refreshData">

    {{-- ── Stat Cards ──────────────────────────────────────────── --}}
    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 xl:grid-cols-4 mb-6">

        {{-- Kunjungan Hari Ini --}}
        <div class="card p-5 flex items-center gap-4">
            <div class="stat-icon bg-blue-50 dark:bg-blue-900/30">
                <svg class="w-6 h-6 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                </svg>
            </div>
            <div>
                <p class="stat-label">Kunjungan Hari Ini</p>
                <p class="stat-value">{{ $kunjunganHariIni }}</p>
                <p class="text-xs text-gray-400 dark:text-gray-500 mt-0.5">Total kunjungan aktif</p>
            </div>
        </div>

        {{-- Pasien Baru --}}
        <div class="card p-5 flex items-center gap-4">
            <div class="stat-icon bg-emerald-50 dark:bg-emerald-900/30">
                <svg class="w-6 h-6 text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/>
                </svg>
            </div>
            <div>
                <p class="stat-label">Pasien Baru</p>
                <p class="stat-value">{{ $pasienBaruBulanIni }}</p>
                <p class="text-xs text-gray-400 dark:text-gray-500 mt-0.5">Registrasi bulan ini</p>
            </div>
        </div>

        {{-- Menunggu Pemeriksaan --}}
        <div class="card p-5 flex items-center gap-4">
            <div class="stat-icon bg-amber-50 dark:bg-amber-900/30">
                <svg class="w-6 h-6 text-amber-500 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
            <div>
                <p class="stat-label">Menunggu Pemeriksaan</p>
                <p class="stat-value">{{ $menungguPemeriksaan }}</p>
                <p class="text-xs text-gray-400 dark:text-gray-500 mt-0.5">Antrean aktif saat ini</p>
            </div>
        </div>

        {{-- Pendapatan Hari Ini --}}
        <div class="card p-5 flex items-center gap-4">
            <div class="stat-icon bg-violet-50 dark:bg-violet-900/30">
                <svg class="w-6 h-6 text-violet-600 dark:text-violet-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
            <div>
                <p class="stat-label">Pendapatan Hari Ini</p>
                <p class="stat-value">Rp {{ number_format($pendapatanHariIni, 0, ',', '.') }}</p>
                <p class="text-xs text-gray-400 dark:text-gray-500 mt-0.5">Total billing lunas hari ini</p>
            </div>
        </div>
    </div>

    {{-- ── Row: Chart & Aksi Cepat ─────────────────────────────── --}}
    <div class="grid grid-cols-1 gap-5 lg:grid-cols-3">

        {{-- Bar Chart Kunjungan 15 Hari --}}
        <div class="lg:col-span-2 card"
            x-data="{
                chart: null,

                {{-- Data awal dari PHP via @js() — tidak bergantung event mount() --}}
                labels: @js($chartLabels),
                total:  @js($chartTotal),
                baru:   @js($chartBaru),
                lama:   @js($chartLama),

                init() {
                    this.$nextTick(() => this.renderChart());
                },

                renderChart() {
                    if (!window.Chart || !this.$refs.kunjunganCanvas) return;
                    if (this.chart) { this.chart.destroy(); this.chart = null; }

                    const ctx = this.$refs.kunjunganCanvas.getContext('2d');
                    this.chart = new window.Chart(ctx, {
                        type: 'bar',
                        data: {
                            labels: this.labels,
                            datasets: [
                                {
                                    label: 'Total Kunjungan',
                                    data: this.total,
                                    backgroundColor: 'rgba(99, 102, 241, 0.85)',
                                    borderColor: '#4f46e5',
                                    borderWidth: 1,
                                    borderRadius: 4,
                                    order: 1,
                                },
                                {
                                    label: 'Pasien Baru',
                                    data: this.baru,
                                    backgroundColor: 'rgba(16, 185, 129, 0.85)',
                                    borderColor: '#059669',
                                    borderWidth: 1,
                                    borderRadius: 4,
                                    order: 2,
                                },
                                {
                                    label: 'Pasien Lama',
                                    data: this.lama,
                                    backgroundColor: 'rgba(59, 130, 246, 0.75)',
                                    borderColor: '#2563eb',
                                    borderWidth: 1,
                                    borderRadius: 4,
                                    order: 3,
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
                                    labels: {
                                        font: { size: 12 },
                                        padding: 12,
                                        boxWidth: 12,
                                        boxHeight: 12,
                                    }
                                },
                                tooltip: {
                                    callbacks: {
                                        label: (c) => ' ' + c.dataset.label + ': ' + c.raw + ' kunjungan'
                                    }
                                }
                            },
                            scales: {
                                x: {
                                    grid: { display: false },
                                    ticks: { font: { size: 11 } }
                                },
                                y: {
                                    beginAtZero: true,
                                    ticks: {
                                        precision: 0,
                                        font: { size: 11 }
                                    },
                                    grid: { color: 'rgba(0,0,0,0.05)' }
                                }
                            }
                        }
                    });
                }
            }"
            x-on:dashboard-chart-update.window="
                labels = $event.detail.labels;
                total  = $event.detail.total;
                baru   = $event.detail.baru;
                lama   = $event.detail.lama;
                $nextTick(() => renderChart())
            "
        >
            <div class="card-header">
                <div>
                    <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-100">Kunjungan Pasien — 15 Hari Terakhir</h3>
                    <p class="text-xs text-gray-400 mt-0.5">Total · Pasien Baru · Pasien Lama</p>
                </div>
                <a href="{{ route('kunjungan.index') }}" class="text-xs text-primary-600 hover:underline dark:text-primary-400">
                    Lihat semua
                </a>
            </div>
            <div class="card-body">
                <div style="height: 280px; position: relative;">
                    <canvas x-ref="kunjunganCanvas"></canvas>
                </div>
            </div>
        </div>

        {{-- Aksi Cepat --}}
        <div class="card">
            <div class="card-header">
                <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-100">Aksi Cepat</h3>
            </div>
            <div class="card-body space-y-2.5">
                @can('kunjungan.create')
                <a href="{{ route('kunjungan.pendaftaran') }}"
                   class="flex items-center gap-3 rounded-lg border border-gray-200 p-3 text-sm text-gray-700
                          hover:border-primary-300 hover:bg-primary-50 transition-colors
                          dark:border-gray-600 dark:text-gray-300 dark:hover:border-blue-500 dark:hover:bg-blue-900/20">
                    <div class="flex h-8 w-8 items-center justify-center rounded-md bg-blue-100 text-blue-600 flex-shrink-0 dark:bg-blue-900/40 dark:text-blue-400">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                        </svg>
                    </div>
                    <span class="font-medium">Daftarkan Kunjungan</span>
                </a>
                @endcan

                @can('pasien.create')
                <a href="{{ route('pasien.create') }}"
                   class="flex items-center gap-3 rounded-lg border border-gray-200 p-3 text-sm text-gray-700
                          hover:border-emerald-300 hover:bg-emerald-50 transition-colors
                          dark:border-gray-600 dark:text-gray-300 dark:hover:border-emerald-500 dark:hover:bg-emerald-900/20">
                    <div class="flex h-8 w-8 items-center justify-center rounded-md bg-emerald-100 text-emerald-600 flex-shrink-0 dark:bg-emerald-900/40 dark:text-emerald-400">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/>
                        </svg>
                    </div>
                    <span class="font-medium">Registrasi Pasien Baru</span>
                </a>
                @endcan

                @can('resep.view')
                <a href="{{ route('farmasi.resep.index') }}"
                   class="flex items-center gap-3 rounded-lg border border-gray-200 p-3 text-sm text-gray-700
                          hover:border-amber-300 hover:bg-amber-50 transition-colors
                          dark:border-gray-600 dark:text-gray-300 dark:hover:border-amber-500 dark:hover:bg-amber-900/20">
                    <div class="flex h-8 w-8 items-center justify-center rounded-md bg-amber-100 text-amber-600 flex-shrink-0 dark:bg-amber-900/40 dark:text-amber-400">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"/>
                        </svg>
                    </div>
                    <span class="font-medium">Cek Resep Masuk</span>
                </a>
                @endcan

                @can('laporan.view')
                <a href="{{ route('laporan.index') }}"
                   class="flex items-center gap-3 rounded-lg border border-gray-200 p-3 text-sm text-gray-700
                          hover:border-violet-300 hover:bg-violet-50 transition-colors
                          dark:border-gray-600 dark:text-gray-300 dark:hover:border-violet-500 dark:hover:bg-violet-900/20">
                    <div class="flex h-8 w-8 items-center justify-center rounded-md bg-violet-100 text-violet-600 flex-shrink-0 dark:bg-violet-900/40 dark:text-violet-400">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                    </div>
                    <span class="font-medium">Lihat Laporan</span>
                </a>
                @endcan
            </div>
        </div>
    </div>
</div>
