<x-app-layout>
    <x-slot name="title">Laporan Pharmacy</x-slot>

    <div class="page-header">
        <div>
            <h1 class="page-title">Laporan Pharmacy</h1>
            <p class="page-subtitle">Rekap Resep, Obat Fast Moving, dan Nilai Inventory</p>
        </div>
    </div>

    <div class="page-content" x-data="{ tab: '{{ $tab ?? 'resep' }}' }">

        <div class="mb-6 flex flex-wrap gap-1 rounded-xl border border-gray-200 bg-gray-100 p-1" style="width: fit-content">
            @foreach([
                'resep'           => 'Rekap Resep',
                'fast-moving'     => 'Obat Fast Moving',
                'nilai-inventory' => 'Nilai Inventory',
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

        <div x-show="tab === 'resep'">
            <livewire:laporan.pharmacy.rekap-resep-report />
        </div>
        <div x-show="tab === 'fast-moving'" x-cloak>
            <livewire:laporan.pharmacy.obat-fast-moving-report />
        </div>
        <div x-show="tab === 'nilai-inventory'" x-cloak>
            <livewire:laporan.pharmacy.nilai-inventory-report />
        </div>

    </div>
</x-app-layout>
