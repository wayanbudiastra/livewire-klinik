<x-app-layout>
    <x-slot name="title">Buat Proposal Harga</x-slot>

    <div class="page-header">
        <div>
            <h2 class="page-title">Buat Proposal Harga Baru</h2>
            <p class="page-subtitle">Isi konfigurasi kenaikan harga, sistem akan mengkalkulasi harga baru secara otomatis</p>
        </div>
        <a href="{{ route('harga.proposal.index') }}" class="btn-secondary">← Kembali</a>
    </div>

    <livewire:harga.proposal-harga-form />

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
