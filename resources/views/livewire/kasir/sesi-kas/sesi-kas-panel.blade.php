<div>
    {{-- Status Kas --}}
    @if($sesiAktif)
    <div class="bg-white rounded-xl border-l-4 border-emerald-500 shadow-sm p-4 flex items-center justify-between">
        <div class="flex items-center gap-3">
            <div class="w-3 h-3 rounded-full bg-emerald-500 animate-pulse"></div>
            <div>
                <p class="font-semibold text-gray-900">Kas Sedang Buka</p>
                <p class="text-xs text-gray-500">
                    Dibuka: {{ $sesiAktif->dibuka_pada->format('H:i') }} &middot;
                    Saldo awal: Rp {{ number_format($sesiAktif->saldo_awal, 0, ',', '.') }}
                </p>
            </div>
        </div>
        <button type="button" wire:click="$set('showTutup', true)"
            class="px-3 py-1.5 text-sm font-medium bg-amber-100 text-amber-700 rounded-lg hover:bg-amber-200 transition">
            Tutup Kas
        </button>
    </div>

    {{-- Modal Tutup Kas --}}
    @if($showTutup)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50"
         x-data @click.self="$wire.set('showTutup', false)">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-sm mx-4">
            <div class="flex items-center justify-between p-4 border-b">
                <h3 class="font-semibold text-gray-900">Tutup Kas</h3>
                <button wire:click="$set('showTutup', false)" class="text-gray-400 hover:text-gray-600">&times;</button>
            </div>
            <div class="p-4 space-y-3">
                <div class="bg-amber-50 border border-amber-200 rounded-lg p-3 text-sm text-amber-800">
                    Setelah kas ditutup, pembatalan tagihan tidak dapat dilakukan.
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Catatan (opsional)</label>
                    <textarea wire:model="catatanTutup" rows="2"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                        placeholder="Catatan penutupan kas"></textarea>
                    @error('catatanTutup') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
            </div>
            <div class="flex justify-end gap-2 p-4 border-t">
                <button wire:click="$set('showTutup', false)"
                    class="px-4 py-2 text-sm border border-gray-300 rounded-lg hover:bg-gray-50">Batal</button>
                <button wire:click="tutupKas" wire:loading.attr="disabled"
                    class="px-4 py-2 text-sm bg-amber-500 text-white rounded-lg hover:bg-amber-600">
                    <span wire:loading.remove wire:target="tutupKas">Tutup Sekarang</span>
                    <span wire:loading wire:target="tutupKas">Menutup...</span>
                </button>
            </div>
        </div>
    </div>
    @endif

    @else
    <div class="bg-white rounded-xl border-l-4 border-gray-300 shadow-sm p-4 flex items-center justify-between">
        <div class="flex items-center gap-3">
            <div class="w-3 h-3 rounded-full bg-gray-400"></div>
            <div>
                <p class="font-semibold text-gray-700">Kas Belum Dibuka</p>
                <p class="text-xs text-gray-400">{{ now()->format('d/m/Y') }}</p>
            </div>
        </div>
        <button type="button" wire:click="$set('showBuka', true)"
            class="px-3 py-1.5 text-sm font-medium bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition">
            Buka Kas
        </button>
    </div>

    {{-- Modal Buka Kas --}}
    @if($showBuka)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50"
         x-data @click.self="$wire.set('showBuka', false)">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-sm mx-4">
            <div class="flex items-center justify-between p-4 border-b">
                <h3 class="font-semibold text-gray-900">Buka Kas &mdash; {{ now()->format('d/m/Y') }}</h3>
                <button wire:click="$set('showBuka', false)" class="text-gray-400 hover:text-gray-600">&times;</button>
            </div>
            <div class="p-4 space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Saldo Awal Kas (Rp) <span class="text-red-500">*</span>
                    </label>
                    <input type="number" wire:model="saldoAwal"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-primary-500 @error('saldoAwal') border-red-400 @enderror"
                        placeholder="0" min="0" />
                    @error('saldoAwal') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Catatan</label>
                    <input type="text" wire:model="catatan"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"
                        placeholder="Opsional" />
                </div>
            </div>
            <div class="flex justify-end gap-2 p-4 border-t">
                <button wire:click="$set('showBuka', false)"
                    class="px-4 py-2 text-sm border border-gray-300 rounded-lg hover:bg-gray-50">Batal</button>
                <button wire:click="bukaKas" wire:loading.attr="disabled"
                    class="px-4 py-2 text-sm bg-primary-600 text-white rounded-lg hover:bg-primary-700">
                    <span wire:loading.remove>Buka Kas</span>
                    <span wire:loading>Membuka...</span>
                </button>
            </div>
        </div>
    </div>
    @endif

    {{-- Buka Kembali (SuperAdmin) --}}
    @if($sesiTutupHariIni->count() > 0)
    <div class="mt-2">
        <button type="button" wire:click="$set('showBukaKembali', true)"
            class="text-sm text-primary-600 hover:underline">
            Buka Kembali Kas yang Sudah Tutup (SuperAdmin)
        </button>
    </div>

    @if($showBukaKembali)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50"
         x-data @click.self="$wire.set('showBukaKembali', false)">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-md mx-4">
            <div class="flex items-center justify-between p-4 border-b bg-red-50 rounded-t-xl">
                <h3 class="font-semibold text-red-700">Buka Kembali Kas</h3>
                <button wire:click="$set('showBukaKembali', false)" class="text-gray-400">&times;</button>
            </div>
            <div class="p-4 space-y-4">
                @if($errorMsg)
                <div class="bg-red-50 border border-red-200 rounded-lg p-3 text-sm text-red-700">{{ $errorMsg }}</div>
                @endif

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Sesi Kas <span class="text-red-500">*</span></label>
                    <select wire:model="sesiIdBukaKembali"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm @error('sesiIdBukaKembali') border-red-400 @enderror">
                        <option value="">— Pilih Sesi —</option>
                        @foreach($sesiTutupHariIni as $s)
                        <option value="{{ $s->id }}">
                            {{ $s->user->nama }} &mdash; Tutup: {{ $s->ditutup_pada?->format('H:i') }}
                            (Saldo: Rp {{ number_format($s->saldo_akhir ?? 0, 0, ',', '.') }})
                        </option>
                        @endforeach
                    </select>
                    @error('sesiIdBukaKembali') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Alasan <span class="text-red-500">*</span></label>
                    <textarea wire:model="alasanBukaKembali" rows="2"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm @error('alasanBukaKembali') border-red-400 @enderror"
                        placeholder="Minimal 10 karakter"></textarea>
                    @error('alasanBukaKembali') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div x-data="{ show: false }">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Password SuperAdmin <span class="text-red-500">*</span></label>
                    <div class="relative">
                        <input :type="show ? 'text' : 'password'"
                            wire:model="passwordBukaKembali"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 pr-10 text-sm @error('passwordBukaKembali') border-red-400 @enderror"
                            placeholder="Password SuperAdmin" />
                        <button type="button" @click="show = !show"
                            class="absolute inset-y-0 right-3 flex items-center text-gray-400">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M15 12a3 3 0 11-6 0 3 3 0 016 0z M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                            </svg>
                        </button>
                    </div>
                    @error('passwordBukaKembali') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
            </div>
            <div class="flex justify-end gap-2 p-4 border-t">
                <button wire:click="$set('showBukaKembali', false)"
                    class="px-4 py-2 text-sm border border-gray-300 rounded-lg hover:bg-gray-50">Batal</button>
                <button wire:click="bukaKasKembali" wire:loading.attr="disabled"
                    class="px-4 py-2 text-sm bg-red-600 text-white rounded-lg hover:bg-red-700">
                    <span wire:loading.remove>Buka Kas Kembali</span>
                    <span wire:loading>Memproses...</span>
                </button>
            </div>
        </div>
    </div>
    @endif
    @endif
    @endif
</div>
