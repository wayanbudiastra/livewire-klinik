<x-app-layout>
    <x-slot name="title">Laporan Registrasi</x-slot>

    <div class="page-header">
        <div>
            <h1 class="page-title">Laporan Registrasi</h1>
            <p class="page-subtitle">Kunjungan, Batal Registrasi, Appointment, dan Rekap Warga Negara</p>
        </div>
    </div>

    <div class="page-content" x-data="{ tab: '{{ $tab ?? 'kunjungan' }}' }">

        <div class="mb-6 flex flex-wrap gap-1 rounded-xl border border-gray-200 bg-gray-100 p-1" style="width: fit-content">
            @foreach([
                'kunjungan'    => 'Kunjungan Pasien',
                'batal'        => 'Batal Registrasi',
                'appointment'  => 'Appointment',
                'warga-negara' => 'Rekap WNA',
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

        <div x-show="tab === 'kunjungan'">
            <livewire:laporan.registrasi.kunjungan-pasien-report />
        </div>
        <div x-show="tab === 'batal'" x-cloak>
            <livewire:laporan.registrasi.batal-registrasi-report />
        </div>
        <div x-show="tab === 'appointment'" x-cloak>
            <livewire:laporan.registrasi.appointment-report />
        </div>
        <div x-show="tab === 'warga-negara'" x-cloak>
            <livewire:laporan.registrasi.rekap-warga-negara-report />
        </div>

    </div>
</x-app-layout>
