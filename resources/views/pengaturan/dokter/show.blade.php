<x-app-layout>
    <x-slot name="title">Dokter — {{ $dokter->user->nama }}</x-slot>

    @php $tab = request()->query('tab', 'profil'); @endphp

    <div class="page-header">
        <div>
            <h2 class="page-title">{{ $dokter->user->nama }}</h2>
            <p class="page-subtitle">{{ $dokter->spesialisasi ?? 'Dokter Umum' }}
                @if($dokter->poli) · {{ $dokter->poli->first()?->nama }} @endif
            </p>
        </div>
        <a href="{{ route('pengaturan.dokter') }}" class="btn-secondary">← Kembali</a>
    </div>

    {{-- Tab Nav --}}
    <div class="border-b border-gray-200 dark:border-gray-700 mb-6">
        <nav class="flex gap-0 -mb-px overflow-x-auto">
            @foreach ([
                'profil'  => 'Profil Klinis',
                'poli'    => 'Mapping Poli',
                'fee'     => 'Sharing Fee',
                'jadwal'  => 'Jadwal Praktek',
            ] as $key => $label)
            <a href="?tab={{ $key }}"
               @class([
                   'px-5 py-3 text-sm font-medium border-b-2 whitespace-nowrap transition-colors',
                   'border-[#0a3d62] text-[#0a3d62] dark:border-blue-400 dark:text-blue-400' => $tab === $key,
                   'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400' => $tab !== $key,
               ])>{{ $label }}</a>
            @endforeach
        </nav>
    </div>

    @switch($tab)
        @case('profil')
            <livewire:pengaturan.dokter.dokter-profil-form />
            @break
        @case('poli')
            <livewire:pengaturan.dokter.dokter-poli-mapping :dokter-id="$dokter->id" />
            @break
        @case('fee')
            <livewire:pengaturan.dokter.sharing-fee-form :dokter-id="$dokter->id" />
            @break
        @case('jadwal')
            <livewire:pengaturan.dokter.jadwal-praktek-manager :dokter-id="$dokter->id" />
            @break
    @endswitch

    <div x-data="{ show:false, type:'success', message:'' }"
         x-on:notify.window="show=true; type=$event.detail.type; message=$event.detail.message; setTimeout(()=>show=false,3500)"
         x-show="show" x-transition
         class="fixed bottom-5 right-5 z-50 min-w-72">
        <div :class="{ 'bg-emerald-50 border-emerald-400 text-emerald-800': type==='success',
                        'bg-red-50 border-red-400 text-red-800': type==='error' }"
             class="flex items-center gap-3 rounded-xl border px-4 py-3 shadow-lg text-sm font-medium">
            <span x-text="message"></span>
        </div>
    </div>
</x-app-layout>
