@if($show)
<div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50"
     x-data @click.self="$wire.set('show', false)">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-md mx-4">

        <div class="flex items-center justify-between p-4 bg-red-50 rounded-t-xl border-b">
            <div class="flex items-center gap-2">
                <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
                <h3 class="font-semibold text-red-700">Batalkan Tagihan</h3>
            </div>
            <button wire:click="$set('show', false)" class="text-gray-400 hover:text-gray-600">&times;</button>
        </div>

        <div class="p-4 space-y-4">
            @if($errorMsg)
            <div class="bg-red-50 border border-red-200 rounded-lg p-3 text-sm text-red-700">
                {{ $errorMsg }}
            </div>
            @endif

            <div class="bg-amber-50 border border-amber-200 rounded-lg p-3 text-sm text-amber-800">
                <strong>Perhatian:</strong> Tindakan ini tidak dapat diurungkan.
                Saldo deposit yang terpakai akan dikembalikan secara otomatis.
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Alasan Pembatalan <span class="text-red-500">*</span>
                </label>
                <textarea wire:model="alasan" rows="3"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-red-500 @error('alasan') border-red-400 @enderror"
                    placeholder="Jelaskan alasan pembatalan (minimal 10 karakter)"></textarea>
                @error('alasan') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            <div x-data="{ show: false }">
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Password SuperAdmin <span class="text-red-500">*</span>
                </label>
                <div class="relative">
                    <input :type="show ? 'text' : 'password'"
                        wire:model="password"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 pr-10 text-sm @error('password') border-red-400 @enderror"
                        placeholder="Masukkan password SuperAdmin" />
                    <button type="button" @click="show = !show"
                        class="absolute inset-y-0 right-3 flex items-center text-gray-400 hover:text-gray-600">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path x-show="!show" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M15 12a3 3 0 11-6 0 3 3 0 016 0z M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                            <path x-show="show" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>
                        </svg>
                    </button>
                </div>
                @error('password') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                <p class="text-xs text-gray-400 mt-1">Diperlukan verifikasi password SuperAdmin untuk tindakan ini.</p>
            </div>
        </div>

        <div class="flex justify-end gap-2 p-4 border-t">
            <button type="button" wire:click="$set('show', false)"
                class="px-4 py-2 text-sm border border-gray-300 rounded-lg hover:bg-gray-50">Batal</button>
            <button type="button" wire:click="batalkan" wire:loading.attr="disabled"
                class="px-4 py-2 text-sm bg-red-600 text-white rounded-lg hover:bg-red-700">
                <span wire:loading.remove wire:target="batalkan">Ya, Batalkan</span>
                <span wire:loading wire:target="batalkan">Memproses...</span>
            </button>
        </div>
    </div>
</div>
@endif
