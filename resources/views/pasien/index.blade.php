<x-app-layout>
    <x-slot name="title">Manajemen Pasien</x-slot>

    <div class="page-header">
        <div>
            <h2 class="page-title">Manajemen Pasien</h2>
            <p class="page-subtitle">Registrasi, pencarian, dan manajemen data demografi pasien</p>
        </div>
        @can('pasien.create')
        <a href="{{ route('pasien.create') }}" class="btn-primary">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            Daftarkan Pasien
        </a>
        @endcan
    </div>

    <x-alert />
    <livewire:pasien.pasien-table />

    <div x-data="{ show:false, type:'success', message:'' }"
         x-on:notify.window="show=true; type=$event.detail.type; message=$event.detail.message; setTimeout(()=>show=false,3500)"
         x-show="show" x-transition class="fixed bottom-5 right-5 z-50 min-w-72">
        <div :class="{ 'bg-emerald-50 border-emerald-400 text-emerald-800': type==='success',
                        'bg-red-50 border-red-400 text-red-800': type==='error' }"
             class="flex items-center gap-3 rounded-xl border px-4 py-3 shadow-lg text-sm font-medium">
            <span x-text="message"></span>
        </div>
    </div>
</x-app-layout>
