<x-app-layout>
    <x-slot name="title">Demo Data Generator</x-slot>

    <div class="page-header">
        <div>
            <h2 class="page-title">Demo Data Generator</h2>
            <p class="page-subtitle">Generate transaksi realistis untuk keperluan presentasi & demo ke klien</p>
        </div>
        <div class="flex items-center gap-2 rounded-lg bg-amber-50 border border-amber-200 px-3 py-2 text-xs font-medium text-amber-700">
            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            Hanya untuk Super Admin · Demo Purpose Only
        </div>
    </div>

    <livewire:pengaturan.demo-data-generator />

    <div
        x-data="{ show: false, type: 'success', message: '' }"
        x-on:notify.window="show = true; type = $event.detail.type; message = $event.detail.message; setTimeout(() => show = false, 3500)"
        x-show="show"
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0 translate-y-4"
        x-transition:enter-end="opacity-100 translate-y-0"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        x-cloak
        :class="type === 'success' ? 'bg-emerald-600' : 'bg-red-600'"
        class="fixed bottom-6 right-6 z-50 text-white text-sm font-medium px-5 py-3 rounded-xl shadow-lg flex items-center gap-2">
        <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
        </svg>
        <span x-text="message"></span>
    </div>
</x-app-layout>
