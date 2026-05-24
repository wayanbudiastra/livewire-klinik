<div x-data="{ confirmBayar: false }">

    @if (session('success'))
        <div class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
            {{ session('success') }}
        </div>
    @endif
    @if (session('error'))
        <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            {{ session('error') }}
        </div>
    @endif

    {{-- Guard: shift harus aktif --}}
    @if (! $this->activeShift)
        <div class="rounded-xl border border-yellow-200 bg-yellow-50 px-5 py-4 text-sm text-yellow-800">
            <strong>Shift belum dibuka.</strong> Buka shift kasir terlebih dahulu untuk memproses transaksi.
        </div>
    @else

    {{-- Search Pasien --}}
    @if (! $kunjunganId)
    <div class="mb-6">
        <label class="mb-1 block text-sm font-medium text-gray-700">Cari Pasien (Nama / No. RM)</label>
        <div class="relative">
            <input wire:model.live.debounce.400ms="searchPasien"
                type="text"
                class="w-full rounded-xl border-gray-300 py-2.5 pl-4 pr-10 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                placeholder="Ketik nama atau No. RM pasien (min. 2 karakter)...">
            <svg class="absolute right-3 top-2.5 size-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M21 21l-4.35-4.35M11 18a7 7 0 110-14 7 7 0 010 14z"/>
            </svg>
        </div>

        @if (count($searchResults))
            <div class="mt-2 divide-y divide-gray-100 overflow-hidden rounded-xl border border-gray-200 bg-white shadow-md">
                @foreach ($searchResults as $r)
                    <button wire:click="selectKunjungan({{ $r['id'] }})"
                        class="flex w-full items-center justify-between px-4 py-3 text-left hover:bg-blue-50">
                        <div>
                            <p class="text-sm font-semibold text-gray-800">{{ $r['pasien_nama'] }}</p>
                            <p class="text-xs text-gray-500">{{ $r['no_rm'] }} &bull; {{ $r['poli'] }} &bull; dr. {{ $r['dokter'] }}</p>
                        </div>
                        <div class="text-right">
                            <span class="inline-block rounded-full px-2 py-0.5 text-xs font-semibold
                                {{ $r['tipe'] === 'bpjs' ? 'bg-green-100 text-green-700' : 'bg-blue-100 text-blue-700' }}">
                                {{ strtoupper($r['tipe']) }}
                            </span>
                            @if ($r['invoice_status'])
                                <p class="mt-0.5 text-xs text-gray-400">{{ $r['invoice_status'] }}</p>
                            @endif
                        </div>
                    </button>
                @endforeach
            </div>
        @elseif (strlen($searchPasien) >= 2)
            <p class="mt-2 text-xs text-gray-500">Tidak ada kunjungan aktif hari ini untuk pencarian tersebut.</p>
        @endif
    </div>
    @endif

    {{-- Invoice View --}}
    @if ($kunjunganId && $this->kunjungan)
    <div class="space-y-5">

        {{-- Patient header --}}
        <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-gray-50 px-5 py-3">
            <div>
                <p class="text-base font-bold text-gray-800">{{ $this->kunjungan->pasien->nama }}</p>
                <p class="text-xs text-gray-500">
                    {{ $this->kunjungan->pasien->no_rm }}
                    &bull; {{ $this->kunjungan->poli->nama ?? '-' }}
                    &bull; dr. {{ $this->kunjungan->dokter->nama ?? '-' }}
                    &bull;
                    <span class="font-semibold {{ $this->kunjungan->tipe_pembayaran === 'bpjs' ? 'text-green-600' : 'text-blue-600' }}">
                        {{ strtoupper($this->kunjungan->tipe_pembayaran) }}
                    </span>
                </p>
            </div>
            <div class="flex gap-2">
                <button wire:click="fetchTagihan"
                    class="flex items-center gap-1.5 rounded-lg border border-blue-300 bg-white px-3 py-1.5 text-xs font-medium text-blue-600 hover:bg-blue-50">
                    <svg class="size-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                    </svg>
                    Refresh Tagihan
                </button>
                <button wire:click="resetPilihan"
                    class="rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-xs font-medium text-gray-600 hover:bg-gray-50">
                    Ganti Pasien
                </button>
            </div>
        </div>

        @if ($this->hasPendingResep)
        <div class="rounded-lg border border-orange-200 bg-orange-50 px-4 py-3 text-sm text-orange-700">
            <strong>Perhatian:</strong> Masih ada resep obat yang belum dikonfirmasi apoteker.
            Tagihan tidak dapat diproses sampai semua resep dikonfirmasi.
        </div>
        @endif

        @if ($this->invoice)
        {{-- Invoice items table --}}
        <div class="overflow-hidden rounded-xl border border-gray-200">
            <div class="flex items-center justify-between border-b border-gray-200 bg-white px-4 py-3">
                <p class="text-sm font-semibold text-gray-700">
                    Rincian Tagihan
                    <span class="ml-2 text-xs font-normal text-gray-400">{{ $this->invoice->nomor_invoice }}</span>
                </p>
                @if ($this->invoice->status === 'belum_bayar')
                <button wire:click="$set('showManualForm', true)"
                    class="flex items-center gap-1 rounded-lg bg-gray-100 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-200">
                    + Tambah Item Manual
                </button>
                @endif
            </div>

            @if ($showManualForm)
            <div class="border-b border-gray-200 bg-blue-50 px-4 py-3">
                <p class="mb-2 text-xs font-semibold text-blue-700">Item Tambahan Manual</p>
                <div class="flex flex-wrap items-end gap-2">
                    <div>
                        <label class="block text-xs text-gray-600">Nama Item *</label>
                        <input wire:model="manualNama" type="text"
                            class="mt-1 w-48 rounded-lg border-gray-300 text-sm shadow-sm"
                            placeholder="Biaya administrasi...">
                        @error('manualNama') <p class="text-xs text-red-500">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-xs text-gray-600">Qty *</label>
                        <input wire:model="manualQty" type="number" min="0.01" step="0.01"
                            class="mt-1 w-24 rounded-lg border-gray-300 text-sm shadow-sm" value="1">
                        @error('manualQty') <p class="text-xs text-red-500">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-xs text-gray-600">Satuan</label>
                        <input wire:model="manualSatuan" type="text"
                            class="mt-1 w-24 rounded-lg border-gray-300 text-sm shadow-sm" placeholder="buah">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-600">Harga Satuan *</label>
                        <input wire:model="manualHarga" type="number" min="0" step="1000"
                            class="mt-1 w-36 rounded-lg border-gray-300 text-sm shadow-sm" placeholder="0">
                        @error('manualHarga') <p class="text-xs text-red-500">{{ $message }}</p> @enderror
                    </div>
                    <button wire:click="addManualItem"
                        class="rounded-lg bg-blue-600 px-3 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                        Tambah
                    </button>
                    <button wire:click="$set('showManualForm', false)"
                        class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-600 hover:bg-gray-50">
                        Batal
                    </button>
                </div>
            </div>
            @endif

            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-xs font-semibold uppercase text-gray-500">
                    <tr>
                        <th class="px-4 py-2 text-left">Item</th>
                        <th class="px-3 py-2 text-center">Jenis</th>
                        <th class="px-3 py-2 text-right">Qty</th>
                        <th class="px-3 py-2 text-right">Harga Satuan</th>
                        <th class="px-3 py-2 text-right">Diskon</th>
                        <th class="px-3 py-2 text-right">Subtotal</th>
                        @if ($this->invoice->status === 'belum_bayar')
                        <th class="px-3 py-2"></th>
                        @endif
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($this->invoice->items as $item)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-2.5 text-gray-800">{{ $item->nama_item }}</td>
                        <td class="px-3 py-2.5 text-center">
                            @php
                                $jenisClass = match($item->jenis) {
                                    'tindakan' => 'bg-purple-100 text-purple-700',
                                    'alkes'    => 'bg-yellow-100 text-yellow-700',
                                    'penunjang'=> 'bg-cyan-100 text-cyan-700',
                                    'obat'     => 'bg-green-100 text-green-700',
                                    'racikan'  => 'bg-teal-100 text-teal-700',
                                    default    => 'bg-gray-100 text-gray-700',
                                };
                            @endphp
                            <span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $jenisClass }}">
                                {{ ucfirst($item->jenis) }}
                            </span>
                        </td>
                        <td class="px-3 py-2.5 text-right text-gray-600">{{ $item->qty }} {{ $item->satuan }}</td>
                        <td class="px-3 py-2.5 text-right text-gray-600">Rp {{ number_format($item->harga_satuan, 0, ',', '.') }}</td>
                        <td class="px-3 py-2.5 text-right">
                            @if ($this->invoice->status === 'belum_bayar' && $item->jenis !== 'obat')
                                <div class="flex items-center justify-end gap-1">
                                    <input wire:model="editDiskon.{{ $item->id }}"
                                        wire:change="updateDiskonItem({{ $item->id }})"
                                        type="number" min="0" step="1000"
                                        class="w-28 rounded border-gray-300 text-right text-xs shadow-sm">
                                </div>
                            @else
                                <span class="text-gray-600">{{ $item->diskon_item > 0 ? 'Rp ' . number_format($item->diskon_item, 0, ',', '.') : '-' }}</span>
                            @endif
                        </td>
                        <td class="px-3 py-2.5 text-right font-semibold text-gray-800">
                            Rp {{ number_format($item->subtotal, 0, ',', '.') }}
                        </td>
                        @if ($this->invoice->status === 'belum_bayar')
                        <td class="px-3 py-2.5">
                            @if ($item->jenis !== 'obat' && $item->jenis !== 'racikan')
                            <button wire:click="removeItem({{ $item->id }})"
                                wire:confirm="Hapus item ini dari tagihan?"
                                class="rounded p-1 text-red-400 hover:bg-red-50 hover:text-red-600">
                                <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                </svg>
                            </button>
                            @endif
                        </td>
                        @endif
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="px-4 py-8 text-center text-sm text-gray-400">
                            Belum ada item tagihan. Klik "Refresh Tagihan" untuk menarik data dari modul klinis.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>

            {{-- Totals --}}
            <div class="border-t border-gray-200 bg-gray-50 px-4 py-3">
                <div class="flex items-center justify-end gap-6">
                    {{-- Global discount --}}
                    @if ($this->invoice->status === 'belum_bayar')
                    <div class="flex items-center gap-2 text-sm">
                        <label class="text-gray-600">Diskon Global (Rp):</label>
                        <input wire:model="diskonGlobalNominal"
                            wire:change="applyDiskonGlobal"
                            type="number" min="0" step="1000"
                            class="w-36 rounded-lg border-gray-300 text-right text-sm shadow-sm">
                    </div>
                    @else
                    <div class="text-sm text-gray-600">
                        Diskon Global: <strong>Rp {{ number_format($this->invoice->diskon_global, 0, ',', '.') }}</strong>
                    </div>
                    @endif

                    <div class="text-right">
                        <p class="text-xs text-gray-500">Total Tagihan</p>
                        <p class="text-2xl font-bold text-gray-900">
                            Rp {{ number_format($this->invoice->total_tagihan, 0, ',', '.') }}
                        </p>
                        @if ($this->invoice->status !== 'belum_bayar')
                        <p class="text-xs text-gray-500">
                            Dibayar: Rp {{ number_format($this->invoice->total_bayar, 0, ',', '.') }}
                            &bull; Sisa: Rp {{ number_format($this->invoice->sisa, 0, ',', '.') }}
                        </p>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- Payment section --}}
        @if ($this->invoice->status === 'belum_bayar')
        <div class="rounded-xl border border-gray-200 bg-white">
            <div class="border-b border-gray-200 px-5 py-3">
                <p class="text-sm font-semibold text-gray-700">Proses Pembayaran</p>
            </div>
            <div class="px-5 py-4">

                {{-- Metode tabs --}}
                <div class="mb-4 flex gap-2">
                    @foreach (['tunai' => 'Tunai', 'non_tunai' => 'Non-Tunai', 'asuransi' => 'Asuransi / BPJS'] as $val => $label)
                    <button wire:click="$set('metodePembayaran', '{{ $val }}')"
                        class="rounded-lg px-4 py-2 text-sm font-medium
                            {{ $metodePembayaran === $val
                                ? 'bg-blue-600 text-white'
                                : 'border border-gray-300 bg-white text-gray-600 hover:bg-gray-50' }}">
                        {{ $label }}
                    </button>
                    @endforeach
                </div>

                {{-- Tunai --}}
                @if ($metodePembayaran === 'tunai')
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-gray-700">Jumlah Diterima (Rp) *</label>
                        <input wire:model.live="jumlahTunai" type="number" min="0" step="1000"
                            class="mt-1 w-full rounded-xl border-gray-300 text-lg font-bold shadow-sm focus:border-blue-500 focus:ring-blue-500"
                            placeholder="{{ $this->invoice->sisa }}">
                        @error('jumlahTunai') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div class="flex flex-col justify-end">
                        <p class="text-xs text-gray-500">Kembalian</p>
                        <p class="text-2xl font-bold {{ $this->kembalian >= 0 ? 'text-green-600' : 'text-red-500' }}">
                            Rp {{ number_format($this->kembalian, 0, ',', '.') }}
                        </p>
                    </div>
                </div>
                @endif

                {{-- Non-Tunai --}}
                @if ($metodePembayaran === 'non_tunai')
                <div class="grid grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-gray-700">Nama Bank *</label>
                        <input wire:model="bankNama" type="text"
                            class="mt-1 w-full rounded-xl border-gray-300 text-sm shadow-sm"
                            placeholder="BCA, Mandiri, BNI...">
                        @error('bankNama') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700">No. Kartu / Referensi *</label>
                        <input wire:model="nomorReferensi" type="text"
                            class="mt-1 w-full rounded-xl border-gray-300 text-sm shadow-sm"
                            placeholder="xxxx-xxxx-xxxx">
                        @error('nomorReferensi') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700">Tipe Kartu</label>
                        <select wire:model="tipeKartu"
                            class="mt-1 w-full rounded-xl border-gray-300 text-sm shadow-sm">
                            <option value="debit">Debit</option>
                            <option value="kredit">Kredit</option>
                            <option value="qris">QRIS</option>
                            <option value="transfer">Transfer</option>
                        </select>
                    </div>
                </div>
                @endif

                {{-- Asuransi --}}
                @if ($metodePembayaran === 'asuransi')
                <div class="space-y-3">
                    <div class="rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 text-xs text-blue-700">
                        Tagihan akan dicatat sebagai <strong>piutang asuransi</strong> dan tidak menghasilkan kas masuk hari ini.
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700">Nama Asuransi / Penjamin *</label>
                        <input wire:model="namaAsuransi" type="text"
                            class="mt-1 w-64 rounded-xl border-gray-300 text-sm shadow-sm"
                            placeholder="BPJS, Prudential...">
                        @error('namaAsuransi') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>
                @endif

                <div class="mt-4">
                    <label class="block text-xs font-medium text-gray-700">Catatan (opsional)</label>
                    <input wire:model="catatanBayar" type="text"
                        class="mt-1 w-full rounded-xl border-gray-300 text-sm shadow-sm"
                        placeholder="Catatan pembayaran">
                </div>

                <div class="mt-5 flex items-center gap-3">
                    <button wire:click="prosesPembayaran"
                        wire:confirm="Konfirmasi proses pembayaran untuk pasien {{ $this->kunjungan->pasien->nama }}?"
                        class="flex items-center gap-2 rounded-xl bg-green-600 px-6 py-2.5 text-sm font-bold text-white shadow hover:bg-green-700 focus:outline-none">
                        <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M5 13l4 4L19 7"/>
                        </svg>
                        Bayar — Rp {{ number_format($this->invoice->sisa, 0, ',', '.') }}
                    </button>
                </div>
            </div>
        </div>
        @elseif ($this->invoice->status === 'lunas')
        <div class="rounded-xl border border-green-200 bg-green-50 px-5 py-4">
            <p class="font-semibold text-green-800">Tagihan sudah lunas.</p>
            <p class="mt-1 text-sm text-green-700">
                Total dibayar: Rp {{ number_format($this->invoice->total_bayar, 0, ',', '.') }}
            </p>
            <button wire:click="resetPilihan" class="mt-3 rounded-lg bg-green-600 px-4 py-2 text-sm font-semibold text-white hover:bg-green-700">
                Pasien Berikutnya
            </button>
        </div>
        @endif

        @endif {{-- end if invoice --}}
    </div>
    @endif {{-- end if kunjunganId --}}

    @endif {{-- end if activeShift --}}
</div>
