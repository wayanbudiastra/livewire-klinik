<div class="grid gap-6 lg:grid-cols-3">

    {{-- Sidebar: riwayat shift --}}
    <div class="lg:col-span-1">
        <h3 class="mb-3 text-sm font-semibold text-gray-700">Riwayat Shift</h3>
        <div class="space-y-2">
            @forelse ($this->riwayatShift as $s)
            <button wire:click="selectShift({{ $s['id'] }})"
                class="w-full rounded-xl border px-4 py-3 text-left text-sm transition
                    {{ $shiftId == $s['id']
                        ? 'border-blue-400 bg-blue-50'
                        : 'border-gray-200 bg-white hover:bg-gray-50' }}">
                <div class="flex items-center justify-between">
                    <span class="font-medium text-gray-800">
                        {{ \Carbon\Carbon::parse($s['opened_at'])->format('d/m/Y H:i') }}
                    </span>
                    <span class="rounded-full px-2 py-0.5 text-xs font-semibold
                        {{ $s['status'] === 'open'
                            ? 'bg-green-100 text-green-700'
                            : 'bg-gray-100 text-gray-500' }}">
                        {{ strtoupper($s['status']) }}
                    </span>
                </div>
                <p class="mt-0.5 text-xs text-gray-500">
                    Tunai: Rp {{ number_format($s['total_tunai'], 0, ',', '.') }}
                </p>
            </button>
            @empty
            <p class="text-sm text-gray-400">Belum ada riwayat shift.</p>
            @endforelse
        </div>
    </div>

    {{-- Main: laporan detail --}}
    <div class="lg:col-span-2">
        @if ($this->shift)
        <div class="space-y-4">

            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-base font-bold text-gray-800">Laporan Shift</h3>
                    <p class="text-xs text-gray-500">
                        {{ $this->shift->opened_at->format('d/m/Y H:i') }}
                        @if ($this->shift->closed_at)
                            &mdash; {{ $this->shift->closed_at->format('d/m/Y H:i') }}
                        @else
                            &mdash; <span class="text-green-600 font-semibold">Sedang Berjalan</span>
                        @endif
                    </p>
                </div>
                <span class="rounded-full px-3 py-1 text-xs font-semibold
                    {{ $this->shift->status === 'open' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600' }}">
                    {{ strtoupper($this->shift->status) }}
                </span>
            </div>

            {{-- Summary cards --}}
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                <div class="rounded-xl border border-gray-200 bg-white px-4 py-3 text-center">
                    <p class="text-xs text-gray-500">Modal Awal</p>
                    <p class="mt-1 text-lg font-bold text-gray-800">Rp {{ number_format($this->shift->modal_awal, 0, ',', '.') }}</p>
                </div>
                <div class="rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-center">
                    <p class="text-xs text-green-600">Total Tunai</p>
                    <p class="mt-1 text-lg font-bold text-green-700">Rp {{ number_format($this->shift->total_tunai, 0, ',', '.') }}</p>
                </div>
                <div class="rounded-xl border border-blue-200 bg-blue-50 px-4 py-3 text-center">
                    <p class="text-xs text-blue-600">Non-Tunai</p>
                    <p class="mt-1 text-lg font-bold text-blue-700">Rp {{ number_format($this->shift->total_nontunai, 0, ',', '.') }}</p>
                </div>
                <div class="rounded-xl border border-orange-200 bg-orange-50 px-4 py-3 text-center">
                    <p class="text-xs text-orange-600">Piutang</p>
                    <p class="mt-1 text-lg font-bold text-orange-700">Rp {{ number_format($this->shift->total_piutang, 0, ',', '.') }}</p>
                </div>
            </div>

            {{-- Selisih kas (only for closed shifts) --}}
            @if ($this->shift->status === 'closed')
            <div class="rounded-xl border {{ $this->shift->selisih >= 0 ? 'border-green-200 bg-green-50' : 'border-red-200 bg-red-50' }} px-5 py-4">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-semibold {{ $this->shift->selisih >= 0 ? 'text-green-700' : 'text-red-700' }}">
                            Selisih Kas
                        </p>
                        <p class="mt-1 text-xl font-bold {{ $this->shift->selisih >= 0 ? 'text-green-800' : 'text-red-800' }}">
                            {{ $this->shift->selisih >= 0 ? '+' : '' }}Rp {{ number_format($this->shift->selisih, 0, ',', '.') }}
                        </p>
                    </div>
                    <div class="text-right text-xs text-gray-500">
                        <p>Uang Sistem: Rp {{ number_format($this->shift->modal_awal + $this->shift->total_tunai, 0, ',', '.') }}</p>
                        <p>Uang Fisik: Rp {{ number_format($this->shift->uang_fisik_akhir, 0, ',', '.') }}</p>
                    </div>
                </div>
            </div>
            @endif

            {{-- Non-tunai breakdown --}}
            @if (count($this->rincianNonTunai))
            <div class="overflow-hidden rounded-xl border border-gray-200 bg-white">
                <div class="border-b border-gray-200 px-4 py-2.5">
                    <p class="text-xs font-semibold text-gray-600">Rincian Non-Tunai per Bank</p>
                </div>
                <table class="w-full text-sm">
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($this->rincianNonTunai as $row)
                        <tr>
                            <td class="px-4 py-2.5 text-gray-700">{{ $row['bank_nama'] ?? '-' }}</td>
                            <td class="px-4 py-2.5 text-right font-semibold text-gray-800">
                                Rp {{ number_format($row['total'], 0, ',', '.') }}
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @endif

            {{-- Piutang asuransi breakdown --}}
            @if (count($this->rincianAsuransi))
            <div class="overflow-hidden rounded-xl border border-gray-200 bg-white">
                <div class="border-b border-gray-200 px-4 py-2.5">
                    <p class="text-xs font-semibold text-gray-600">Rincian Piutang per Asuransi</p>
                </div>
                <table class="w-full text-sm">
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($this->rincianAsuransi as $row)
                        <tr>
                            <td class="px-4 py-2.5 text-gray-700">{{ $row['nama_asuransi'] ?? '-' }}</td>
                            <td class="px-4 py-2.5 text-right font-semibold text-gray-800">
                                Rp {{ number_format($row['total'], 0, ',', '.') }}
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @endif

            @if ($this->shift->catatan)
            <div class="rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-600">
                <strong>Catatan:</strong> {{ $this->shift->catatan }}
            </div>
            @endif

        </div>
        @else
        <div class="rounded-xl border border-gray-200 bg-gray-50 px-5 py-10 text-center text-sm text-gray-400">
            Pilih shift dari daftar di sebelah kiri untuk melihat laporan.
        </div>
        @endif
    </div>

</div>
