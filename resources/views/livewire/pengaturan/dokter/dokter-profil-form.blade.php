<div>
    <span x-on:open-dokter-profil.window="$wire.open($event.detail.id)"></span>

    @if ($showModal)
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/50" wire:click="$set('showModal', false)"></div>
        <div class="relative z-10 w-full max-w-lg rounded-2xl bg-white shadow-2xl
                    dark:bg-gray-800 dark:border dark:border-gray-700 animate-fade-in">

            <div class="modal-header">
                <div>
                    <h3 class="modal-title dark:text-white">Edit Profil Dokter</h3>
                    <p class="text-xs text-gray-500 mt-0.5 dark:text-gray-400">{{ $namaUser }}</p>
                </div>
                <button wire:click="$set('showModal', false)" class="text-gray-400 hover:text-gray-600">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <div class="modal-body">
                <form wire:submit="save" class="space-y-4">

                    <div class="form-group">
                        <label class="form-label dark:text-gray-300">NIK</label>
                        <input wire:model="nik" type="text" maxlength="16" placeholder="16 digit angka"
                               class="form-input dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200"/>
                        @error('nik') <p class="form-error">{{ $message }}</p> @enderror
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="form-group">
                            <label class="form-label dark:text-gray-300">Nomor SIP</label>
                            <input wire:model="no_sip" type="text" placeholder="446/SIP-DU/2024"
                                   class="form-input dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200"/>
                            @error('no_sip') <p class="form-error">{{ $message }}</p> @enderror
                        </div>
                        <div class="form-group">
                            <label class="form-label dark:text-gray-300">Tgl. Expired SIP</label>
                            <input wire:model="tgl_expired_sip" type="date"
                                   class="form-input dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200"/>
                            @error('tgl_expired_sip') <p class="form-error">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label dark:text-gray-300">Spesialisasi</label>
                        <input wire:model="spesialisasi" type="text"
                               placeholder="Umum / Penyakit Dalam / Mata / ..."
                               class="form-input dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200"/>
                    </div>

                    <div class="modal-footer -mx-5 -mb-5 mt-2">
                        <button type="button" wire:click="$set('showModal', false)" class="btn-secondary">Batal</button>
                        <button type="submit" class="btn-primary" wire:loading.attr="disabled">
                            <span wire:loading.remove wire:target="save">Simpan</span>
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
