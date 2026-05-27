<x-app-layout>
    <x-slot name="title">Buat Dokumen BHP</x-slot>

    <div class="page-content">
        <div class="page-header">
            <div>
                <h1 class="page-title">Buat Dokumen BHP</h1>
                <p class="page-subtitle">Pemakaian Bahan Habis Pakai dari gudang</p>
            </div>
            <a href="{{ route('inventory.bhp.index') }}" class="btn-secondary">Kembali</a>
        </div>
        <livewire:inventory.bhp.bhp-form />
    </div>
</x-app-layout>
