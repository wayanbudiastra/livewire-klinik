<x-app-layout>
    <x-slot name="title">Edit Transaksi — {{ $tr->nomor_ritel }}</x-slot>

    <div class="page-header">
        <div>
            <h2 class="page-title">Edit Transaksi</h2>
            <p class="page-subtitle font-mono text-sm">{{ $tr->nomor_ritel }}</p>
        </div>
        <a href="{{ route('farmasi.ritel.show', $tr->id) }}" class="btn-secondary">← Batal Edit</a>
    </div>

    <livewire:farmasi.ritel.ritel-form :id="$tr->id" />
</x-app-layout>
