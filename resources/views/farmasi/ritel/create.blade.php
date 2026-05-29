<x-app-layout>
    <x-slot name="title">Transaksi Ritel Baru</x-slot>

    <div class="page-header">
        <div>
            <h2 class="page-title">Transaksi Ritel Baru</h2>
            <p class="page-subtitle">Input obat untuk pembeli tanpa konsultasi dokter</p>
        </div>
        <a href="{{ route('farmasi.ritel.index') }}" class="btn-secondary">← Kembali</a>
    </div>

    <livewire:farmasi.ritel.ritel-form />
</x-app-layout>
