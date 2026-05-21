<div>
    <span
        x-on:open-penunjang-create.window="$wire.openCreate($event.detail.kategori)"
        x-on:open-penunjang-edit.window="$wire.openEdit($event.detail.id)"
    ></span>

    @if ($showModal)
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/50" wire:click="$set('showModal', false)"></div>
        <div class="relative z-10 w-full max-w-lg rounded-2xl bg-white shadow-2xl dark:bg-gray-800 dark:border dark:border-gray-700 animate-fade-in">

            <div class="modal-header">
                <h3 class="modal-title dark:text-white">
                    {{ $isEdit ? 'Edit Item' : 'Tambah Item ' . ucfirst($kategori) }}
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
                            <input wire:model="kode" type="text" placeholder="L001 / R001"
                                   class="form-input uppercase dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200"/>
                            @error('kode') <p class="form-error">{{ $message }}</p> @enderror
                        </div>
                        <div class="form-group">
                            <label class="form-label dark:text-gray-300">Kategori <span class="text-red-500">*</span></label>
                            <select wire:model="kategori"
                                    class="form-select dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200">
                                <option value="lab">Laboratorium</option>
                                <option value="radiologi">Radiologi</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label dark:text-gray-300">Nama Item <span class="text-red-500">*</span></label>
                        <input wire:model="nama" type="text" placeholder="Nama pemeriksaan"
                               class="form-input dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200"/>
                        @error('nama') <p class="form-error">{{ $message }}</p> @enderror
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="form-group">
                            <label class="form-label dark:text-gray-300">Tarif (Rp) <span class="text-red-500">*</span></label>
                            <input wire:model="tarif" type="number" min="0" placeholder="85000"
                                   class="form-input dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200"/>
                            @error('tarif') <p class="form-error">{{ $message }}</p> @enderror
                        </div>
                        <div class="form-group">
                            <label class="form-label dark:text-gray-300">Tarif BPJS (Rp)</label>
                            <input wire:model="tarif_bpjs" type="number" min="0" placeholder="Opsional"
                                   class="form-input dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200"/>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label dark:text-gray-300">Estimasi Waktu</label>
                        <input wire:model="satuan_waktu" type="text" placeholder="Contoh: 2 jam, 1 hari kerja"
                               class="form-input dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200"/>
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
