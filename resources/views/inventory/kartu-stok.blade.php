<x-app-layout>
    <x-slot name="title">Kartu Stok</x-slot>

    <div class="page-header">
        <div>
            <h2 class="page-title">Kartu Stok Barang</h2>
            <p class="page-subtitle">Riwayat mutasi masuk/keluar dengan saldo berjalan per barang</p>
        </div>
    </div>

    @php $tab = request()->query('tab', 'kartu'); @endphp

    <div class="border-b border-gray-200 dark:border-gray-700 mb-6">
        <nav class="flex gap-0 -mb-px overflow-x-auto">
            @foreach ([
                'kartu'     => ['label' => 'Kartu Stok per Barang',    'icon' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01'],
                'ringkasan' => ['label' => 'Ringkasan Semua Barang',   'icon' => 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z'],
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
        @case('kartu')
            <livewire:inventory.kartu-stok.kartu-stok-index />
            @break
        @case('ringkasan')
            <livewire:inventory.kartu-stok.kartu-stok-ringkasan />
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
