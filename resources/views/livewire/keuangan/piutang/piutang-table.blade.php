<div>
    <div class="page-header">
        <div>
            <h1 class="page-title">Piutang Asuransi</h1>
            <p class="page-subtitle">Daftar piutang dari billing dengan penjamin asuransi</p>
        </div>
        @can('piutang.tagih')
        <a href="{{ route('keuangan.penagihan.create') }}" class="btn-primary">+ Buat Penagihan</a>
        @endcan
    </div>

    {{-- Summary Cards --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <div class="card">
            <div class="card-body">
                <p class="text-xs text-gray-500 uppercase tracking-wide">Total Outstanding</p>
                <p class="text-2xl font-bold text-gray-900 mt-1">
                    Rp {{ number_format($summary['total_outstanding'], 0, ',', '.') }}
                </p>
            </div>
        </div>
        <div class="card">
            <div class="card-body">
                <p class="text-xs text-gray-500 uppercase tracking-wide">Belum Ditagih</p>
                <p class="text-2xl font-bold text-amber-600 mt-1">
                    Rp {{ number_format($summary['total_tertagih'], 0, ',', '.') }}
                </p>
            </div>
        </div>
        <div class="card">
            <div class="card-body">
                <p class="text-xs text-gray-500 uppercase tracking-wide">Jatuh Tempo</p>
                <p class="text-2xl font-bold text-red-600 mt-1">
                    {{ $summary['jatuh_tempo'] }} piutang
                </p>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header flex flex-wrap gap-3">
            <input type="text" wire:model.live.debounce.300ms="search"
                class="form-input w-56" placeholder="Cari no. piutang / pasien..." />
            <select wire:model.live="filterAsuransi" class="form-input w-48">
                <option value="0">Semua Asuransi</option>
                @foreach($opsiAsuransi as $a)
                <option value="{{ $a->id }}">{{ $a->nama }}</option>
                @endforeach
            </select>
            <select wire:model.live="filterStatus" class="form-input w-44">
                <option value="">Semua Status</option>
                <option value="tertagih">Tertagih</option>
                <option value="diajukan">Diajukan</option>
                <option value="dibayar_sebagian">Dibayar Sebagian</option>
                <option value="lunas">Lunas</option>
                <option value="ditolak">Ditolak</option>
            </select>
        </div>
        <div class="card-body p-0">
            <table class="table">
                <thead>
                    <tr>
                        <th>No. Piutang</th>
                        <th>Pasien</th>
                        <th>Asuransi</th>
                        <th class="text-right">Piutang</th>
                        <th class="text-right">Sisa</th>
                        <th>Umur</th>
                        <th>Jatuh Tempo</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($piutang as $p)
                    <tr class="{{ $p->is_jatuh_tempo ? 'bg-red-50' : '' }}">
                        <td class="font-mono text-xs">{{ $p->nomor_piutang }}</td>
                        <td>
                            <p class="font-medium text-gray-900 text-sm">{{ $p->pasien->nama }}</p>
                            <p class="text-xs text-gray-400">{{ $p->pasien->no_rekam_medis ?? '-' }}</p>
                        </td>
                        <td class="text-sm">{{ $p->asuransi->nama }}</td>
                        <td class="text-right font-medium text-sm">
                            Rp {{ number_format($p->jumlah_piutang, 0, ',', '.') }}
                        </td>
                        <td class="text-right font-medium text-sm {{ $p->sisa_piutang > 0 ? 'text-amber-600' : 'text-gray-400' }}">
                            Rp {{ number_format($p->sisa_piutang, 0, ',', '.') }}
                        </td>
                        <td class="text-sm text-gray-500">{{ $p->umur_piutang }} hr</td>
                        <td class="text-sm">
                            @if($p->tanggal_jatuh_tempo)
                                <span class="{{ $p->is_jatuh_tempo ? 'text-red-600 font-semibold' : 'text-gray-600' }}">
                                    {{ $p->tanggal_jatuh_tempo->format('d/m/Y') }}
                                </span>
                            @else
                                <span class="text-gray-300">-</span>
                            @endif
                        </td>
                        <td>
                            @php
                                $badgeClass = match($p->status) {
                                    'tertagih'         => 'badge-warning',
                                    'diajukan'         => 'badge-primary',
                                    'dibayar_sebagian' => 'badge-info',
                                    'lunas'            => 'badge-success',
                                    'ditolak'          => 'badge-danger',
                                    default            => 'badge-gray',
                                };
                            @endphp
                            <span class="badge {{ $badgeClass }}">{{ $p->status_label }}</span>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="text-center text-gray-400 py-8">Belum ada data piutang.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($piutang->hasPages())
        <div class="card-footer">
            {{ $piutang->links() }}
        </div>
        @endif
    </div>
</div>
