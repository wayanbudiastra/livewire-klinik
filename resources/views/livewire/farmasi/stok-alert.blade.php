<div class="space-y-6">

    {{-- Reorder Alert --}}
    <div class="card">
        <div class="card-header">
            <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-100 flex items-center gap-2">
                🔔 Stok di Bawah Minimum (Reorder Point)
            </h3>
            <span class="badge-warning">{{ $this->reorderList->count() }} item</span>
        </div>
        <div class="card-body p-0">
            @if ($this->reorderList->isEmpty())
            <div class="empty-state py-8">
                <p class="empty-state-text text-emerald-500">✓ Semua stok dalam kondisi aman</p>
            </div>
            @else
            <table class="table">
                <thead>
                    <tr>
                        <th>Obat / Alkes</th>
                        <th>Lokasi Gudang</th>
                        <th>Stok</th>
                        <th>Min</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($this->reorderList as $obat)
                        @foreach ($obat->stokGudang->filter(fn($s) => $s->stok <= $s->stok_min) as $sg)
                        <tr wire:key="sg-{{ $sg->id }}">
                            <td>
                                <p class="font-medium text-gray-900 dark:text-gray-100">{{ $obat->nama }}</p>
                                <p class="text-xs font-mono text-gray-400">{{ $obat->kode }}</p>
                            </td>
                            <td class="text-sm text-gray-600 dark:text-gray-400">
                                {{ $sg->lokasiGudang->nama }}
                            </td>
                            <td class="font-bold {{ $sg->stok <= 0 ? 'text-red-600' : 'text-amber-600' }}">
                                {{ $sg->stok }}
                            </td>
                            <td class="text-sm text-gray-500">{{ $sg->stok_min }}</td>
                            <td><x-stok-status :status="$sg->status_stok" /></td>
                        </tr>
                        @endforeach
                    @endforeach
                </tbody>
            </table>
            @endif
        </div>
    </div>

    {{-- Filter Expired --}}
    <div class="flex items-center gap-3">
        <label class="text-sm text-gray-600 dark:text-gray-400 font-medium">Tampilkan expired dalam:</label>
        @foreach ([30 => '30 hari', 60 => '60 hari', 90 => '90 hari', 180 => '6 bulan', 365 => '1 tahun'] as $val => $lbl)
        <button wire:click="$set('hariExpired', {{ $val }})"
                @class([
                    'px-3 py-1 rounded-lg text-xs font-medium border transition-colors',
                    'border-[#0a3d62] bg-[#0a3d62] text-white' => $hariExpired === $val,
                    'border-gray-200 text-gray-600 dark:border-gray-600 dark:text-gray-400' => $hariExpired !== $val,
                ])>{{ $lbl }}</button>
        @endforeach
    </div>

    {{-- Akan Expired --}}
    <div class="card">
        <div class="card-header">
            <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-100 flex items-center gap-2">
                ⚠️ Akan Expired dalam {{ $hariExpired }} Hari
            </h3>
            <span class="badge-warning">{{ $this->akanExpiredList->count() }} batch</span>
        </div>
        <div class="card-body p-0">
            @if ($this->akanExpiredList->isEmpty())
            <div class="empty-state py-8">
                <p class="empty-state-text text-emerald-500">✓ Tidak ada batch yang akan expired</p>
            </div>
            @else
            <table class="table">
                <thead>
                    <tr>
                        <th>Obat / Alkes</th>
                        <th>No. Batch</th>
                        <th>Tgl. Expired</th>
                        <th>Sisa Hari</th>
                        <th>Stok Batch</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($this->akanExpiredList as $batch)
                    <tr wire:key="batch-{{ $batch->id }}">
                        <td>
                            <p class="font-medium text-gray-900 dark:text-gray-100">{{ $batch->obat->nama }}</p>
                            <p class="text-xs font-mono text-gray-400">{{ $batch->obat->kode }}</p>
                        </td>
                        <td class="font-mono text-sm text-gray-600 dark:text-gray-400">{{ $batch->nomor_batch }}</td>
                        <td class="text-sm">{{ $batch->tanggal_expired->format('d/m/Y') }}</td>
                        <td>
                            <span @class([
                                'font-bold text-sm',
                                'text-red-600'   => $batch->sisa_hari <= 30,
                                'text-amber-600' => $batch->sisa_hari <= 90 && $batch->sisa_hari > 30,
                                'text-gray-700 dark:text-gray-300' => $batch->sisa_hari > 90,
                            ])>{{ $batch->sisa_hari }} hari</span>
                        </td>
                        <td class="text-sm text-gray-600 dark:text-gray-400">
                            {{ $batch->stok_batch }} {{ $batch->obat->satuan }}
                        </td>
                        <td>
                            @php
                            $statusMap = [
                                'kritis'  => 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300',
                                'warning' => 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300',
                                'aman'    => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300',
                            ];
                            $statusClass = $statusMap[$batch->status_expired] ?? $statusMap['aman'];
                            @endphp
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $statusClass }}">
                                {{ ucfirst($batch->status_expired) }}
                            </span>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            @endif
        </div>
    </div>

    {{-- Sudah Expired --}}
    @if ($this->sudahExpiredList->isNotEmpty())
    <div class="card border-red-200 dark:border-red-800">
        <div class="card-header">
            <h3 class="text-sm font-semibold text-red-700 dark:text-red-400 flex items-center gap-2">
                🚫 Sudah Expired (Perlu Tindakan)
            </h3>
            <span class="badge-danger">{{ $this->sudahExpiredList->count() }} batch</span>
        </div>
        <div class="card-body p-0">
            <table class="table">
                <thead>
                    <tr>
                        <th>Obat / Alkes</th>
                        <th>No. Batch</th>
                        <th>Tgl. Expired</th>
                        <th>Stok Batch</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($this->sudahExpiredList as $batch)
                    <tr wire:key="exp-{{ $batch->id }}" class="bg-red-50/30 dark:bg-red-900/10">
                        <td>
                            <p class="font-medium text-gray-900 dark:text-gray-100">{{ $batch->obat->nama }}</p>
                            <p class="text-xs font-mono text-gray-400">{{ $batch->obat->kode }}</p>
                        </td>
                        <td class="font-mono text-sm text-gray-600 dark:text-gray-400">{{ $batch->nomor_batch }}</td>
                        <td class="text-sm text-red-600 font-medium">{{ $batch->tanggal_expired->format('d/m/Y') }}</td>
                        <td class="text-sm text-red-600 font-medium">
                            {{ $batch->stok_batch }} {{ $batch->obat->satuan }}
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

</div>
