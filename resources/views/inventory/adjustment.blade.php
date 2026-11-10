<x-app-layout>
<x-slot name="title">Adjustment Stok</x-slot>
<div class="page-header"><div><h2 class="page-title">Adjustment Stok Barang</h2><p class="page-subtitle">Penyesuaian manual dengan catatan wajib untuk audit</p></div>@can('obat.edit')<a href="{{ route('inventory.adjustment.create') }}" class="btn-primary">+ Adjustment Baru</a>@endcan</div>
@if(session('success'))<div class="alert alert-success mb-4">{{ session('success') }}</div>@endif
@php($tab=request()->query('tab','list'))
<div class="border-b border-gray-200 dark:border-gray-700 mb-6"><nav class="flex gap-0 -mb-px"><a href="{{ route('inventory.adjustment.index') }}" class="px-5 py-3 text-sm font-medium border-b-2">Riwayat Adjustment</a>@can('obat.edit')<a href="{{ route('inventory.adjustment.create') }}" class="px-5 py-3 text-sm font-medium border-b-2">Buat Adjustment</a>@endcan</nav></div>
@if($tab==='create')<livewire:inventory.adjustment.adjustment-create />@else<livewire:inventory.adjustment.adjustment-index />@endif
</x-app-layout>
