<div>
    @if (session('success'))
        <div class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
            {{ session('success') }}
        </div>
    @endif

    @if ($shift)
        {{-- Shift sedang aktif --}}
        <div class="rounded-xl border border-green-200 bg-green-50 p-5">
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-green-600">Shift Aktif</p>
                    <p class="mt-1 text-lg font-bold text-green-800">
                        Dibuka: {{ $shift->opened_at->format('d/m/Y H:i') }}
                    </p>
                    <p class="mt-1 text-sm text-green-700">
                        Modal awal: <strong>Rp {{ number_format($shift->modal_awal, 0, ',', '.') }}</strong>
                        &bull;
                        Tunai masuk: <strong>Rp {{ number_format($shift->total_tunai, 0, ',', '.') }}</strong>
                    </p>
                </div>
                <span class="inline-flex items-center gap-1.5 rounded-full bg-green-200 px-3 py-1 text-xs font-semibold text-green-800">
                    <span class="size-2 animate-pulse rounded-full bg-green-500"></span> OPEN
                </span>
            </div>

            <div class="mt-4 border-t border-green-200 pt-4">
                @if (! $showCloseForm)
                    <button wire:click="$set('showCloseForm', true)"
                        class="rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700 focus:outline-none">
                        Tutup Shift (Close Kas)
                    </button>
                @else
                    <div class="mt-2 space-y-3">
                        <p class="text-sm font-semibold text-gray-700">Input Uang Fisik Akhir</p>
                        <div>
                            <label class="block text-xs text-gray-600">Uang Fisik di Laci (Rp)</label>
                            <input wire:model="uangFisikAkhir" type="number" min="0" step="1000"
                                class="mt-1 w-48 rounded-lg border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                placeholder="0">
                            @error('uangFisikAkhir') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-xs text-gray-600">Catatan (opsional)</label>
                            <input wire:model="catatanClose" type="text"
                                class="mt-1 w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                placeholder="Catatan penutupan shift">
                        </div>
                        <div class="flex gap-2">
                            <button wire:click="closeShift"
                                class="rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700">
                                Konfirmasi Tutup Shift
                            </button>
                            <button wire:click="$set('showCloseForm', false)"
                                class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm text-gray-600 hover:bg-gray-50">
                                Batal
                            </button>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    @else
        {{-- Tidak ada shift aktif --}}
        <div class="rounded-xl border border-yellow-200 bg-yellow-50 p-5">
            <p class="text-sm font-semibold text-yellow-800">Belum ada shift aktif.</p>
            <p class="mt-1 text-xs text-yellow-700">Buka shift terlebih dahulu untuk dapat memproses transaksi.</p>

            @if (! $showOpenForm)
                <button wire:click="$set('showOpenForm', true)"
                    class="mt-3 rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                    Buka Shift (Open Kas)
                </button>
            @else
                <div class="mt-4 space-y-3">
                    <div>
                        <label class="block text-xs text-gray-700 font-medium">Uang Modal Awal (Rp)</label>
                        <input wire:model="modalAwal" type="number" min="0" step="1000"
                            class="mt-1 w-48 rounded-lg border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                            placeholder="0">
                        @error('modalAwal') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div class="flex gap-2">
                        <button wire:click="openShift"
                            class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                            Buka Shift
                        </button>
                        <button wire:click="$set('showOpenForm', false)"
                            class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm text-gray-600 hover:bg-gray-50">
                            Batal
                        </button>
                    </div>
                </div>
            @endif
        </div>
    @endif
</div>
