<div>
    @if(session('success'))
    <div class="alert alert-success mb-4">{{ session('success') }}</div>
    @endif
    @if(session('error'))
    <div class="alert alert-danger mb-4">{{ session('error') }}</div>
    @endif

    <div class="page-header">
        <div>
            <h2 class="page-title">Proposal Penyesuaian Harga</h2>
            <p class="page-subtitle">Kelola kenaikan harga jasa pelayanan dan obat/alkes secara terstruktur</p>
        </div>
        @can('harga.proposal')
        <a href="{{ route('harga.proposal.create') }}" class="btn-primary">+ Buat Proposal Baru</a>
        @endcan
    </div>

    <div class="card">
        <div class="card-header flex flex-wrap gap-3">
            <input type="text" wire:model.live.debounce.300ms="search"
                   class="form-input w-56" placeholder="Cari judul proposal..." />

            <select wire:model.live="filterStatus" class="form-input w-44">
                <option value="">Semua Status</option>
                <option value="draft">Draft</option>
                <option value="menunggu_persetujuan">Menunggu Persetujuan</option>
                <option value="disetujui">Disetujui</option>
                <option value="efektif">Efektif</option>
                <option value="dibatalkan">Dibatalkan</option>
            </select>

            <select wire:model.live="filterTahun" class="form-input w-28">
                <option value="">Semua Tahun</option>
                @foreach($tahunList as $t)
                <option value="{{ $t }}">{{ $t }}</option>
                @endforeach
                @if(!$tahunList->contains(now()->year))
                <option value="{{ now()->year }}">{{ now()->year }}</option>
                @endif
            </select>
        </div>

        <div class="card-body p-0">
            <table class="table">
                <thead>
                    <tr>
                        <th>Judul Proposal</th>
                        <th class="text-center">Tahun</th>
                        <th>Cakupan</th>
                        <th>Tgl Efektif</th>
                        <th class="text-center">Item</th>
                        <th>Status</th>
                        <th>Dibuat Oleh</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($proposals as $p)
                    <tr>
                        <td class="font-medium text-gray-900 dark:text-gray-100 max-w-xs">
                            <div>{{ $p->judul }}</div>
                            @if($p->alasan_tolak)
                            <p class="text-xs text-red-500 mt-0.5">Dikembalikan: {{ Str::limit($p->alasan_tolak, 60) }}</p>
                            @endif
                        </td>
                        <td class="text-center text-sm">{{ $p->tahun }}</td>
                        <td class="text-sm">
                            {{ match($p->cakupan) {
                                'semua'    => 'Semua',
                                'tindakan' => 'Tindakan',
                                'barang'   => 'Barang',
                                default    => $p->cakupan,
                            } }}
                        </td>
                        <td class="text-sm text-gray-600 dark:text-gray-400">
                            {{ $p->tanggal_efektif->format('d/m/Y') }}
                            @if($p->status === 'disetujui' && now()->lt($p->tanggal_efektif))
                            <span class="block text-xs text-amber-500">Belum bisa diterapkan</span>
                            @endif
                        </td>
                        <td class="text-center text-sm">{{ $p->items_count ?? $p->items()->count() }}</td>
                        <td>
                            <span class="badge {{ $p->status_badge }}">{{ $p->status_label }}</span>
                        </td>
                        <td class="text-sm text-gray-500">{{ $p->dibuatOleh->nama ?? '-' }}</td>
                        <td>
                            <div class="flex gap-1">
                                <a href="{{ route('harga.proposal.show', $p->id) }}"
                                   class="btn-secondary btn-sm">Detail</a>

                                @if(!in_array($p->status, ['efektif', 'dibatalkan']))
                                @can('harga.proposal')
                                <x-confirm-button :action="'batalkan(' . $p->id . ')'"
                                    title="Batalkan Proposal?"
                                    text="Proposal ini akan dibatalkan dan tidak bisa dipulihkan."
                                    icon="warning" type="danger" confirm="Ya, Batalkan"
                                    class="btn-danger btn-sm">
                                    Batalkan
                                </x-confirm-button>
                                @endcan
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="text-center text-gray-400 py-8">
                            Belum ada proposal harga.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($proposals->hasPages())
        <div class="card-footer">{{ $proposals->links() }}</div>
        @endif
    </div>
</div>
