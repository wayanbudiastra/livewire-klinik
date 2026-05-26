<x-app-layout>
    <x-slot name="title">Penagihan Asuransi</x-slot>

    <div class="page-content">
        @if(session('success'))
        <div class="alert alert-success mb-4">{{ session('success') }}</div>
        @endif

        <div class="page-header">
            <div>
                <h1 class="page-title">Penagihan Asuransi</h1>
                <p class="page-subtitle">Daftar batch penagihan ke pihak asuransi</p>
            </div>
            @can('piutang.tagih')
            <a href="{{ route('keuangan.penagihan.create') }}" class="btn-primary">+ Buat Penagihan</a>
            @endcan
        </div>

        <div class="card">
            <div class="card-body p-0">
                <table class="table">
                    <thead>
                        <tr>
                            <th>No. Penagihan</th>
                            <th>Asuransi</th>
                            <th>Tanggal</th>
                            <th class="text-right">Total Tagihan</th>
                            <th class="text-right">Sudah Bayar</th>
                            <th class="text-right">Sisa</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse(\App\Models\PenagihanAsuransi::with('asuransi')->latest()->paginate(20) as $p)
                        <tr>
                            <td class="font-mono text-xs">{{ $p->nomor_penagihan }}</td>
                            <td class="text-sm font-medium">{{ $p->asuransi->nama }}</td>
                            <td class="text-sm">{{ $p->tanggal_penagihan->format('d/m/Y') }}</td>
                            <td class="text-right text-sm">Rp {{ number_format($p->total_tagihan, 0, ',', '.') }}</td>
                            <td class="text-right text-sm text-emerald-600">Rp {{ number_format($p->total_dibayar, 0, ',', '.') }}</td>
                            <td class="text-right text-sm font-medium {{ $p->sisa_tagihan > 0 ? 'text-amber-600' : 'text-gray-400' }}">
                                Rp {{ number_format($p->sisa_tagihan, 0, ',', '.') }}
                            </td>
                            <td>
                                @php
                                    $sc = match($p->status) {
                                        'diajukan'         => 'badge-primary',
                                        'dibayar_sebagian' => 'badge-warning',
                                        'lunas'            => 'badge-success',
                                        'ditutup'          => 'badge-gray',
                                        default            => 'badge-gray',
                                    };
                                @endphp
                                <span class="badge {{ $sc }}">{{ ucfirst(str_replace('_', ' ', $p->status)) }}</span>
                            </td>
                            <td>
                                <a href="{{ route('keuangan.penagihan.show', $p->id) }}" class="btn-secondary btn-sm">Detail</a>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="8" class="text-center text-gray-400 py-8">Belum ada penagihan.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
