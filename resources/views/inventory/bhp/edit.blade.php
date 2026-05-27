<x-app-layout>
    <x-slot name="title">Dokumen BHP</x-slot>

    <div class="page-content">
        <div class="page-header">
            <div>
                <h1 class="page-title">Dokumen Pemakaian BHP</h1>
                <p class="page-subtitle">Edit draft atau verifikasi pengeluaran stok</p>
            </div>
            <a href="{{ route('inventory.bhp.index') }}" class="btn-secondary">Kembali</a>
        </div>
        <livewire:inventory.bhp.bhp-form :id="$bhp->id" />
    </div>
</x-app-layout>
