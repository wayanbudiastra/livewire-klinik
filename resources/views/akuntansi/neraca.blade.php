<x-app-layout>
    <x-slot name="title">Neraca</x-slot>

    <div class="page-header">
        <div>
            <h2 class="page-title">Neraca (Balance Sheet)</h2>
            <p class="page-subtitle">Posisi keuangan klinik: Aset = Liabilitas + Ekuitas</p>
        </div>
    </div>

    <livewire:akuntansi.neraca-report />

    @include('akuntansi.partials.toast')
</x-app-layout>
