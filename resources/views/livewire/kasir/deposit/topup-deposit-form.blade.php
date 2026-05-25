<div class="space-y-5">

    @if(session('success'))
    <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
        {{ session('success') }}
    </div>
    @endif

    {{-- Header + tombol Top-up baru --}}
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-base font-semibold text-gray-800">Deposit Pasien Aktif</h2>
            <p class="text-xs text-gray-500 mt-0.5">Pasien yang memiliki saldo deposit tersisa</p>
        </div>
        <button wire:click="openTopup()" type="button"
            class="flex items-center gap-1.5 rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-700">
            <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            Top-up Deposit
        </button>
    </div>

    {{-- Search daftar --}}
    <div class="relative max-w-xs">
        <input type="text" wire:model.live.debounce.300ms="searchDeposit"
            class="w-full rounded-lg border-gray-300 py-2 pl-9 pr-3 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500"
            placeholder="Cari nama atau No. RM...">
        <svg class="absolute left-3 top-2.5 size-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
        </svg>
    </div>

    {{-- Tabel daftar deposit aktif --}}
    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-xs font-semibold uppercase text-gray-500">
                <tr>
                    <th class="px-4 py-3 text-left">Pasien</th>
                    <th class="px-4 py-3 text-left">No. RM</th>
                    <th class="px-4 py-3 text-right">Saldo</th>
                    <th class="px-4 py-3 text-right">Total Top-up</th>
                    <th class="px-4 py-3 text-right">Total Terpakai</th>
                    <th class="px-4 py-3 text-center">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($this->daftarDeposit as $dep)
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 font-medium text-gray-800">{{ $dep->pasien->nama }}</td>
                    <td class="px-4 py-3 font-mono text-xs text-gray-500">{{ $dep->pasien->nomor_rm }}</td>
                    <td class="px-4 py-3 text-right font-bold text-emerald-700">
                        Rp {{ number_format($dep->saldo, 0, ',', '.') }}
                    </td>
                    <td class="px-4 py-3 text-right text-gray-600">
                        Rp {{ number_format($dep->total_topup, 0, ',', '.') }}
                    </td>
                    <td class="px-4 py-3 text-right text-gray-600">
                        Rp {{ number_format($dep->total_terpakai, 0, ',', '.') }}
                    </td>
                    <td class="px-4 py-3 text-center">
                        <div class="flex items-center justify-center gap-1.5">
                            <button wire:click="openTopup({{ $dep->pasien_id }})" type="button"
                                class="rounded-lg bg-primary-50 px-2.5 py-1.5 text-xs font-semibold text-primary-700 border border-primary-200 hover:bg-primary-100">
                                Top-up
                            </button>
                            <button wire:click="openRefund({{ $dep->pasien_id }})" type="button"
                                class="rounded-lg bg-red-50 px-2.5 py-1.5 text-xs font-semibold text-red-600 border border-red-200 hover:bg-red-100">
                                Refund
                            </button>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="px-4 py-10 text-center text-sm text-gray-400">
                        @if($searchDeposit)
                            Tidak ditemukan pasien dengan deposit aktif sesuai pencarian.
                        @else
                            Belum ada pasien dengan saldo deposit.
                        @endif
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>

        @if($this->daftarDeposit->hasPages())
        <div class="border-t border-gray-200 px-4 py-3">
            {{ $this->daftarDeposit->links() }}
        </div>
        @endif
    </div>

    {{-- ══════════════════════════════════════════════════════════════
         MODAL TOP-UP
    ══════════════════════════════════════════════════════════════ --}}
    @if($showTopupModal)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50"
         x-data @click.self="$wire.set('showTopupModal', false)">
        <div class="w-full max-w-md rounded-xl bg-white shadow-xl mx-4">

            <div class="flex items-center justify-between border-b px-5 py-4">
                <h3 class="font-semibold text-gray-800">Top-up Deposit Pasien</h3>
                <button wire:click="$set('showTopupModal', false)" class="text-gray-400 hover:text-gray-600">&times;</button>
            </div>

            <div class="p-5 space-y-4">
                {{-- Pilih pasien jika belum dipilih --}}
                @if(!$pasienDipilih)
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Cari Pasien</label>
                    <div class="relative">
                        <input type="text" wire:model.live.debounce.300ms="searchPasien"
                            class="w-full rounded-lg border-gray-300 pl-9 pr-3 py-2 text-sm focus:ring-2 focus:ring-primary-500"
                            placeholder="Nama, No. RM, atau telepon...">
                        <svg class="absolute left-3 top-2.5 size-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                    </div>
                    @if(!empty($hasilSearch))
                    <div class="mt-1.5 rounded-lg border border-gray-200 divide-y overflow-hidden max-h-48 overflow-y-auto">
                        @foreach($hasilSearch as $p)
                        <button type="button" wire:click="pilihPasien({{ $p['id'] }})"
                            class="w-full text-left px-3 py-2 hover:bg-gray-50 flex items-center justify-between text-sm">
                            <div>
                                <span class="font-medium text-gray-900">{{ $p['nama'] }}</span>
                                <span class="text-gray-400 ml-2 text-xs">{{ $p['nomor_rm'] }}</span>
                            </div>
                            <span class="text-xs text-emerald-600 font-semibold">
                                Saldo: Rp {{ number_format($p['saldo'], 0, ',', '.') }}
                            </span>
                        </button>
                        @endforeach
                    </div>
                    @endif
                    @error('pasienId') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                @else
                {{-- Pasien terpilih --}}
                <div class="flex items-center justify-between rounded-lg bg-blue-50 border border-blue-200 px-3 py-2.5">
                    <div>
                        <p class="font-semibold text-blue-900 text-sm">{{ $pasienDipilih['nama'] }}</p>
                        <p class="text-xs text-blue-600">No. RM: {{ $pasienDipilih['nomor_rm'] }}</p>
                    </div>
                    <button type="button" wire:click="$set('pasienDipilih', null); $set('pasienId', null)"
                        class="text-xs text-red-500 hover:text-red-700">Ganti</button>
                </div>

                @if($depositInfo)
                <div class="grid grid-cols-3 gap-2 text-center text-xs">
                    <div class="rounded-lg bg-gray-50 p-2">
                        <p class="text-gray-500">Saldo</p>
                        <p class="font-bold text-gray-900">Rp {{ number_format($depositInfo['saldo'], 0, ',', '.') }}</p>
                    </div>
                    <div class="rounded-lg bg-gray-50 p-2">
                        <p class="text-gray-500">Total Top-up</p>
                        <p class="font-semibold text-emerald-600">Rp {{ number_format($depositInfo['total_topup'], 0, ',', '.') }}</p>
                    </div>
                    <div class="rounded-lg bg-gray-50 p-2">
                        <p class="text-gray-500">Terpakai</p>
                        <p class="font-semibold text-red-500">Rp {{ number_format($depositInfo['total_terpakai'], 0, ',', '.') }}</p>
                    </div>
                </div>
                @endif

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Jumlah Top-up (Rp) <span class="text-red-500">*</span>
                    </label>
                    <input type="number" wire:model="jumlah" min="1000" step="1000"
                        class="w-full rounded-lg border-gray-300 px-3 py-2 text-sm focus:ring-2 focus:ring-primary-500 @error('jumlah') border-red-400 @enderror"
                        placeholder="Minimal Rp 1.000">
                    @error('jumlah') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Keterangan (opsional)</label>
                    <input type="text" wire:model="keterangan" maxlength="200"
                        class="w-full rounded-lg border-gray-300 px-3 py-2 text-sm"
                        placeholder="Catatan top-up">
                </div>
                @endif
            </div>

            <div class="flex justify-end gap-2 border-t px-5 py-3">
                <button type="button" wire:click="$set('showTopupModal', false)"
                    class="rounded-lg border border-gray-300 px-4 py-2 text-sm hover:bg-gray-50">Batal</button>
                @if($pasienDipilih)
                <button type="button" wire:click="simpan" wire:loading.attr="disabled"
                    class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-700 disabled:opacity-50">
                    <span wire:loading.remove wire:target="simpan">Proses Top-up</span>
                    <span wire:loading wire:target="simpan">Memproses...</span>
                </button>
                @endif
            </div>
        </div>
    </div>
    @endif

    {{-- ══════════════════════════════════════════════════════════════
         MODAL REFUND
    ══════════════════════════════════════════════════════════════ --}}
    @if($showRefundModal)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50"
         x-data @click.self="$wire.set('showRefundModal', false)">
        <div class="w-full max-w-md rounded-xl bg-white shadow-xl mx-4">

            <div class="flex items-center justify-between rounded-t-xl border-b bg-red-50 px-5 py-4">
                <div class="flex items-center gap-2">
                    <svg class="size-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/>
                    </svg>
                    <h3 class="font-semibold text-red-700">Refund Deposit Pasien</h3>
                </div>
                <button wire:click="$set('showRefundModal', false)" class="text-gray-400 hover:text-gray-600">&times;</button>
            </div>

            <div class="p-5 space-y-4">
                @if($errorRefund)
                <div class="rounded-lg border border-red-200 bg-red-50 px-3 py-2.5 text-sm text-red-700">
                    {{ $errorRefund }}
                </div>
                @endif

                {{-- Info pasien --}}
                @if($refundPasienInfo)
                <div class="rounded-lg bg-gray-50 px-4 py-3">
                    <p class="font-semibold text-gray-800">{{ $refundPasienInfo['nama'] }}</p>
                    <p class="text-xs text-gray-500">No. RM: {{ $refundPasienInfo['nomor_rm'] }}</p>
                    <p class="mt-1.5 text-sm font-bold text-emerald-700">
                        Saldo: Rp {{ number_format($refundPasienInfo['saldo'], 0, ',', '.') }}
                    </p>
                </div>

                <div class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2.5 text-xs text-amber-800">
                    <strong>Perhatian:</strong> Refund berarti deposit dikembalikan ke pasien secara tunai/fisik.
                    Pastikan pembayaran fisik sudah diberikan sebelum memproses.
                </div>
                @endif

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Jumlah Refund (Rp) <span class="text-red-500">*</span>
                    </label>
                    <input type="number" wire:model="jumlahRefund"
                        min="1000" step="1000"
                        max="{{ $refundPasienInfo['saldo'] ?? 0 }}"
                        class="w-full rounded-lg border-gray-300 px-3 py-2 text-sm focus:ring-2 focus:ring-red-500 @error('jumlahRefund') border-red-400 @enderror"
                        placeholder="Maks: Rp {{ number_format($refundPasienInfo['saldo'] ?? 0, 0, ',', '.') }}">
                    @error('jumlahRefund') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Alasan Refund <span class="text-red-500">*</span>
                    </label>
                    <textarea wire:model="alasanRefund" rows="2" maxlength="200"
                        class="w-full rounded-lg border-gray-300 px-3 py-2 text-sm focus:ring-2 focus:ring-red-500 @error('alasanRefund') border-red-400 @enderror"
                        placeholder="Jelaskan alasan refund deposit..."></textarea>
                    @error('alasanRefund') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="flex justify-end gap-2 border-t px-5 py-3">
                <button type="button" wire:click="$set('showRefundModal', false)"
                    class="rounded-lg border border-gray-300 px-4 py-2 text-sm hover:bg-gray-50">Batal</button>
                <button type="button" wire:click="prosesRefund" wire:loading.attr="disabled"
                    class="rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700 disabled:opacity-50">
                    <span wire:loading.remove wire:target="prosesRefund">Proses Refund</span>
                    <span wire:loading wire:target="prosesRefund">Memproses...</span>
                </button>
            </div>
        </div>
    </div>
    @endif

</div>
