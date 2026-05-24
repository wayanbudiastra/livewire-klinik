<x-app-layout>
    <x-slot name="title">Laporan Kasir</x-slot>

    <div class="page-header">
        <div>
            <h1 class="page-title">Laporan Kasir</h1>
            <p class="page-subtitle">Transaksi Kasir, Cancel Bill, dan Deposit Pasien</p>
        </div>
    </div>

    <div class="page-content" x-data="{ tab: '{{ $tab ?? 'transaksi' }}' }">

        <div class="mb-6 flex flex-wrap gap-1 rounded-xl border border-gray-200 bg-gray-100 p-1" style="width: fit-content">
            @foreach([
                'transaksi'   => 'Transaksi Kasir',
                'cancel-bill' => 'Cancel Bill',
                'deposit'     => 'Deposit Pasien',
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

        <div x-show="tab === 'transaksi'">
            <livewire:laporan.kasir.transaksi-kasir-report />
        </div>
        <div x-show="tab === 'cancel-bill'" x-cloak>
            <livewire:laporan.kasir.cancel-bill-report />
        </div>
        <div x-show="tab === 'deposit'" x-cloak>
            <livewire:laporan.kasir.deposit-report />
        </div>

    </div>
</x-app-layout>
