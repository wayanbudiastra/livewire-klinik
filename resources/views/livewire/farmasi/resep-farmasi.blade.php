<div>
    {{-- ═══ FILTER BAR ═══ --}}
    <div class="flex flex-col sm:flex-row gap-3 mb-6">
        <div class="flex-1 relative">
            <input wire:model.live.debounce.300ms="search"
                   type="text"
                   placeholder="Cari nama pasien atau nomor RM..."
                   class="form-input pl-9 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200"/>
            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
            </svg>
        </div>
        <div class="flex gap-1 rounded-xl border border-gray-200 dark:border-gray-600 overflow-hidden p-1 bg-gray-50 dark:bg-gray-800">
            @foreach(['menunggu' => 'Menunggu', 'dikonfirmasi' => 'Dikonfirmasi'] as $val => $label)
            <button type="button" wire:click="$set('statusFilter', '{{ $val }}')"
                    @class([
                        'px-4 py-1.5 text-sm font-medium rounded-lg transition-colors',
                        'bg-white dark:bg-gray-700 text-[#0a3d62] dark:text-blue-400 shadow-sm' => $statusFilter === $val,
                        'text-gray-500 dark:text-gray-400 hover:text-gray-700' => $statusFilter !== $val,
                    ])>
                {{ $label }}
            </button>
            @endforeach
        </div>
    </div>

    {{-- ═══ LIST RESEP ═══ --}}
    @forelse($this->resepList as $resep)
    @php
        $pasien  = $resep->kunjungan?->pasien;
        $dokter  = $resep->kunjungan?->dokter?->user?->nama ?? '—';
        $hasItem = $resep->itemResep->count() > 0 || $resep->racikan->count() > 0;
    @endphp
    <div class="card mb-4" wire:key="resep-{{ $resep->id }}">
        {{-- Header Resep --}}
        <div class="card-header flex flex-col sm:flex-row sm:items-center gap-3">
            <div class="flex-1 min-w-0">
                <div class="flex items-center gap-3 flex-wrap">
                    <h3 class="text-sm font-bold text-gray-900 dark:text-white">{{ $pasien?->nama }}</h3>
                    <span class="badge badge-gray text-xs">{{ $pasien?->nomor_rm }}</span>
                    @if($resep->is_locked)
                    <span class="badge badge-success text-xs">Dikonfirmasi</span>
                    @else
                    <span class="badge badge-warning text-xs">Menunggu</span>
                    @endif
                </div>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                    Dokter: {{ $dokter }} ·
                    Tanggal: {{ $resep->created_at->format('d/m/Y H:i') }}
                    @if($resep->is_locked)
                    · Dikunci: {{ $resep->locked_at?->format('d/m/Y H:i') }} oleh {{ $resep->locker?->nama }}
                    @endif
                </p>
            </div>
            @if(!$resep->is_locked && $hasItem)
            <x-confirm-button
                :action="'konfirmasi(' . $resep->id . ')'"
                title="Konfirmasi Resep?"
                text="Stok obat akan dipotong dan resep dikunci. Tindakan ini tidak dapat dibatalkan."
                confirm="Ya, Konfirmasi"
                type="success"
                class="btn-success btn-sm flex-shrink-0">
                <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
                Konfirmasi & Potong Stok
            </x-confirm-button>
            @endif
        </div>

        {{-- Obat Jadi --}}
        @if($resep->itemResep->count() > 0)
        <div class="px-4 py-3 border-t border-gray-100 dark:border-gray-700">
            <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-2">Obat Jadi</p>
            <div class="space-y-2">
                @foreach($resep->itemResep as $item)
                <div wire:key="fi-{{ $item->id }}" class="flex flex-col sm:flex-row gap-2 sm:items-center">
                    @if($editingItemId === $item->id)
                    {{-- Edit mode --}}
                    <div class="flex-1 flex flex-wrap gap-2 items-center">
                        <span class="text-sm font-medium text-gray-900 dark:text-gray-100 min-w-0">{{ $item->obat?->nama }}</span>
                        <input wire:model="editJumlah" type="number" min="1"
                               class="form-input w-20 text-xs py-1 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200"/>
                        <span class="text-xs text-gray-400">{{ $item->obat?->satuan }}</span>
                        <input wire:model="editSigna" type="text" placeholder="Signa..."
                               class="form-input text-xs py-1 w-48 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200"/>
                    </div>
                    <div class="flex gap-1">
                        <button wire:click="saveEditItem" class="btn-success btn-xs">Simpan</button>
                        <button wire:click="cancelEditItem" class="btn-secondary btn-xs">Batal</button>
                    </div>
                    @else
                    <div class="flex-1">
                        <span class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $item->obat?->nama }}</span>
                        <span class="text-xs text-gray-400 ml-2">{{ $item->jumlah }} {{ $item->obat?->satuan }}</span>
                        @if($item->aturan_pakai)
                        <span class="text-xs text-blue-600 dark:text-blue-400 ml-2">· {{ $item->aturan_pakai }}</span>
                        @endif
                    </div>
                    <span class="text-xs font-mono text-gray-500 dark:text-gray-400">
                        Rp {{ number_format(($item->obat?->harga ?? 0) * $item->jumlah, 0, ',', '.') }}
                    </span>
                    @if(!$resep->is_locked)
                    <div class="flex gap-1">
                        <button wire:click="openEditItem({{ $item->id }})"
                                class="btn-secondary btn-xs">Edit</button>
                        <x-confirm-button
                            :action="'hapusItem(' . $item->id . ')'"
                            title="Hapus Item?"
                            :text="'Hapus ' . ($item->obat?->nama ?? 'item') . ' dari resep?'"
                            confirm="Ya, Hapus"
                            type="danger"
                            class="btn-danger btn-xs">
                            Hapus
                        </x-confirm-button>
                    </div>
                    @endif
                    @endif
                </div>
                @endforeach
            </div>
        </div>
        @endif

        {{-- Racikan --}}
        @foreach($resep->racikan as $racikan)
        <div wire:key="fr-{{ $racikan->id }}" class="px-4 py-3 border-t border-gray-100 dark:border-gray-700">
            <div class="flex flex-col sm:flex-row sm:items-start gap-2 mb-2">
                @if($editingRacikanId === $racikan->id)
                <div class="flex-1 flex flex-wrap gap-2 items-center">
                    <span class="text-xs font-semibold text-purple-600 dark:text-purple-400 uppercase">Racikan:</span>
                    <span class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $racikan->nama_racikan }}</span>
                    <span class="badge badge-purple text-xs capitalize">{{ $racikan->metode }}</span>
                    <div class="flex items-center gap-1">
                        <input wire:model="editJumlahSediaan" type="number" min="1"
                               class="form-input w-20 text-xs py-1 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200"/>
                        <span class="text-xs text-gray-400">sediaan</span>
                    </div>
                    <input wire:model="editAturanPakai" type="text" placeholder="Aturan pakai..."
                           class="form-input text-xs py-1 w-48 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200"/>
                </div>
                <div class="flex gap-1">
                    <button wire:click="saveEditRacikan" class="btn-success btn-xs">Simpan</button>
                    <button wire:click="cancelEditRacikan" class="btn-secondary btn-xs">Batal</button>
                </div>
                @else
                <div class="flex-1">
                    <span class="text-xs font-semibold text-purple-600 dark:text-purple-400 uppercase">Racikan:</span>
                    <span class="text-sm font-medium text-gray-900 dark:text-gray-100 ml-1">{{ $racikan->nama_racikan }}</span>
                    <span class="badge badge-purple text-xs capitalize ml-2">{{ $racikan->metode }}</span>
                    <span class="text-xs text-gray-500 dark:text-gray-400 ml-2">
                        {{ $racikan->jumlah_sediaan }} sediaan
                        @if($racikan->aturan_pakai) · {{ $racikan->aturan_pakai }} @endif
                    </span>
                </div>
                @if(!$resep->is_locked)
                <div class="flex gap-1">
                    <button wire:click="openEditRacikan({{ $racikan->id }})"
                            class="btn-secondary btn-xs">Edit</button>
                    <x-confirm-button
                        :action="'hapusRacikan(' . $racikan->id . ')'"
                        title="Hapus Racikan?"
                        :text="'Hapus racikan ' . $racikan->nama_racikan . '?'"
                        confirm="Ya, Hapus"
                        type="danger"
                        class="btn-danger btn-xs">
                        Hapus
                    </x-confirm-button>
                </div>
                @endif
                @endif
            </div>
            <div class="ml-4 space-y-1">
                @foreach($racikan->bahanRacikan as $bahan)
                <div class="text-xs text-gray-600 dark:text-gray-400 flex gap-2">
                    <span class="text-gray-400">—</span>
                    <span>{{ $bahan->obat?->nama }}</span>
                    <span class="font-mono">{{ $bahan->jumlah }} {{ $bahan->satuan ?? $bahan->obat?->satuan }}</span>
                </div>
                @endforeach
            </div>
        </div>
        @endforeach

        @if($resep->itemResep->count() === 0 && $resep->racikan->count() === 0)
        <div class="px-4 py-4 border-t border-gray-100 dark:border-gray-700">
            <p class="text-sm text-gray-400 text-center">Resep kosong</p>
        </div>
        @endif
    </div>
    @empty
    <div class="card">
        <div class="card-body py-16">
            <div class="empty-state">
                <svg class="empty-state-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                          d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                <p class="empty-state-text">
                    @if($statusFilter === 'menunggu')
                    Tidak ada resep menunggu konfirmasi
                    @else
                    Tidak ada resep yang telah dikonfirmasi
                    @endif
                </p>
            </div>
        </div>
    </div>
    @endforelse

    <div class="mt-4">
        {{ $this->resepList->links() }}
    </div>
</div>
