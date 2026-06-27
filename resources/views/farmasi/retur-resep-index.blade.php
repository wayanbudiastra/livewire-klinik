<x-app-layout>
    <x-slot name="title">Retur Resep</x-slot>

    <div class="page-header">
        <div>
            <h2 class="page-title">Retur Resep</h2>
            <p class="page-subtitle">Retur resep pasien yang sudah lunas, selama masih di hari yang sama</p>
        </div>
    </div>

    <livewire:farmasi.retur-resep.retur-resep-table />

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
