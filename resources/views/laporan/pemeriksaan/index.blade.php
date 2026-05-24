<x-app-layout>
    <x-slot name="title">Laporan Pemeriksaan</x-slot>

    <div class="page-header">
        <div>
            <h1 class="page-title">Laporan Pemeriksaan</h1>
            <p class="page-subtitle">Diagnosa, Tindakan, Rekap per Poli, dan Rekap per Dokter</p>
        </div>
    </div>

    <div class="page-content" x-data="{ tab: '{{ $tab ?? 'diagnosa' }}' }">

        <div class="mb-6 flex flex-wrap gap-1 rounded-xl border border-gray-200 bg-gray-100 p-1" style="width: fit-content">
            @foreach([
                'diagnosa'  => 'Rekap Diagnosa',
                'tindakan'  => 'Rekap Tindakan',
                'poli'      => 'Rekap per Poli',
                'dokter'    => 'Rekap per Dokter',
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

        <div x-show="tab === 'diagnosa'">
            <livewire:laporan.pemeriksaan.rekap-diagnosa-report />
        </div>
        <div x-show="tab === 'tindakan'" x-cloak>
            <livewire:laporan.pemeriksaan.rekap-tindakan-report />
        </div>
        <div x-show="tab === 'poli'" x-cloak>
            <livewire:laporan.pemeriksaan.rekap-poli-report />
        </div>
        <div x-show="tab === 'dokter'" x-cloak>
            <livewire:laporan.pemeriksaan.rekap-dokter-report />
        </div>

    </div>
</x-app-layout>
