<x-app-layout>
    <x-slot name="title">Jurnal Manual</x-slot>

    <div class="page-header">
        <div>
            <h2 class="page-title">Jurnal Manual</h2>
            <p class="page-subtitle">Riwayat input jurnal manual untuk biaya operasional & mutasi modal non-sistem</p>
        </div>
    </div>

    <livewire:akuntansi.jurnal-manual-table />

    @include('akuntansi.partials.toast')
</x-app-layout>
