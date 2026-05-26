<div>
    @if(session('success'))
    <div class="alert alert-success mb-4">{{ session('success') }}</div>
    @endif

    <div class="page-header">
        <div>
            <h1 class="page-title">Master Asuransi</h1>
            <p class="page-subtitle">Kelola data penjamin dan asuransi</p>
        </div>
        @can('asuransi.master.manage')
        <a href="{{ route('pengaturan.asuransi.create') }}" class="btn-primary">+ Tambah Asuransi</a>
        @endcan
    </div>

    <div class="card">
        <div class="card-header">
            <input type="text" wire:model.live.debounce.300ms="search"
                class="form-input max-w-xs" placeholder="Cari nama / kode..." />
        </div>
        <div class="card-body p-0">
            <table class="table">
                <thead>
                    <tr>
                        <th>Kode</th>
                        <th>Nama</th>
                        <th>Tipe</th>
                        <th class="text-center">Cover (%)</th>
                        <th class="text-center">TOP (Hari)</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($asuransi as $a)
                    <tr>
                        <td class="font-mono text-xs text-gray-500">{{ $a->kode }}</td>
                        <td>
                            <p class="font-medium text-gray-900">{{ $a->nama }}</p>
                            @if($a->pic)
                            <p class="text-xs text-gray-400">PIC: {{ $a->pic }}</p>
                            @endif
                        </td>
                        <td>
                            <span class="badge badge-gray text-xs">{{ $a->tipe_label }}</span>
                        </td>
                        <td class="text-xs text-center">
                            <div class="space-y-0.5">
                                <div>Prosedur: <span class="font-medium">{{ $a->cover_prosedur }}%</span></div>
                                <div>Lab: <span class="font-medium">{{ $a->cover_laboratorium }}%</span></div>
                                <div>Radiologi: <span class="font-medium">{{ $a->cover_radiologi }}%</span></div>
                                <div>Peralatan: <span class="font-medium">{{ $a->cover_peralatan }}%</span></div>
                            </div>
                        </td>
                        <td class="text-center">{{ $a->term_pembayaran_hari }} hari</td>
                        <td>
                            @if($a->is_active)
                                <span class="badge badge-success">Aktif</span>
                            @else
                                <span class="badge badge-gray">Nonaktif</span>
                            @endif
                        </td>
                        <td>
                            <div class="flex gap-1">
                                @can('asuransi.master.manage')
                                <a href="{{ route('pengaturan.asuransi.edit', $a->id) }}" class="btn-warning btn-sm">Edit</a>
                                <button wire:click="toggleActive({{ $a->id }})"
                                    wire:loading.attr="disabled"
                                    wire:target="toggleActive({{ $a->id }})"
                                    class="btn-secondary btn-sm">
                                    {{ $a->is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                                </button>
                                @endcan
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="text-center text-gray-400 py-8">Belum ada data asuransi.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($asuransi->hasPages())
        <div class="card-footer">
            {{ $asuransi->links() }}
        </div>
        @endif
    </div>
</div>
