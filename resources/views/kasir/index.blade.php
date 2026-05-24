<x-app-layout>
    <x-slot name="title">Billing & Kasir</x-slot>

    <div class="page-header">
        <div>
            <h1 class="page-title">Billing & Kasir</h1>
            <p class="page-subtitle">Manajemen shift, tagihan pasien, deposit, dan laporan kasir</p>
        </div>
    </div>

    <div class="page-content" x-data="{ tab: '{{ request()->query('tab', 'tagihan') }}' }">

        {{-- Tab navigation --}}
        <div class="mb-6 flex flex-wrap gap-1 rounded-xl border border-gray-200 bg-gray-100 p-1" style="width: fit-content">
            @foreach ([
                'tagihan'  => 'Tagihan Pasien',
                'riwayat'  => 'Riwayat Pembayaran',
                'deposit'  => 'Deposit Pasien',
                'sesi-kas' => 'Sesi Kas',
                'shift'    => 'Kelola Shift',
                'laporan'  => 'Laporan Shift',
            ] as $key => $label)
            <button @click="tab = '{{ $key }}'"
                :class="tab === '{{ $key }}'
                    ? 'bg-white text-blue-700 shadow-sm font-semibold'
                    : 'text-gray-500 hover:text-gray-700'"
                class="rounded-lg px-5 py-2 text-sm transition">
                {{ $label }}
            </button>
            @endforeach
        </div>

        {{-- Tagihan Pasien --}}
        <div x-show="tab === 'tagihan'">
            <livewire:kasir.tagihan-pasien />
        </div>

        {{-- Riwayat Pembayaran --}}
        <div x-show="tab === 'riwayat'" x-cloak>
            <livewire:kasir.riwayat-pembayaran />
        </div>

        {{-- Deposit Pasien --}}
        <div x-show="tab === 'deposit'" x-cloak>
            <livewire:kasir.deposit.topup-deposit-form />
        </div>

        {{-- Sesi Kas (v2) --}}
        <div x-show="tab === 'sesi-kas'" x-cloak>
            <livewire:kasir.sesi-kas.sesi-kas-panel />
        </div>

        {{-- Kelola Shift --}}
        <div x-show="tab === 'shift'" x-cloak>
            <livewire:kasir.kelola-shift />
        </div>

        {{-- Laporan Shift --}}
        <div x-show="tab === 'laporan'" x-cloak>
            <livewire:kasir.laporan-shift />
        </div>

    </div>
</x-app-layout>
