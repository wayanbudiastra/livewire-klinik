<div>
    <span
        x-on:open-tindakan-create.window="$wire.openCreate()"
        x-on:open-tindakan-edit.window="$wire.openEdit($event.detail.id)"
    ></span>

    @if ($showModal)
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/50" wire:click="$set('showModal', false)"></div>
        <div class="relative z-10 w-full max-w-2xl rounded-2xl bg-white shadow-2xl dark:bg-gray-800 dark:border dark:border-gray-700 animate-fade-in">

            <div class="modal-header">
                <h3 class="modal-title dark:text-white">
                    {{ $isEdit ? 'Edit Tindakan' : 'Tambah Tindakan Baru' }}
                </h3>
                <button wire:click="$set('showModal', false)" class="text-gray-400 hover:text-gray-600">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <div class="modal-body">
                <form wire:submit="save" class="space-y-4">

                    <div class="grid grid-cols-2 gap-4">
                        <div class="form-group">
                            <label class="form-label dark:text-gray-300">Kode <span class="text-red-500">*</span></label>
                            <input wire:model="kode" type="text" placeholder="Contoh: T001"
                                   class="form-input uppercase dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200"/>
                            @error('kode') <p class="form-error">{{ $message }}</p> @enderror
                        </div>
                        <div class="form-group">
                            <label class="form-label dark:text-gray-300">Status</label>
                            <div class="flex items-center gap-3 mt-2">
                                <button type="button"
                                        wire:click="$set('is_active', !{{ $is_active ? 'true' : 'false' }})"
                                        @class([
                                            'relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none',
                                            'bg-primary-600' => $is_active,
                                            'bg-gray-300 dark:bg-gray-600' => !$is_active,
                                        ])>
                                    <span @class([
                                        'inline-block h-4 w-4 rounded-full bg-white shadow transition-transform',
                                        'translate-x-6' => $is_active,
                                        'translate-x-1' => !$is_active,
                                    ])></span>
                                </button>
                                <span class="text-sm text-gray-700 dark:text-gray-300">{{ $is_active ? 'Aktif' : 'Nonaktif' }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label dark:text-gray-300">Nama Tindakan <span class="text-red-500">*</span></label>
                        <input wire:model="nama" type="text" placeholder="Nama lengkap tindakan"
                               class="form-input dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200"/>
                        @error('nama') <p class="form-error">{{ $message }}</p> @enderror
                    </div>

                    <div class="form-group">
                        <label class="form-label dark:text-gray-300">Deskripsi</label>
                        <textarea wire:model="deskripsi" rows="2" placeholder="Keterangan singkat (opsional)"
                                  class="form-textarea dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200"></textarea>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="form-group">
                            <label class="form-label dark:text-gray-300">Tarif (Rp) <span class="text-red-500">*</span></label>
                            <input wire:model="tarif" type="number" min="0" step="1000" placeholder="50000"
                                   class="form-input dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200"/>
                            @error('tarif') <p class="form-error">{{ $message }}</p> @enderror
                        </div>
                        <div class="form-group">
                            <label class="form-label dark:text-gray-300">Tarif BPJS (Rp)</label>
                            <input wire:model="tarif_bpjs" type="number" min="0" step="1000" placeholder="Opsional"
                                   class="form-input dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200"/>
                        </div>
                    </div>

                    {{-- Poli Mapping --}}
                    <div class="form-group">
                        <label class="form-label dark:text-gray-300">
                            Poli <span class="text-red-500">*</span>
                            <span class="text-xs text-gray-400 ml-1">(pilih satu atau lebih)</span>
                        </label>
                        <div class="grid grid-cols-2 sm:grid-cols-3 gap-2 mt-1 p-3 rounded-lg border border-gray-200 dark:border-gray-600 dark:bg-gray-700/30">
                            @foreach ($this->poliList as $poli)
                            <label class="flex items-center gap-2 cursor-pointer hover:text-gray-900 dark:hover:text-white">
                                <input type="checkbox" wire:model="poli_ids" value="{{ $poli->id }}"
                                       class="form-checkbox"/>
                                <span class="text-sm text-gray-700 dark:text-gray-300">
                                    {{ $poli->nama }}
                                    <span class="text-xs text-gray-400">({{ $poli->kode }})</span>
                                </span>
                            </label>
                            @endforeach
                        </div>
                        @error('poli_ids') <p class="form-error">{{ $message }}</p> @enderror
                    </div>

                    <div class="modal-footer -mx-5 -mb-5 mt-2">
                        <button type="button" wire:click="$set('showModal', false)" class="btn-secondary">Batal</button>
                        <button type="submit" class="btn-primary" wire:loading.attr="disabled">
                            <span wire:loading.remove wire:target="save">{{ $isEdit ? 'Simpan' : 'Tambah' }}</span>
                            <span wire:loading wire:target="save" class="flex items-center gap-2">
                                <div class="spinner h-4 w-4 border-white border-t-transparent"></div> Menyimpan...
                            </span>
                        </button>
                    </div>

                </form>
            </div>
        </div>
    </div>
    @endif
</div>
