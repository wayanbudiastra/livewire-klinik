<div>
    <span x-on:open-reset-password.window="$wire.open($event.detail.userId)"></span>

    @if ($showModal)
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4">

        <div class="absolute inset-0 bg-black/50" wire:click="$set('showModal', false)"></div>

        <div class="relative z-10 w-full max-w-md rounded-2xl bg-white shadow-2xl
                    dark:bg-gray-800 dark:border dark:border-gray-700 animate-fade-in">

            {{-- Header --}}
            <div class="modal-header">
                <div>
                    <h3 class="modal-title dark:text-white">Reset Password</h3>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                        Pengguna: <span class="font-semibold text-gray-700 dark:text-gray-300">{{ $userName }}</span>
                    </p>
                </div>
                <button wire:click="$set('showModal', false)"
                        class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            {{-- Body --}}
            <div class="modal-body">

                {{-- Info box --}}
                <div class="flex items-start gap-3 rounded-lg bg-amber-50 border border-amber-200 p-3 text-sm text-amber-800 dark:bg-amber-900/20 dark:border-amber-700 dark:text-amber-300">
                    <svg class="h-4 w-4 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                    </svg>
                    <span>Password baru akan langsung aktif. Pengguna akan diminta login ulang.</span>
                </div>

                <form wire:submit="save" class="space-y-4 mt-4">

                    {{-- Password Baru --}}
                    <div class="form-group" x-data="{ show: false }">
                        <label class="form-label dark:text-gray-300">
                            Password Baru <span class="text-red-500">*</span>
                        </label>
                        <div class="relative">
                            <input wire:model="new_password" :type="show ? 'text' : 'password'"
                                   placeholder="Min. 8 karakter"
                                   class="form-input pr-10 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200"/>
                            <button type="button" @click="show = !show"
                                    class="absolute inset-y-0 right-3 flex items-center text-gray-400 hover:text-gray-600">
                                <svg x-show="!show" class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M15 12a3 3 0 11-6 0 3 3 0 016 0M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                </svg>
                                <svg x-show="show" class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>
                                </svg>
                            </button>
                        </div>
                        @error('new_password') <p class="form-error">{{ $message }}</p> @enderror
                    </div>

                    {{-- Konfirmasi Password --}}
                    <div class="form-group">
                        <label class="form-label dark:text-gray-300">
                            Konfirmasi Password <span class="text-red-500">*</span>
                        </label>
                        <input wire:model="new_password_confirmation" type="password"
                               placeholder="Ulangi password baru"
                               class="form-input dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200"/>
                        @error('new_password_confirmation') <p class="form-error">{{ $message }}</p> @enderror
                    </div>

                    {{-- Footer --}}
                    <div class="modal-footer -mx-5 -mb-5 mt-2">
                        <button type="button" wire:click="$set('showModal', false)" class="btn-secondary">
                            Batal
                        </button>
                        <button type="submit" class="btn-warning" wire:loading.attr="disabled">
                            <span wire:loading.remove wire:target="save">Reset Password</span>
                            <span wire:loading wire:target="save" class="flex items-center gap-2">
                                <div class="spinner h-4 w-4 border-white border-t-transparent"></div>
                                Menyimpan...
                            </span>
                        </button>
                    </div>

                </form>
            </div>
        </div>
    </div>
    @endif
</div>
