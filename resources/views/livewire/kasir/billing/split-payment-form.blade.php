<div class="space-y-5">

    @if($errors->has('global'))
    <div class="bg-red-50 border border-red-200 rounded-lg p-3 text-sm text-red-700">
        {{ $errors->first('global') }}
    </div>
    @endif

    @if($this->hasPendingResep)
    <div class="flex items-start gap-3 rounded-lg border border-orange-200 bg-orange-50 px-4 py-3 text-sm text-orange-800">
        <svg class="mt-0.5 size-5 shrink-0 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
        </svg>
        <div>
            <p class="font-semibold">Resep belum dikonfirmasi</p>
            <p class="mt-0.5 text-orange-700">Masih ada resep obat yang belum dikonfirmasi oleh apoteker. Pembayaran tidak dapat diproses sebelum seluruh resep dikonfirmasi.</p>
        </div>
    </div>
    @endif

    {{-- Ringkasan Invoice --}}
    <div class="bg-white rounded-xl shadow-sm p-5">
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
            <div>
                <p class="text-gray-500">No. Invoice</p>
                <p class="font-mono font-semibold">{{ $billing->nomor_invoice }}</p>
            </div>
            <div>
                <p class="text-gray-500">Total Tagihan</p>
                <p class="font-semibold text-gray-900">Rp {{ number_format($billing->total_tagihan, 0, ',', '.') }}</p>
            </div>
            <div>
                <p class="text-gray-500">Sudah Dibayar</p>
                <p class="font-semibold text-emerald-600">Rp {{ number_format($billing->total_bayar, 0, ',', '.') }}</p>
            </div>
            <div>
                <p class="text-gray-500">Sisa Tagihan</p>
                <p class="font-bold text-lg {{ $sisaTagihan > 0 ? 'text-red-600' : 'text-emerald-600' }}">
                    Rp {{ number_format($sisaTagihan, 0, ',', '.') }}
                </p>
            </div>
        </div>

        @if($saldoDeposit > 0)
        <div class="mt-3 flex items-center gap-2 text-sm bg-blue-50 px-4 py-2 rounded-lg">
            <svg class="w-4 h-4 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>
            </svg>
            <span class="text-blue-700">
                Saldo Deposit Pasien: <strong>Rp {{ number_format($saldoDeposit, 0, ',', '.') }}</strong>
            </span>
        </div>
        @endif
    </div>

    {{-- Tambah Metode --}}
    <div class="bg-white rounded-xl shadow-sm p-5">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-sm font-semibold text-gray-700">Tambah Metode Pembayaran</h3>
            <button type="button" wire:click="isiOtomatis"
                class="text-xs px-3 py-1 border border-gray-300 rounded-lg hover:bg-gray-50">
                Isi Otomatis
            </button>
        </div>

        {{-- Pilih Metode --}}
        <div class="grid grid-cols-2 md:grid-cols-4 gap-2 mb-4">
            @foreach($metodeList as $val => $label)
            @php
                $disabled = $val === 'deposit' && $saldoDeposit <= 0;
                $active   = $metode === $val;
            @endphp
            <button type="button"
                @if(!$disabled) wire:click="$set('metode', '{{ $val }}')" @endif
                @disabled($disabled)
                class="py-2 px-3 rounded-lg border text-xs font-medium transition-all text-center
                    {{ $active ? 'bg-primary-50 border-primary-500 text-primary-700 ring-1 ring-primary-500' : 'border-gray-200 hover:bg-gray-50 text-gray-700' }}
                    {{ $disabled ? 'opacity-40 cursor-not-allowed' : 'cursor-pointer' }}">
                {{ $label }}
                @if($val === 'deposit')
                    <div class="text-xs text-gray-400 mt-0.5">Rp {{ number_format($saldoDeposit, 0, ',', '.') }}</div>
                @endif
            </button>
            @endforeach
        </div>

        {{-- Input Jumlah --}}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Jumlah (Rp) <span class="text-red-500">*</span></label>
                <input type="number" wire:model="jumlahInput"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-primary-500 @error('jumlahInput') border-red-400 @enderror"
                    placeholder="0" min="0" step="0.01" />
                @error('jumlahInput') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            @if($metode === 'bpjs')
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Nomor SEP <span class="text-red-500">*</span></label>
                <input type="text" wire:model="referensi"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm @error('referensi') border-red-400 @enderror"
                    placeholder="Nomor SEP BPJS" />
                @error('referensi') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
            @endif

            @if(in_array($metode, ['transfer','debit','kredit','qris']))
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">No. Referensi / Otorisasi</label>
                <input type="text" wire:model="referensi"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"
                    placeholder="Opsional" />
            </div>
            @endif
        </div>

        @if($metode === 'asuransi')
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Nama Asuransi <span class="text-red-500">*</span></label>
                <input type="text" wire:model="namaAsuransi"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm @error('namaAsuransi') border-red-400 @enderror"
                    placeholder="Prudential, AXA, dll" />
                @error('namaAsuransi') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Nomor Polis <span class="text-red-500">*</span></label>
                <input type="text" wire:model="nomorPolis"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm @error('nomorPolis') border-red-400 @enderror" />
                @error('nomorPolis') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Cover Asuransi (Rp) <span class="text-red-500">*</span></label>
                <input type="number" wire:model="jumlahCover"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm @error('jumlahCover') border-red-400 @enderror"
                    placeholder="0" />
                @error('jumlahCover') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
        </div>
        @endif

        <button type="button" wire:click="tambahItem"
            class="px-4 py-2 bg-gray-100 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-200 transition">
            + Tambahkan
        </button>
    </div>

    {{-- Daftar Item Split --}}
    @if(!empty($splitItems))
    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
        <div class="px-5 py-3 border-b">
            <h3 class="text-sm font-semibold text-gray-700">Rincian Pembayaran</h3>
        </div>
        <table class="w-full text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-2 text-left font-medium text-gray-600">Metode</th>
                    <th class="px-4 py-2 text-left font-medium text-gray-600">Detail</th>
                    <th class="px-4 py-2 text-right font-medium text-gray-600">Jumlah (Rp)</th>
                    <th class="px-4 py-2"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach($splitItems as $i => $item)
                <tr>
                    <td class="px-4 py-3">
                        <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-700">
                            {{ $item['label'] }}
                        </span>
                    </td>
                    <td class="px-4 py-3 text-gray-500 text-xs">
                        @if($item['referensi']) {{ $item['referensi'] }} @endif
                        @if($item['nama_asuransi'])
                            {{ $item['nama_asuransi'] }} &mdash; Polis: {{ $item['nomor_polis'] }}
                            @if($item['jumlah_cover'])
                            <br>Cover: Rp {{ number_format($item['jumlah_cover'], 0, ',', '.') }}
                            @endif
                        @endif
                    </td>
                    <td class="px-4 py-3 text-right font-semibold text-gray-900">
                        {{ number_format($item['jumlah'], 0, ',', '.') }}
                    </td>
                    <td class="px-4 py-3 text-center">
                        <button type="button" wire:click="hapusItem({{ $i }})"
                            class="text-red-400 hover:text-red-600">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </td>
                </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr class="bg-gray-50 font-semibold">
                    <td colspan="2" class="px-4 py-3 text-right text-gray-700">Total Terbayar</td>
                    <td class="px-4 py-3 text-right text-lg {{ abs($totalSudahDiisi - $sisaTagihan) < 0.01 ? 'text-emerald-600' : 'text-red-600' }}">
                        Rp {{ number_format($totalSudahDiisi, 0, ',', '.') }}
                    </td>
                    <td></td>
                </tr>
                @if(abs($totalSudahDiisi - $sisaTagihan) >= 0.01)
                <tr class="bg-red-50">
                    <td colspan="2" class="px-4 py-2 text-right text-sm text-red-600">Kurang</td>
                    <td class="px-4 py-2 text-right text-sm text-red-600 font-medium">
                        Rp {{ number_format($sisaTagihan - $totalSudahDiisi, 0, ',', '.') }}
                    </td>
                    <td></td>
                </tr>
                @endif
            </tfoot>
        </table>
    </div>
    @endif

    {{-- Tombol Konfirmasi --}}
    <div class="flex justify-end gap-3">
        <a href="{{ route('kasir.billing.show', $billing) }}"
            class="px-4 py-2 text-sm border border-gray-300 rounded-lg hover:bg-gray-50">Batal</a>
        <button type="button" wire:click="konfirmasi" wire:loading.attr="disabled"
            class="px-6 py-2 bg-primary-600 text-white text-sm font-semibold rounded-lg hover:bg-primary-700 transition disabled:opacity-50"
            @disabled($this->hasPendingResep || abs($totalSudahDiisi - $sisaTagihan) >= 0.01 || empty($splitItems))>
            <span wire:loading.remove>Konfirmasi Pembayaran</span>
            <span wire:loading>Memproses...</span>
        </button>
    </div>
</div>
