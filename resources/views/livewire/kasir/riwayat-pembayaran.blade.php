<div class="space-y-5">

    {{-- Filter bar --}}
    <div class="flex flex-wrap items-end gap-3">
        <div>
            <label class="block text-xs font-medium text-gray-600">Tanggal</label>
            <input wire:model.live="filterTanggal" type="date"
                class="mt-1 rounded-lg border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-600">Cari Pasien</label>
            <input wire:model.live.debounce.400ms="searchNama" type="text"
                class="mt-1 w-56 rounded-lg border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                placeholder="Nama atau No. RM...">
        </div>
    </div>

    {{-- Summary cards --}}
    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
        <div class="rounded-xl border border-gray-200 bg-white px-4 py-3 text-center shadow-sm">
            <p class="text-xs text-gray-500">Transaksi</p>
            <p class="mt-1 text-2xl font-bold text-gray-800">{{ $this->totalHariIni['count'] }}</p>
        </div>
        <div class="rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-center">
            <p class="text-xs text-green-600">Tunai</p>
            <p class="mt-1 text-base font-bold text-green-700">Rp {{ number_format($this->totalHariIni['tunai'], 0, ',', '.') }}</p>
        </div>
        <div class="rounded-xl border border-blue-200 bg-blue-50 px-4 py-3 text-center">
            <p class="text-xs text-blue-600">Non-Tunai</p>
            <p class="mt-1 text-base font-bold text-blue-700">Rp {{ number_format($this->totalHariIni['non_tunai'], 0, ',', '.') }}</p>
        </div>
        <div class="rounded-xl border border-orange-200 bg-orange-50 px-4 py-3 text-center">
            <p class="text-xs text-orange-600">Asuransi</p>
            <p class="mt-1 text-base font-bold text-orange-700">Rp {{ number_format($this->totalHariIni['asuransi'], 0, ',', '.') }}</p>
        </div>
    </div>

    {{-- Transactions table --}}
    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-xs font-semibold uppercase text-gray-500">
                <tr>
                    <th class="px-4 py-3 text-left">No. Invoice</th>
                    <th class="px-4 py-3 text-left">Pasien</th>
                    <th class="px-3 py-3 text-left">Poli / Dokter</th>
                    <th class="px-3 py-3 text-center">Penjamin</th>
                    <th class="px-3 py-3 text-center">Metode</th>
                    <th class="px-3 py-3 text-right">Total</th>
                    <th class="px-3 py-3 text-center">Waktu</th>
                    <th class="px-3 py-3 text-center">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($this->invoices as $inv)
                @php
                    $metodes = $inv->pembayaran->pluck('metode')->unique()->values();
                    $metodeLabel = $metodes->map(fn ($m) => match($m) {
                        'tunai'     => 'Tunai',
                        'non_tunai' => 'Non-Tunai',
                        'asuransi'  => 'Asuransi',
                        default     => ucfirst($m),
                    })->implode(', ');
                @endphp
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3">
                        <span class="font-mono text-xs text-gray-700">{{ $inv->nomor_invoice }}</span>
                    </td>
                    <td class="px-4 py-3">
                        <p class="font-semibold text-gray-800">{{ $inv->kunjungan->pasien->nama }}</p>
                        <p class="text-xs text-gray-500">{{ $inv->kunjungan->pasien->nomor_rm }}</p>
                    </td>
                    <td class="px-3 py-3 text-xs text-gray-600">
                        <p>{{ $inv->kunjungan->poli->nama ?? '-' }}</p>
                        <p class="text-gray-400">dr. {{ $inv->kunjungan->dokter->nama ?? '-' }}</p>
                    </td>
                    <td class="px-3 py-3 text-center">
                        <span class="rounded-full px-2 py-0.5 text-xs font-semibold
                            {{ $inv->kunjungan->tipe_pembayaran === 'bpjs' ? 'bg-emerald-100 text-emerald-700' : 'bg-indigo-100 text-indigo-700' }}">
                            {{ strtoupper($inv->kunjungan->tipe_pembayaran ?? 'umum') }}
                        </span>
                    </td>
                    <td class="px-3 py-3 text-center">
                        <span class="rounded-full px-2 py-0.5 text-xs font-medium bg-gray-100 text-gray-700">
                            {{ $metodeLabel ?: '-' }}
                        </span>
                    </td>
                    <td class="px-3 py-3 text-right font-semibold text-gray-800">
                        Rp {{ number_format($inv->total_tagihan, 0, ',', '.') }}
                    </td>
                    <td class="px-3 py-3 text-center text-xs text-gray-500">
                        {{ $inv->updated_at->format('H:i') }}
                    </td>
                    <td class="px-3 py-3 text-center">
                        <a href="{{ route('invoice.print', $inv->id) }}" target="_blank"
                            class="inline-flex items-center gap-1 rounded-lg bg-blue-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-blue-700">
                            <svg class="size-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                            </svg>
                            Cetak
                        </a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" class="px-4 py-10 text-center text-sm text-gray-400">
                        Belum ada transaksi lunas
                        @if ($filterTanggal) pada tanggal {{ \Carbon\Carbon::parse($filterTanggal)->format('d/m/Y') }} @endif.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>

        @if ($this->invoices->hasPages())
        <div class="border-t border-gray-200 px-4 py-3">
            {{ $this->invoices->links() }}
        </div>
        @endif
    </div>

</div>
