<x-app-layout>
    <x-slot name="title">Detail Invoice {{ $billing->nomor_invoice }}</x-slot>

    <div class="page-header">
        <div class="flex items-center gap-3">
            <a href="{{ route('kasir.billing.index') }}"
               class="text-gray-400 hover:text-gray-600">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
            </a>
            <div>
                <h1 class="page-title">Detail Invoice</h1>
                <p class="page-subtitle">{{ $billing->nomor_invoice }}</p>
            </div>
        </div>
    </div>

    <div class="page-content max-w-3xl">
        <livewire:kasir.billing.billing-detail :billing="$billing" />
    </div>
</x-app-layout>
