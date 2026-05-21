<x-app-layout>
    <x-slot name="title">Pendaftaran & Antrean</x-slot>

    <div class="page-header">
        <div>
            <h2 class="page-title">Pendaftaran & Antrean</h2>
            <p class="page-subtitle">Appointment, registrasi walk-in, dan monitoring antrean</p>
        </div>
    </div>

    @php $tab = request()->query('tab', 'list'); @endphp

    {{-- Tab Navigation --}}
    <div class="border-b border-gray-200 dark:border-gray-700 mb-6">
        <nav class="flex gap-0 -mb-px">
            @foreach ([
                'list'        => ['label' => 'List Pendaftaran',     'icon' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01'],
                'pendaftaran' => ['label' => 'Pendaftaran / Walk-in', 'icon' => 'M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z'],
                'appointment' => ['label' => 'Appointment',           'icon' => 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z'],
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

    {{-- Konten Tab --}}
    @switch($tab)
        @case('list')
            <livewire:kunjungan.list-pendaftaran />
            @break
        @case('pendaftaran')
            <livewire:kunjungan.pendaftaran-tab />
            @break
        @case('appointment')
            <livewire:kunjungan.appointment-tab />
            @break
    @endswitch

    {{-- Toast --}}
    <div x-data="{ show:false, type:'success', message:'' }"
         x-on:notify.window="show=true; type=$event.detail.type; message=$event.detail.message; setTimeout(()=>show=false,4000)"
         x-show="show" x-transition class="fixed bottom-5 right-5 z-50 min-w-72">
        <div :class="{ 'bg-emerald-50 border-emerald-400 text-emerald-800': type==='success',
                        'bg-red-50 border-red-400 text-red-800': type==='error' }"
             class="flex items-center gap-3 rounded-xl border px-4 py-3 shadow-lg text-sm font-medium">
            <span x-text="message"></span>
        </div>
    </div>
</x-app-layout>
