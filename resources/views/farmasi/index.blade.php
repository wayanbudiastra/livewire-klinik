<x-app-layout>
    <x-slot name="title">Farmasi</x-slot>

    <div class="page-header">
        <div>
            <h2 class="page-title">Farmasi</h2>
            <p class="page-subtitle">Master data obat/alkes, stok, satuan, dan gudang</p>
        </div>
    </div>

    @php $tab = request()->query('tab', 'stok'); @endphp

    <div class="border-b border-gray-200 dark:border-gray-700 mb-6">
        <nav class="flex gap-0 -mb-px overflow-x-auto">
            @foreach ([
                'stok'    => ['label' => 'Stok Obat & Alkes', 'icon' => 'M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z'],
                'alert'   => ['label' => 'Alert Stok & Expired', 'icon' => 'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z'],
                'satuan'  => ['label' => 'Satuan', 'icon' => 'M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z'],
                'gudang'  => ['label' => 'Lokasi Gudang', 'icon' => 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4'],
            ] as $key => $t)
            <a href="?tab={{ $key }}"
               @class([
                   'flex items-center gap-2 whitespace-nowrap px-5 py-3 text-sm font-medium border-b-2 transition-colors',
                   'border-[#0a3d62] text-[#0a3d62] dark:border-blue-400 dark:text-blue-400' => $tab === $key,
                   'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400' => $tab !== $key,
               ])>
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $t['icon'] }}"/>
                </svg>
                {{ $t['label'] }}
            </a>
            @endforeach
        </nav>
    </div>

    @switch($tab)
        @case('stok')
            <livewire:farmasi.obat-table />
            <livewire:farmasi.obat-form />
            @break
        @case('alert')
            <livewire:farmasi.stok-alert />
            @break
        @case('satuan')
            <livewire:farmasi.satuan-table />
            @break
        @case('gudang')
            <livewire:farmasi.gudang-table />
            @break
    @endswitch

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
