<x-app-layout>
    <x-slot name="title">Dashboard</x-slot>

    <div class="page-header">
        <div>
            <h2 class="page-title">Dashboard</h2>
            <p class="page-subtitle">Selamat datang, {{ auth()->user()->nama }}. Hari ini {{ now()->translatedFormat('l, d F Y') }}.</p>
        </div>
    </div>

    <livewire:dashboard />

</x-app-layout>
