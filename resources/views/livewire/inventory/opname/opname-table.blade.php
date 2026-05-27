<div>
    @if(session('success'))
    <div class="alert alert-success mb-4">{{ session('success') }}</div>
    @endif

    <div class="page-header">
        <div>
            <h1 class="page-title">Stok Opname</h1>
            <p class="page-subtitle">Rekonsiliasi stok fisik dengan data sistem</p>
        </div>
        @can('obat.edit')
        <a href="{{ route('inventory.opname.create') }}" class="btn-primary">+ Buat Opname Baru</a>
        @endcan
    </div>

    <div class="card">
        <div class="card-header flex flex-wrap gap-3">
            <select wire:model.live="filterStatus" class="form-input w-52">
                <option value="">Semua Status</option>
                <option value="draft">Draft</option>
                <option value="menunggu_verifikasi">Menunggu Verifikasi</option>
                <option value="selesai">Selesai</option>
                <option value="dibatalkan">Dibatalkan</option>
            </select>
            <input type="date" wire:model.live="filterDari"   class="form-input w-36" title="Dari tanggal" />
            <input type="date" wire:model.live="filterSampai" class="form-input w-36" title="Sampai tanggal" />
        </div>
        <div class="card-body p-0">
            <table class="table">
                <thead>
                    <tr>
                        <th>No. Opname</th>
                        <th>Tanggal</th>
                        <th>Periode</th>
                        <th>Dibuat Oleh</th>
                        <th class="text-center">Jml Item</th>
                        <th class="text-center">Sudah Diisi</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($opnameList as $opname)
                    @php $ringkasan = $opname->ringkasan; @endphp
                    <tr>
                        <td class="font-mono text-xs">{{ $opname->nomor_opname }}</td>
                        <td class="text-sm">{{ $opname->tanggal_opname->format('d/m/Y') }}</td>
                        <td class="text-sm text-gray-500">{{ $opname->keterangan_periode ?? '-' }}</td>
                        <td class="text-sm">{{ $opname->pembuat->name ?? '-' }}</td>
                        <td class="text-center text-sm">{{ $ringkasan['total_item'] }}</td>
                        <td class="text-center text-sm">
                            <span @class(['font-medium', 'text-emerald-600'=>$ringkasan['sudah_diisi']==$ringkasan['total_item'], 'text-amber-600'=>$ringkasan['sudah_diisi']<$ringkasan['total_item']])>
                                {{ $ringkasan['sudah_diisi'] }}/{{ $ringkasan['total_item'] }}
                            </span>
                        </td>
                        <td>
                            @php
                                $sc = match($opname->status) {
                                    'draft'               => 'badge-warning',
                                    'menunggu_verifikasi' => 'badge-primary',
                                    'selesai'             => 'badge-success',
                                    'dibatalkan'          => 'badge-gray',
                                    default               => 'badge-gray',
                                };
                            @endphp
                            <span class="badge {{ $sc }}">{{ $opname->status_label }}</span>
                        </td>
                        <td>
                            <a href="{{ route('inventory.opname.show', $opname->id) }}" class="btn-secondary btn-sm">
                                {{ $opname->status === 'draft' ? 'Input Stok' : 'Detail' }}
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="text-center text-gray-400 py-8">Belum ada data opname.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($opnameList->hasPages())
        <div class="card-footer">{{ $opnameList->links() }}</div>
        @endif
    </div>
</div>
