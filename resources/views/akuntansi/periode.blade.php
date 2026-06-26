<x-app-layout>
    <x-slot name="title">Tutup/Buka Periode</x-slot>

    <div class="page-header">
        <div>
            <h2 class="page-title">Tutup/Buka Periode Bulanan</h2>
            <p class="page-subtitle">Kunci data akuntansi bulan yang sudah selesai diproses (paling lambat tanggal 5 bulan berikutnya)</p>
        </div>
    </div>

    <livewire:akuntansi.periode-akuntansi-table />

    @include('akuntansi.partials.toast')
</x-app-layout>
