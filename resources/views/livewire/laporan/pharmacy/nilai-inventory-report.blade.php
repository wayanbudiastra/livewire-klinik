<div class="space-y-5">
    <div class="page-header">
        <div>
            <h1 class="page-title">Nilai Inventory</h1>
            <p class="page-subtitle">Snapshot nilai stok real-time (stok × harga pokok)</p>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="flex items-center gap-3">
                <button type="button" wire:click="generate" class="btn-primary">
                    <span wire:loading.remove wire:target="generate">Refresh Snapshot</span>
                    <span wire:loading wire:target="generate" class="flex items-center gap-2">
                        <div class="spinner w-4 h-4"></div> Memuat...
                    </span>
                </button>
                @can('laporan.export')
                @if($hasil)
                <button type="button" wire:click="exportPdf" class="btn-danger">PDF</button>
                <button type="button" wire:click="exportExcel" class="btn-success">Excel</button>
                @endif
                @endcan
                @if($hasil)
                <span class="text-xs text-gray-400">Snapshot: {{ $hasil['tanggal_snapshot'] }}</span>
                @endif
            </div>
        </div>
    </div>

    @if($hasil)
    <div wire:loading.remove wire:target="generate">
        <div class="grid grid-cols-2 md:grid-cols-3 gap-4 mb-5">
            <div class="card p-4 text-center">
                <p class="text-2xl font-bold text-blue-600">{{ number_format($hasil['total_jenis_barang']) }}</p>
                <p class="text-xs text-gray-500 mt-1">Jenis Barang Aktif</p>
            </div>
            <div class="card p-4 text-center col-span-2">
                <p class="text-2xl font-bold text-emerald-600">Rp {{ number_format($hasil['total_nilai'], 0, ',', '.') }}</p>
                <p class="text-xs text-gray-500 mt-1">Total Nilai Inventory</p>
            </div>
        </div>

        <div class="card mb-5">
            <div class="card-header"><h3 class="text-sm font-semibold text-gray-700">Per Jenis Barang</h3></div>
            <div class="card-body p-0">
                <table class="table">
                    <thead><tr><th>Jenis</th><th class="text-right">Item</th><th class="text-right">Total Stok</th><th class="text-right">Total Nilai</th></tr></thead>
                    <tbody>
                        @foreach($hasil['per_jenis'] as $jenis => $data)
                        <tr>
                            <td class="capitalize font-medium">{{ $jenis ?: 'Lainnya' }}</td>
                            <td class="text-right">{{ number_format($data['jumlah_item']) }}</td>
                            <td class="text-right">{{ number_format($data['total_stok']) }}</td>
                            <td class="text-right font-medium">Rp {{ number_format($data['total_nilai'], 0, ',', '.') }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h3 class="text-sm font-semibold text-gray-700">Detail Barang (Diurutkan Nilai Tertinggi)</h3></div>
            <div class="card-body p-0">
                <table class="table">
                    <thead>
                        <tr><th>Kode</th><th>Nama</th><th>Jenis</th><th class="text-right">Stok</th><th>Satuan</th><th class="text-right">Harga Pokok</th><th class="text-right">Nilai</th></tr>
                    </thead>
                    <tbody>
                        @foreach($hasil['detail'] as $row)
                        <tr>
                            <td class="text-sm font-mono">{{ $row['kode'] }}</td>
                            <td class="text-sm font-medium">{{ $row['nama'] }}</td>
                            <td class="text-sm capitalize text-gray-500">{{ $row['jenis'] }}</td>
                            <td class="text-right text-sm">{{ number_format($row['stok']) }}</td>
                            <td class="text-sm text-gray-500">{{ $row['satuan'] }}</td>
                            <td class="text-right text-sm">Rp {{ number_format($row['harga_pokok'], 0, ',', '.') }}</td>
                            <td class="text-right font-medium text-sm">Rp {{ number_format($row['nilai'], 0, ',', '.') }}</td>
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
                <p class="empty-state-text">Klik "Refresh Snapshot" untuk melihat nilai inventory terkini</p>
            </div>
        </div>
    </div>
    @endif
</div>
