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

    {{-- Guard: kas harus aktif --}}
    @if (! $this->activeSesi)
        <div class="rounded-xl border border-yellow-200 bg-yellow-50 px-5 py-4 text-sm text-yellow-800">
            <strong>Kas belum dibuka.</strong> Buka kas terlebih dahulu di tab "Sesi Kas" untuk memproses transaksi.
        </div>
    @else

    {{-- Search & Daftar Pasien --}}
    @if (! $kunjunganId)
    <div class="mb-6">
        {{-- Search box --}}
        <div class="relative mb-3">
            <input wire:model.live.debounce.400ms="searchPasien"
                type="text"
                class="w-full rounded-xl border-gray-300 py-2.5 pl-4 pr-10 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                placeholder="Cari nama atau No. RM pasien...">
            <svg class="absolute right-3 top-2.5 size-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M21 21l-4.35-4.35M11 18a7 7 0 110-14 7 7 0 010 14z"/>
            </svg>
        </div>

        @php
            $listPasien = strlen($searchPasien) >= 2 ? $searchResults : $this->daftarHariIni;
            $listTitle  = strlen($searchPasien) >= 2 ? 'Hasil Pencarian' : 'Kunjungan Hari Ini';
        @endphp

        @if (count($listPasien))
            <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                <div class="border-b border-gray-100 bg-gray-50 px-4 py-2">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ $listTitle }}</p>
                </div>
                <div class="divide-y divide-gray-100">
                    @foreach ($listPasien as $r)
                        @php
                            $sBadge = match($r['status']) {
                                'selesai'           => 'bg-green-100 text-green-700',
                                'dalam_pemeriksaan' => 'bg-blue-100 text-blue-700',
                                default             => 'bg-yellow-100 text-yellow-700',
                            };
                            $sLabel = match($r['status']) {
                                'selesai'           => 'Selesai',
                                'dalam_pemeriksaan' => 'Diperiksa',
                                default             => 'Menunggu',
                            };
                        @endphp
                        <button wire:click="selectKunjungan({{ $r['id'] }})"
                            class="flex w-full items-center justify-between px-4 py-3 text-left hover:bg-blue-50">
                            <div>
                                <p class="text-sm font-semibold text-gray-800">{{ $r['pasien_nama'] }}</p>
                                <p class="text-xs text-gray-500">
                                    {{ $r['no_rm'] }} &bull; {{ $r['tanggal'] }} &bull; {{ $r['poli'] }} &bull; dr. {{ $r['dokter'] }}
                                </p>
                            </div>
                            <div class="flex shrink-0 items-center gap-2">
                                <span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ $sBadge }}">
                                    {{ $sLabel }}
                                </span>
                                <span class="rounded-full px-2 py-0.5 text-xs font-semibold
                                    {{ ($r['tipe'] ?? '') === 'bpjs' ? 'bg-emerald-100 text-emerald-700' : 'bg-indigo-100 text-indigo-700' }}">
                                    {{ strtoupper($r['tipe'] ?? 'umum') }}
                                </span>
                            </div>
                        </button>
                    @endforeach
                </div>
            </div>
        @elseif (strlen($searchPasien) >= 2)
            <p class="mt-2 text-xs text-gray-500">Tidak ada pasien yang sesuai dengan pencarian.</p>
        @else
            <div class="rounded-xl border border-gray-200 bg-gray-50 px-5 py-8 text-center text-sm text-gray-400">
                Belum ada kunjungan hari ini.
            </div>
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
                    {{ $this->kunjungan->pasien->nomor_rm }}
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

        @if ($this->kunjungan->status !== 'selesai')
        <div class="rounded-lg border border-yellow-200 bg-yellow-50 px-4 py-3 text-sm text-yellow-800">
            <strong>Pemeriksaan belum selesai.</strong>
            Status saat ini: <span class="font-semibold">{{ ucfirst(str_replace('_', ' ', $this->kunjungan->status)) }}</span>.
            Tagihan dapat diproses setelah dokter menyelesaikan pemeriksaan.
        </div>
        @elseif ($this->hasPendingResep)
        <div class="rounded-lg border border-orange-200 bg-orange-50 px-4 py-3 text-sm text-orange-700">
            <strong>Resep belum dikonfirmasi apoteker.</strong>
            Selesaikan konfirmasi resep di modul Farmasi sebelum proses pembayaran.
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
                <div class="flex items-center gap-2">
                    <button wire:click="$set('showKomponenForm', true)"
                        class="flex items-center gap-1 rounded-lg bg-indigo-100 px-3 py-1.5 text-xs font-medium text-indigo-700 hover:bg-indigo-200">
                        <svg class="size-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/>
                        </svg>
                        Tambah Komponen
                    </button>
                    <button wire:click="$set('showManualForm', true)"
                        class="flex items-center gap-1 rounded-lg bg-gray-100 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-200">
                        + Tambah Item Manual
                    </button>
                </div>
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
            <div class="flex items-center justify-between border-b border-gray-200 px-5 py-3">
                <p class="text-sm font-semibold text-gray-700">Proses Pembayaran</p>
                <a href="{{ route('kasir.billing.split-payment', $this->invoice) }}"
                   class="flex items-center gap-1.5 rounded-lg border border-purple-300 bg-purple-50 px-3 py-1.5 text-xs font-medium text-purple-700 hover:bg-purple-100">
                    <svg class="size-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>
                    </svg>
                    Split / Deposit
                </a>
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
                        @disabled($this->hasPendingResep)
                        class="flex items-center gap-2 rounded-xl bg-green-600 px-6 py-2.5 text-sm font-bold text-white shadow hover:bg-green-700 focus:outline-none disabled:cursor-not-allowed disabled:opacity-50">
                        <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M5 13l4 4L19 7"/>
                        </svg>
                        Bayar — Rp {{ number_format($this->invoice->sisa, 0, ',', '.') }}
                    </button>
                </div>
            </div>
        </div>
        @elseif ($this->invoice->status === 'dibatalkan')
        <div class="rounded-xl border border-red-200 bg-red-50 px-5 py-4">
            <p class="text-sm font-semibold text-red-700">Invoice ini telah dibatalkan.</p>
            <p class="mt-1 text-xs text-red-600">Klik tombol di bawah untuk menerbitkan invoice baru dengan nomor baru.</p>
            <button wire:click="fetchTagihan"
                class="mt-3 rounded-lg bg-red-600 px-4 py-2 text-xs font-semibold text-white hover:bg-red-700">
                Buat Invoice Baru
            </button>
        </div>
        @elseif ($this->invoice->status === 'lunas')
        <div class="rounded-xl border border-green-200 bg-green-50 px-5 py-5">
            <div class="flex items-center justify-between">
                <div>
                    <p class="font-bold text-green-800">&#10003; Tagihan Lunas</p>
                    <p class="mt-1 text-sm text-green-700">
                        {{ $this->invoice->nomor_invoice }} &bull;
                        Total: <strong>Rp {{ number_format($this->invoice->total_tagihan, 0, ',', '.') }}</strong>
                    </p>
                </div>
                <div class="flex gap-2">
                    <a href="{{ route('invoice.print', $this->invoice->id) }}" target="_blank"
                        class="flex items-center gap-1.5 rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                        <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                        </svg>
                        Cetak Invoice
                    </a>
                    <button wire:click="resetPilihan"
                        class="rounded-lg border border-green-300 bg-white px-4 py-2 text-sm font-semibold text-green-700 hover:bg-green-50">
                        Pasien Berikutnya
                    </button>
                </div>
            </div>
        </div>
        @endif

        @endif {{-- end if invoice --}}
    </div>
    @endif {{-- end if kunjunganId --}}

    @endif {{-- end if activeSesi --}}

    {{-- ══ PANEL TAMBAH KOMPONEN ══ --}}
    @if($showKomponenForm)
    <div class="fixed inset-0 z-50 flex">
        {{-- Backdrop --}}
        <div class="fixed inset-0 bg-gray-900/50 backdrop-blur-sm"
             wire:click="$set('showKomponenForm', false)"></div>

        {{-- Side panel (slide in from right) --}}
        <div class="relative ml-auto flex h-full w-full max-w-lg flex-col bg-white shadow-2xl">

            {{-- Header --}}
            <div class="flex items-center justify-between border-b border-gray-200 px-5 py-4">
                <div>
                    <h3 class="text-base font-semibold text-gray-900">Tambah Komponen Tagihan</h3>
                    <p class="text-xs text-gray-500 mt-0.5">Pilih dari master data klinik</p>
                </div>
                <button wire:click="$set('showKomponenForm', false)"
                    class="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600">
                    <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            {{-- Tab navigation --}}
            <div class="flex border-b border-gray-200 bg-gray-50">
                @foreach([
                    'prosedur'  => ['label' => 'Prosedur',     'color' => 'purple', 'icon' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01'],
                    'peralatan' => ['label' => 'Peralatan',    'color' => 'amber',  'icon' => 'M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z'],
                    'lab'       => ['label' => 'Laboratorium', 'color' => 'cyan',   'icon' => 'M9 3H5a2 2 0 00-2 2v4m6-6h10a2 2 0 012 2v4M9 3v18m0 0h10a2 2 0 002-2V9M9 21H5a2 2 0 01-2-2V9m0 0h18'],
                    'radiologi' => ['label' => 'Radiologi',    'color' => 'rose',   'icon' => 'M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z'],
                ] as $tabKey => $tabCfg)
                @php
                    $isActive = $komponenTab === $tabKey;
                    $activeClass = match($tabCfg['color']) {
                        'purple' => 'border-purple-600 text-purple-700 bg-white',
                        'amber'  => 'border-amber-500 text-amber-700 bg-white',
                        'cyan'   => 'border-cyan-600 text-cyan-700 bg-white',
                        'rose'   => 'border-rose-600 text-rose-700 bg-white',
                        default  => 'border-blue-600 text-blue-700 bg-white',
                    };
                @endphp
                <button wire:click="switchKomponenTab('{{ $tabKey }}')"
                    class="flex flex-1 flex-col items-center gap-1 border-b-2 px-2 py-2.5 text-xs font-medium transition-colors
                           {{ $isActive ? $activeClass : 'border-transparent text-gray-500 hover:text-gray-700' }}">
                    <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="{{ $tabCfg['icon'] }}"/>
                    </svg>
                    {{ $tabCfg['label'] }}
                </button>
                @endforeach
            </div>

            {{-- Search box --}}
            <div class="border-b border-gray-100 px-4 py-3">
                <div class="relative">
                    <span class="absolute inset-y-0 left-3 flex items-center text-gray-400 pointer-events-none">
                        <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M21 21l-4.35-4.35M17 11A6 6 0 1 1 5 11a6 6 0 0 1 12 0z"/>
                        </svg>
                    </span>
                    <input wire:model.live.debounce.400ms="searchKomponen"
                        type="text"
                        placeholder="Cari nama atau kode..."
                        class="w-full rounded-lg border-gray-300 pl-9 pr-3 py-2 text-sm shadow-sm focus:border-indigo-400 focus:ring-indigo-400"/>
                    <div class="absolute inset-y-0 right-3 flex items-center pointer-events-none">
                        <svg wire:loading wire:target="searchKomponen,switchKomponenTab"
                            class="animate-spin size-4 text-gray-400" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
                        </svg>
                    </div>
                </div>
                @if(strlen($searchKomponen) < 2 && strlen($searchKomponen) > 0)
                <p class="mt-1 text-xs text-gray-400">Ketik minimal 2 karakter untuk mencari, atau kosongkan untuk lihat semua.</p>
                @endif
            </div>

            {{-- Item list --}}
            <div class="flex-1 overflow-y-auto divide-y divide-gray-100">
                @forelse($this->komponenList as $item)
                <div x-data="{ qty: 1 }"
                     class="flex items-center gap-3 px-4 py-3 hover:bg-indigo-50/40 transition-colors">
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-gray-800 truncate">{{ $item['nama'] }}</p>
                        <div class="flex items-center gap-2 mt-0.5">
                            <span class="text-xs font-semibold text-indigo-700">
                                Rp {{ number_format($item['harga'], 0, ',', '.') }}
                            </span>
                            <span class="text-xs text-gray-400">/ {{ $item['satuan'] }}</span>
                            @if($item['info'])
                            <span class="text-xs text-gray-400">&bull; {{ $item['info'] }}</span>
                            @endif
                        </div>
                    </div>
                    <div class="flex items-center gap-2 flex-shrink-0">
                        <div class="flex items-center rounded-lg border border-gray-300 bg-white overflow-hidden">
                            <button @click="qty = Math.max(1, qty - 1)"
                                class="px-2 py-1 text-gray-500 hover:bg-gray-100 text-sm leading-none">−</button>
                            <input x-model.number="qty" type="number" min="1" max="999"
                                class="w-12 border-0 border-x border-gray-300 py-1 text-center text-sm focus:ring-0"/>
                            <button @click="qty = Math.min(999, qty + 1)"
                                class="px-2 py-1 text-gray-500 hover:bg-gray-100 text-sm leading-none">+</button>
                        </div>
                        <button
                            x-on:click="$wire.addKomponenItem({{ $item['id'] }}, @js($item['nama']), {{ $item['harga'] }}, @js($item['satuan']), qty)"
                            class="flex items-center gap-1 rounded-lg bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-indigo-700 transition-colors">
                            <svg class="size-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                            </svg>
                            Tambah
                        </button>
                    </div>
                </div>
                @empty
                <div class="flex flex-col items-center justify-center py-16 text-center">
                    <svg class="size-12 text-gray-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                            d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <p class="text-sm text-gray-400">
                        @if(strlen($searchKomponen) >= 2)
                            Tidak ada {{ match($komponenTab) { 'prosedur' => 'prosedur', 'peralatan' => 'peralatan', 'lab' => 'item laboratorium', 'radiologi' => 'item radiologi', default => 'item' } }}
                            yang cocok dengan "{{ $searchKomponen }}"
                        @else
                            Belum ada data {{ match($komponenTab) { 'prosedur' => 'prosedur', 'peralatan' => 'peralatan/BMHP', 'lab' => 'laboratorium', 'radiologi' => 'radiologi', default => '' } }} aktif.
                        @endif
                    </p>
                </div>
                @endforelse
            </div>

            {{-- Footer --}}
            <div class="border-t border-gray-200 bg-gray-50 px-5 py-3 flex items-center justify-between">
                <p class="text-xs text-gray-400">
                    {{ count($this->komponenList) }} item ditampilkan
                </p>
                <button wire:click="$set('showKomponenForm', false)"
                    class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50">
                    Tutup
                </button>
            </div>
        </div>
    </div>
    @endif

</div>
