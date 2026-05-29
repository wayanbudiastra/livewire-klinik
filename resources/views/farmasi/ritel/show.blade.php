<x-app-layout>
    <x-slot name="title">Detail — {{ $tr->nomor_ritel }}</x-slot>

    <div class="page-header">
        <div>
            <h2 class="page-title font-mono">{{ $tr->nomor_ritel }}</h2>
            <p class="page-subtitle">Detail Transaksi Ritel · {{ $tr->nama_pembeli }}</p>
        </div>
        <a href="{{ route('farmasi.ritel.index') }}" class="btn-secondary">← Kembali</a>
    </div>

    <livewire:farmasi.ritel.ritel-detail :transaksi="$tr" />
</x-app-layout>
