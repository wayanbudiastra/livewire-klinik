<div>
    <span
        x-on:open-peralatan-create.window="$wire.openCreate()"
        x-on:open-peralatan-edit.window="$wire.openEdit($event.detail.id)"
    ></span>

    @if ($showModal)
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/50" wire:click="$set('showModal', false)"></div>
        <div class="relative z-10 w-full max-w-lg rounded-2xl bg-white shadow-2xl dark:bg-gray-800 dark:border dark:border-gray-700 animate-fade-in">

            <div class="modal-header">
                <h3 class="modal-title dark:text-white">
                    {{ $isEdit ? 'Edit Peralatan' : 'Tambah Peralatan Medis' }}
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
                            <input wire:model="kode" type="text" placeholder="A001"
                                   class="form-input uppercase dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200"/>
                            @error('kode') <p class="form-error">{{ $message }}</p> @enderror
                        </div>
                        <div class="form-group">
                            <label class="form-label dark:text-gray-300">No. Seri</label>
                            <input wire:model="nomor_seri" type="text" placeholder="SN-001"
                                   class="form-input dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200"/>
                            @error('nomor_seri') <p class="form-error">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label dark:text-gray-300">Nama Peralatan <span class="text-red-500">*</span></label>
                        <input wire:model="nama" type="text" placeholder="Nama alat medis"
                               class="form-input dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200"/>
                        @error('nama') <p class="form-error">{{ $message }}</p> @enderror
                    </div>

                    <div class="form-group">
                        <label class="form-label dark:text-gray-300">Merk</label>
                        <input wire:model="merk" type="text" placeholder="Omron, GE, dll."
                               class="form-input dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200"/>
                    </div>

                    <div class="form-group">
                        <label class="form-label dark:text-gray-300">Deskripsi</label>
                        <textarea wire:model="deskripsi" rows="2"
                                  class="form-textarea dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200"></textarea>
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
