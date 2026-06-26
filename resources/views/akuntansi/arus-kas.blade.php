<x-app-layout>
    <x-slot name="title">Laporan Arus Kas</x-slot>

    <div class="page-header">
        <div>
            <h2 class="page-title">Laporan Arus Kas (Cash Flow Statement)</h2>
            <p class="page-subtitle">Pergerakan kas & bank per aktivitas Operasi/Investasi/Pendanaan — metode langsung</p>
        </div>
    </div>

    <livewire:akuntansi.arus-kas-report />

    @include('akuntansi.partials.toast')
</x-app-layout>
