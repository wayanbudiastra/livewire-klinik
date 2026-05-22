<x-app-layout>
    <x-slot name="title">Inventory</x-slot>

    <div class="page-header">
        <div>
            <h2 class="page-title">Manajemen Inventory</h2>
            <p class="page-subtitle">Supplier, barang, pembelian, penerimaan, dan alert stok</p>
        </div>
    </div>

    @php $tab = request()->query('tab', 'alert'); @endphp

    <div class="border-b border-gray-200 dark:border-gray-700 mb-6">
        <nav class="flex gap-0 -mb-px overflow-x-auto">
            @foreach ([
                'alert'    => ['label' => 'Alert Stok',        'icon' => 'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z'],
                'barang'   => ['label' => 'Master Barang',     'icon' => 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4'],
                'supplier' => ['label' => 'Supplier',          'icon' => 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4'],
                'po'       => ['label' => 'Purchase Order',    'icon' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01'],
                'gr'       => ['label' => 'Penerimaan Barang', 'icon' => 'M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4'],
            ] as $key => $t)
            <a href="?tab={{ $key }}"
               @class([
                   'flex items-center gap-2 whitespace-nowrap px-4 py-3 text-sm font-medium border-b-2 transition-colors',
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
        @case('alert')
            <livewire:inventory.alert-stok.alert-stok-table />
            @break
        @case('barang')
            <livewire:inventory.barang.barang-table />
            <livewire:inventory.barang.barang-form />
            @break
        @case('supplier')
            <livewire:inventory.supplier.supplier-table />
            <livewire:inventory.supplier.supplier-form />
            @break
        @case('po')
            <livewire:inventory.purchase-order.po-table />
            @break
        @case('gr')
            <livewire:inventory.goods-receipt.gr-table />
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
