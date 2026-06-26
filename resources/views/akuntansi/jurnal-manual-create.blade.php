<x-app-layout>
    <x-slot name="title">Input Jurnal Manual</x-slot>

    <div class="page-header">
        <div>
            <h2 class="page-title">Input Jurnal Manual</h2>
            <p class="page-subtitle">Catat biaya operasional non-sistem atau mutasi modal pemilik</p>
        </div>
        <a href="{{ route('akuntansi.jurnal-manual') }}" class="btn-secondary">← Kembali</a>
    </div>

    <livewire:akuntansi.jurnal-manual-form />

    @include('akuntansi.partials.toast')
</x-app-layout>
