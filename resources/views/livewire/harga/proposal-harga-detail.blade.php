<div class="space-y-5">

    {{-- Header Info --}}
    <div class="card">
        <div class="card-header flex flex-wrap items-start gap-4">
            <div class="flex-1 min-w-0">
                <div class="flex items-center gap-3 flex-wrap">
                    <h2 class="text-base font-semibold text-gray-900 dark:text-white truncate">{{ $proposal->judul }}</h2>
                    <span class="badge {{ $proposal->status_badge }}">{{ $proposal->status_label }}</span>
                </div>
                <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">
                    Cakupan: <strong>{{ match($proposal->cakupan) { 'semua'=>'Semua','tindakan'=>'Tindakan','barang'=>'Barang', default=>$proposal->cakupan } }}</strong>
                    &nbsp;·&nbsp; Efektif: <strong>{{ $proposal->tanggal_efektif->format('d/m/Y') }}</strong>
                    &nbsp;·&nbsp; BPJS ikut: <strong>{{ $proposal->ikut_bpjs ? 'Ya' : 'Tidak' }}</strong>
                    &nbsp;·&nbsp; Dibuat oleh: <strong>{{ $proposal->dibuatOleh->nama ?? '-' }}</strong>
                </p>
                @if($proposal->catatan)
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Catatan: {{ $proposal->catatan }}</p>
                @endif
                @if($proposal->alasan_tolak)
                <p class="text-xs text-red-500 mt-1">Alasan dikembalikan: {{ $proposal->alasan_tolak }}</p>
                @endif
                @if($proposal->status === 'efektif')
                <p class="text-xs text-emerald-600 dark:text-emerald-400 mt-1">
                    Diterapkan {{ $proposal->diterapkan_pada->format('d/m/Y H:i') }}
                    oleh {{ $proposal->diterapkanOleh->nama ?? '-' }}
                </p>
                @endif
            </div>

            {{-- Ringkasan counter --}}
            <div class="flex gap-4 text-center text-sm shrink-0">
                <div>
                    <div class="text-lg font-bold text-gray-800 dark:text-white">{{ $totalItem }}</div>
                    <div class="text-xs text-gray-400">Total Item</div>
                </div>
                <div>
                    <div class="text-lg font-bold text-emerald-600">{{ $totalNaik }}</div>
                    <div class="text-xs text-gray-400">Naik</div>
                </div>
                <div>
                    <div class="text-lg font-bold text-gray-400">{{ $totalSkip }}</div>
                    <div class="text-xs text-gray-400">Tidak Naik</div>
                </div>
            </div>
        </div>

        {{-- Workflow buttons --}}
        <div class="card-footer flex flex-wrap gap-2">
            <a href="{{ route('harga.proposal.index') }}" class="btn-secondary btn-sm">← Kembali</a>

            @if($proposal->status === 'draft')
                @can('harga.review')
                <x-confirm-button action="submitReview"
                    title="Submit untuk Persetujuan?"
                    text="Proposal akan dikirim ke approver dan tidak bisa diedit lagi sampai dikembalikan."
                    icon="info" type="primary" confirm="Ya, Submit"
                    class="btn-primary btn-sm">
                    Submit untuk Persetujuan
                </x-confirm-button>
                @endcan
                @can('harga.proposal')
                <x-confirm-button action="batalkan"
                    title="Batalkan Proposal?" text="Tindakan ini tidak bisa diurungkan."
                    icon="warning" type="danger" confirm="Ya, Batalkan"
                    class="btn-danger btn-sm">Batalkan</x-confirm-button>
                @endcan
            @endif

            @if($proposal->status === 'menunggu_persetujuan')
                @can('harga.setujui')
                <x-confirm-button action="setujui"
                    title="Setujui Proposal?"
                    text="Proposal ini akan disetujui. Harga belum berubah — diterapkan saat Anda klik Terapkan."
                    icon="info" type="primary" confirm="Ya, Setujui"
                    class="btn-primary btn-sm">Setujui</x-confirm-button>
                <button type="button" wire:click="openTolak"
                        class="btn-secondary btn-sm">Tolak / Kembalikan ke Draft</button>
                @endcan
                @can('harga.proposal')
                <x-confirm-button action="batalkan"
                    title="Batalkan Proposal?" text="Tindakan ini tidak bisa diurungkan."
                    icon="warning" type="danger" confirm="Ya, Batalkan"
                    class="btn-danger btn-sm">Batalkan</x-confirm-button>
                @endcan
            @endif

            @if($proposal->status === 'disetujui')
                @can('harga.terapkan')
                @if($proposal->bisa_diterapkan)
                <x-confirm-button action="terapkan"
                    title="Terapkan Harga Sekarang?"
                    text="Harga di seluruh master data akan diperbarui sesuai proposal ini. Tindakan ini TIDAK BISA DIURUNGKAN."
                    icon="warning" type="danger" confirm="Ya, Terapkan Harga"
                    class="btn-primary btn-sm">🚀 Terapkan Harga Sekarang</x-confirm-button>
                @else
                <button disabled class="btn-primary btn-sm opacity-50 cursor-not-allowed">
                    Terapkan (tersedia mulai {{ $proposal->tanggal_efektif->format('d/m/Y') }})
                </button>
                @endif
                <x-confirm-button action="batalkan"
                    title="Batalkan Proposal?" text="Tindakan ini tidak bisa diurungkan."
                    icon="warning" type="danger" confirm="Ya, Batalkan"
                    class="btn-danger btn-sm">Batalkan</x-confirm-button>
                @endcan
            @endif
        </div>
    </div>

    {{-- Modal tolak --}}
    @if($showTolakModal)
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/50" wire:click="$set('showTolakModal', false)"></div>
        <div class="relative z-10 w-full max-w-md rounded-2xl bg-white dark:bg-gray-800 shadow-2xl p-6">
            <h3 class="text-base font-semibold text-gray-900 dark:text-white mb-4">Tolak / Kembalikan ke Draft</h3>
            <div class="form-group">
                <label class="form-label dark:text-gray-300">Alasan Penolakan <span class="text-red-500">*</span></label>
                <textarea wire:model="alasanTolak" rows="3"
                          class="form-input dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200"
                          placeholder="Tulis alasan mengapa proposal dikembalikan ke draft..."></textarea>
                @error('alasanTolak') <p class="form-error">{{ $message }}</p> @enderror
            </div>
            <div class="flex justify-end gap-2 mt-4">
                <button type="button" wire:click="$set('showTolakModal', false)" class="btn-secondary">Batal</button>
                <button type="button" wire:click="tolak" class="btn-danger">Kembalikan ke Draft</button>
            </div>
        </div>
    </div>
    @endif

    {{-- Filter item --}}
    <div class="card">
        <div class="card-header flex flex-wrap gap-3">
            <input type="text" wire:model.live.debounce.300ms="search"
                   class="form-input w-52" placeholder="Cari nama item..." />

            <select wire:model.live="filterKategori" class="form-input w-44">
                <option value="">Semua Kategori</option>
                @foreach($kategoriList as $kat)
                <option value="{{ $kat }}">{{ $kat }}</option>
                @endforeach
            </select>

            <select wire:model.live="filterReview" class="form-input w-44">
                <option value="">Semua Status Review</option>
                <option value="naik">Naik Harga</option>
                <option value="skip">Tidak Naik</option>
                <option value="dikoreksi">Dikoreksi Manual</option>
            </select>
        </div>

        <div class="card-body p-0">
            <table class="table">
                <thead>
                    <tr>
                        <th>Nama Item</th>
                        <th>Tipe / Kategori</th>
                        <th class="text-right">Harga Lama</th>
                        <th class="text-center">% Naik</th>
                        <th class="text-right">Harga Baru</th>
                        <th class="text-right">Selisih</th>
                        @if($proposal->ikut_bpjs)
                        <th class="text-right">BPJS Lama</th>
                        <th class="text-right">BPJS Baru</th>
                        @endif
                        <th class="text-center">Tidak Naik</th>
                        @if(in_array($proposal->status, ['draft']))
                        <th>Aksi</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @forelse($items as $item)
                    <tr wire:key="item-{{ $item->id }}"
                        class="{{ $item->is_skip ? 'opacity-50' : '' }}">

                        @if($editingId === $item->id)
                        {{-- Edit row --}}
                        <td colspan="{{ $proposal->ikut_bpjs ? 9 : 7 }}" class="bg-amber-50 dark:bg-amber-900/20 p-3">
                            <div class="flex flex-wrap items-end gap-3">
                                <div>
                                    <p class="text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">{{ $item->item_nama }}</p>
                                    <p class="text-xs text-gray-400">Harga lama: Rp {{ number_format($item->harga_lama, 0, ',', '.') }}</p>
                                </div>
                                <div class="form-group mb-0">
                                    <label class="form-label text-xs dark:text-gray-300">Harga Baru <span class="text-red-500">*</span></label>
                                    <input wire:model="editHarga" type="number"
                                           class="form-input w-40 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200"
                                           min="0" step="100" />
                                    @error('editHarga') <p class="form-error text-xs">{{ $message }}</p> @enderror
                                </div>
                                @if($proposal->ikut_bpjs)
                                <div class="form-group mb-0">
                                    <label class="form-label text-xs dark:text-gray-300">Harga BPJS Baru</label>
                                    <input wire:model="editHargaBpjs" type="number"
                                           class="form-input w-40 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200"
                                           min="0" step="100" />
                                </div>
                                @endif
                                <div class="flex gap-2">
                                    <button type="button" wire:click="saveEdit" class="btn-primary btn-sm">Simpan</button>
                                    <button type="button" wire:click="cancelEdit" class="btn-secondary btn-sm">Batal</button>
                                </div>
                            </div>
                        </td>
                        @else
                        {{-- Normal row --}}
                        <td class="font-medium text-gray-900 dark:text-gray-100 text-sm">
                            {{ $item->item_nama }}
                            @if($item->is_dikoreksi_manual)
                            <span class="badge badge-warning ml-1 text-xs">Dikoreksi</span>
                            @endif
                        </td>
                        <td class="text-xs text-gray-500">
                            <span class="badge {{ $item->item_type === 'tindakan' ? 'badge-primary' : 'badge-gray' }}">
                                {{ $item->item_type_label }}
                            </span>
                            <span class="block mt-0.5">{{ $item->item_kategori ?: '-' }}</span>
                        </td>
                        <td class="text-right text-sm text-gray-600 dark:text-gray-400">
                            Rp {{ number_format($item->harga_lama, 0, ',', '.') }}
                        </td>
                        <td class="text-center text-sm">
                            @if($item->is_skip)
                            <span class="text-gray-400">—</span>
                            @else
                            <span class="{{ $item->persen_aktual > 0 ? 'text-emerald-600' : 'text-gray-400' }}">
                                +{{ number_format($item->persen_aktual, 1) }}%
                            </span>
                            @endif
                        </td>
                        <td class="text-right text-sm font-medium text-gray-900 dark:text-white">
                            Rp {{ number_format($item->harga_baru, 0, ',', '.') }}
                        </td>
                        <td class="text-right text-sm">
                            @if(!$item->is_skip && $item->selisih > 0)
                            <span class="text-emerald-600">+Rp {{ number_format($item->selisih, 0, ',', '.') }}</span>
                            @else
                            <span class="text-gray-400">—</span>
                            @endif
                        </td>
                        @if($proposal->ikut_bpjs)
                        <td class="text-right text-sm text-gray-500">
                            {{ $item->harga_bpjs_lama ? 'Rp ' . number_format($item->harga_bpjs_lama, 0, ',', '.') : '-' }}
                        </td>
                        <td class="text-right text-sm">
                            {{ $item->harga_bpjs_baru ? 'Rp ' . number_format($item->harga_bpjs_baru, 0, ',', '.') : '-' }}
                        </td>
                        @endif
                        <td class="text-center">
                            @if($proposal->status === 'draft')
                            <input type="checkbox"
                                   wire:click="toggleSkip({{ $item->id }})"
                                   {{ $item->is_skip ? 'checked' : '' }}
                                   class="h-4 w-4 rounded border-gray-300 text-gray-600 focus:ring-gray-500 cursor-pointer" />
                            @else
                            <span class="{{ $item->is_skip ? 'text-gray-400' : 'text-emerald-500' }}">
                                {{ $item->is_skip ? '✗' : '✓' }}
                            </span>
                            @endif
                        </td>
                        @if($proposal->status === 'draft')
                        <td>
                            @if(!$item->is_skip)
                            <button type="button" wire:click="startEdit({{ $item->id }})"
                                    class="btn-secondary btn-sm">Edit</button>
                            @endif
                        </td>
                        @endif
                        @endif
                    </tr>
                    @empty
                    <tr>
                        <td colspan="{{ $proposal->ikut_bpjs ? 9 : 7 }}" class="text-center text-gray-400 py-8">
                            Tidak ada item yang sesuai filter.
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
</div>
