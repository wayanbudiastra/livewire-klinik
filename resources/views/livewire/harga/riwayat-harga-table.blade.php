<div>
    <div class="page-header">
        <div>
            <h2 class="page-title">Riwayat Perubahan Harga</h2>
            <p class="page-subtitle">Seluruh perubahan harga dari proposal yang sudah diterapkan</p>
        </div>
        @can('harga.proposal')
        <a href="{{ route('harga.proposal.index') }}" class="btn-secondary">← Proposal</a>
        @endcan
    </div>

    <div class="card">
        <div class="card-header flex flex-wrap gap-3">
            <input type="text" wire:model.live.debounce.300ms="search"
                   class="form-input w-52" placeholder="Cari nama item..." />

            <select wire:model.live="filterTipe" class="form-input w-36">
                <option value="">Semua Tipe</option>
                <option value="tindakan">Tindakan</option>
                <option value="barang">Barang</option>
            </select>

            @if($kategoriList->isNotEmpty())
            <select wire:model.live="filterKategori" class="form-input w-44">
                <option value="">Semua Kategori</option>
                @foreach($kategoriList as $kat)
                <option value="{{ $kat }}">{{ $kat }}</option>
                @endforeach
            </select>
            @endif

            <select wire:model.live="filterProposal" class="form-input w-56">
                <option value="">Semua Proposal</option>
                @foreach($proposalList as $p)
                <option value="{{ $p->id }}">{{ $p->judul }} ({{ $p->tahun }})</option>
                @endforeach
            </select>

            <select wire:model.live="filterTahun" class="form-input w-28">
                <option value="">Semua Tahun</option>
                @foreach($tahunList as $t)
                <option value="{{ $t }}">{{ $t }}</option>
                @endforeach
            </select>
        </div>

        <div class="card-body p-0">
            <table class="table">
                <thead>
                    <tr>
                        <th>Tgl Efektif</th>
                        <th>Nama Item</th>
                        <th>Tipe / Kategori</th>
                        <th class="text-right">Harga Lama</th>
                        <th class="text-right">Harga Baru</th>
                        <th class="text-right">Selisih</th>
                        <th class="text-center">%</th>
                        <th>Proposal</th>
                        <th>Diterapkan Oleh</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($items as $item)
                    <tr wire:key="riwayat-{{ $item->id }}">
                        <td class="text-sm text-gray-600 dark:text-gray-400 whitespace-nowrap">
                            {{ $item->proposal->tanggal_efektif->format('d/m/Y') }}
                        </td>
                        <td class="font-medium text-gray-900 dark:text-gray-100 text-sm">
                            {{ $item->item_nama }}
                            @if($item->is_dikoreksi_manual)
                            <span class="badge badge-warning ml-1 text-xs">Koreksi</span>
                            @endif
                        </td>
                        <td class="text-xs">
                            <span class="badge {{ $item->item_type === 'tindakan' ? 'badge-primary' : 'badge-gray' }}">
                                {{ $item->item_type_label }}
                            </span>
                            <span class="block mt-0.5 text-gray-400">{{ $item->item_kategori ?: '-' }}</span>
                        </td>
                        <td class="text-right text-sm text-gray-500">
                            Rp {{ number_format($item->harga_lama, 0, ',', '.') }}
                        </td>
                        <td class="text-right text-sm font-medium text-gray-900 dark:text-white">
                            Rp {{ number_format($item->harga_baru, 0, ',', '.') }}
                        </td>
                        <td class="text-right text-sm text-emerald-600">
                            +Rp {{ number_format($item->selisih, 0, ',', '.') }}
                        </td>
                        <td class="text-center text-sm text-emerald-600">
                            +{{ number_format($item->persen_aktual, 1) }}%
                        </td>
                        <td class="text-sm text-gray-600 dark:text-gray-400">
                            <a href="{{ route('harga.proposal.show', $item->proposal_harga_id) }}"
                               class="text-primary-600 hover:underline text-xs">
                                {{ Str::limit($item->proposal->judul, 35) }}
                            </a>
                        </td>
                        <td class="text-sm text-gray-500">
                            {{ $item->proposal->diterapkanOleh->nama ?? '-' }}
                        </td>
                        <td>
                            <button type="button"
                                    wire:click="openTimeline('{{ $item->item_type }}', {{ $item->item_id }}, '{{ addslashes($item->item_nama) }}')"
                                    class="btn-secondary btn-sm whitespace-nowrap">
                                Timeline
                            </button>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="10" class="text-center text-gray-400 py-8">
                            Belum ada riwayat perubahan harga.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($items->hasPages())
        <div class="card-footer">{{ $items->links() }}</div>
        @endif
    </div>

    {{-- Timeline Modal --}}
    @if($showTimeline)
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/50" wire:click="$set('showTimeline', false)"></div>
        <div class="relative z-10 w-full max-w-lg rounded-2xl bg-white dark:bg-gray-800 shadow-2xl p-6 max-h-[80vh] overflow-y-auto">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-base font-semibold text-gray-900 dark:text-white">
                    Timeline: {{ $timelineNama }}
                </h3>
                <button type="button" wire:click="$set('showTimeline', false)"
                        class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">✕</button>
            </div>

            @if(empty($timeline))
            <p class="text-sm text-gray-400 text-center py-4">Tidak ada riwayat perubahan.</p>
            @else
            <div class="space-y-3">
                @foreach($timeline as $t)
                <div class="flex gap-3">
                    <div class="flex-shrink-0 w-2 h-2 rounded-full bg-emerald-500 mt-1.5"></div>
                    <div class="flex-1">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-medium text-gray-600 dark:text-gray-400">
                                {{ $t->proposal->tanggal_efektif->format('d/m/Y') }}
                            </span>
                            <span class="text-xs text-emerald-600">+{{ number_format($t->persen_aktual, 1) }}%</span>
                        </div>
                        <p class="text-sm text-gray-900 dark:text-white">
                            Rp {{ number_format($t->harga_lama, 0, ',', '.') }}
                            → <strong>Rp {{ number_format($t->harga_baru, 0, ',', '.') }}</strong>
                            <span class="text-emerald-600">(+Rp {{ number_format($t->selisih, 0, ',', '.') }})</span>
                        </p>
                        <p class="text-xs text-gray-400 mt-0.5">
                            Proposal: {{ $t->proposal->judul }}
                        </p>
                    </div>
                </div>
                @endforeach
            </div>
            @endif
        </div>
    </div>
    @endif
</div>
