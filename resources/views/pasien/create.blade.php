<x-app-layout>
    <x-slot name="title">Daftarkan Pasien Baru</x-slot>

    <div class="page-header">
        <div>
            <h2 class="page-title">Daftarkan Pasien Baru</h2>
            <p class="page-subtitle">Isi data demografi pasien dengan lengkap</p>
        </div>
        <a href="{{ route('pasien.index') }}" class="btn-secondary">← Kembali</a>
    </div>

    <livewire:pasien.pasien-form />

    <div x-data="{ show:false, type:'success', message:'' }"
         x-on:notify.window="show=true; type=$event.detail.type; message=$event.detail.message; setTimeout(()=>show=false,5000)"
         x-show="show" x-transition class="fixed bottom-5 right-5 z-50 min-w-72 max-w-sm">
        <div :class="{ 'bg-emerald-50 border-emerald-400 text-emerald-800': type==='success',
                        'bg-red-50 border-red-400 text-red-800': type==='error',
                        'bg-amber-50 border-amber-400 text-amber-800': type==='warning' }"
             class="flex items-center gap-3 rounded-xl border px-4 py-3 shadow-lg text-sm font-medium">
            <svg x-show="type==='success'" class="h-5 w-5 flex-shrink-0 text-emerald-500" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
            </svg>
            <svg x-show="type==='error'" class="h-5 w-5 flex-shrink-0 text-red-500" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
            </svg>
            <span x-text="message"></span>
        </div>
    </div>
</x-app-layout>
