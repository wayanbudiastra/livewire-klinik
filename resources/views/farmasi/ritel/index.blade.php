<x-app-layout>
    <x-slot name="title">Penjualan Ritel</x-slot>

    <div class="page-header">
        <div>
            <h2 class="page-title">Penjualan Ritel</h2>
            <p class="page-subtitle">Transaksi pembelian obat tanpa konsultasi dokter</p>
        </div>
        @can('obat.create')
        <a href="{{ route('farmasi.ritel.create') }}" class="btn-primary">+ Transaksi Baru</a>
        @endcan
    </div>

    <livewire:farmasi.ritel.ritel-table />
</x-app-layout>
