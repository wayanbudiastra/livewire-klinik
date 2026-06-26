<x-app-layout>
    <x-slot name="title">Jurnal Pending</x-slot>

    <div class="page-header">
        <div>
            <h2 class="page-title">Jurnal Pending</h2>
            <p class="page-subtitle">Review draft jurnal otomatis sebelum diposting ke buku besar</p>
        </div>
    </div>

    <livewire:akuntansi.jurnal-pending-table />

    @include('akuntansi.partials.toast')
</x-app-layout>
