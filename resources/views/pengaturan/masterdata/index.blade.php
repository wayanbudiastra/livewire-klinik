<x-app-layout>
    <x-slot name="title">Master Data Klinis</x-slot>

    <div class="page-header">
        <div>
            <h2 class="page-title">Master Data Klinis</h2>
            <p class="page-subtitle">Kelola poli, tindakan, laboratorium, radiologi, dan peralatan medis</p>
        </div>
    </div>

    <x-alert />

    {{-- Tab Navigation --}}
    @php $activeTab = request()->query('tab', 'tindakan'); @endphp
    <div class="border-b border-gray-200 dark:border-gray-700 mb-6">
        <nav class="flex gap-0 -mb-px overflow-x-auto">
            @foreach ([
                'tindakan'  => ['label' => 'Tindakan',        'icon' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2'],
                'lab'       => ['label' => 'Laboratorium',     'icon' => 'M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z'],
                'radiologi' => ['label' => 'Radiologi',        'icon' => 'M9 3H5a2 2 0 00-2 2v4m6-6h10a2 2 0 012 2v4M9 3v18m0 0h10a2 2 0 002-2V9M9 21H5a2 2 0 01-2-2V9m0 0h18'],
                'peralatan' => ['label' => 'Peralatan Medis',  'icon' => 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z M15 12a3 3 0 11-6 0 3 3 0 016 0z'],
            ] as $key => $tab)
            <a href="?tab={{ $key }}"
               @class([
                   'flex items-center gap-2 whitespace-nowrap px-5 py-3 text-sm font-medium border-b-2 transition-colors',
                   'border-[#0a3d62] text-[#0a3d62] dark:border-blue-400 dark:text-blue-400' => $activeTab === $key,
                   'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300' => $activeTab !== $key,
               ])>
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $tab['icon'] }}"/>
                </svg>
                {{ $tab['label'] }}
            </a>
            @endforeach
        </nav>
    </div>

    {{-- Konten --}}
    @switch($activeTab)
        @case('tindakan')
            <livewire:pengaturan.masterdata.tindakan-table />
            <livewire:pengaturan.masterdata.tindakan-form />
            @break
        @case('lab')
            <livewire:pengaturan.masterdata.penunjang-table :kategori="'lab'" />
            <livewire:pengaturan.masterdata.penunjang-form />
            @break
        @case('radiologi')
            <livewire:pengaturan.masterdata.penunjang-table :kategori="'radiologi'" />
            <livewire:pengaturan.masterdata.penunjang-form />
            @break
        @case('peralatan')
            <livewire:pengaturan.masterdata.peralatan-table />
            <livewire:pengaturan.masterdata.peralatan-form />
            @break
    @endswitch

    {{-- Toast Notification --}}
    <div
        x-data="{ show: false, type: 'success', message: '' }"
        x-on:notify.window="show = true; type = $event.detail.type; message = $event.detail.message; setTimeout(() => show = false, 3500)"
        x-show="show"
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0 translate-y-2"
        x-transition:enter-end="opacity-100 translate-y-0"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed bottom-5 right-5 z-50 min-w-72 max-w-sm"
    >
        <div :class="{
                'bg-emerald-50 border-emerald-400 text-emerald-800': type === 'success',
                'bg-red-50 border-red-400 text-red-800': type === 'error',
             }"
             class="flex items-center gap-3 rounded-xl border px-4 py-3 shadow-lg text-sm font-medium">
            <svg x-show="type === 'success'" class="h-5 w-5 flex-shrink-0 text-emerald-500" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
            </svg>
            <span x-text="message"></span>
        </div>
    </div>

</x-app-layout>
