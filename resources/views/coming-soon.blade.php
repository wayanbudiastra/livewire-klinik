<x-app-layout>
    <x-slot name="title">{{ $modul ?? 'Modul' }} — Dalam Pengembangan</x-slot>

    <div class="flex flex-col items-center justify-center min-h-[60vh] text-center px-4">

        {{-- Ilustrasi --}}
        <div class="relative mb-8">
            <div class="w-32 h-32 rounded-full bg-[#0a3d62]/10 dark:bg-[#0a3d62]/20 flex items-center justify-center mx-auto">
                <div class="w-20 h-20 rounded-full bg-[#0a3d62]/15 dark:bg-[#0a3d62]/30 flex items-center justify-center">
                    <svg class="w-10 h-10 text-[#0a3d62] dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                              d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                </div>
            </div>

            {{-- Animasi titik berputar --}}
            <div class="absolute top-2 right-6 w-4 h-4 rounded-full bg-amber-400 animate-bounce" style="animation-delay: 0s;"></div>
            <div class="absolute bottom-4 left-4 w-3 h-3 rounded-full bg-[#0a3d62]/40 animate-bounce" style="animation-delay: 0.2s;"></div>
            <div class="absolute top-6 left-8 w-2.5 h-2.5 rounded-full bg-emerald-400 animate-bounce" style="animation-delay: 0.4s;"></div>
        </div>

        {{-- Badge status --}}
        <div class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-amber-100 text-amber-700
                    dark:bg-amber-900/30 dark:text-amber-400 text-sm font-semibold mb-4">
            <span class="h-2 w-2 rounded-full bg-amber-500 animate-pulse"></span>
            Sedang Dalam Pengembangan
        </div>

        {{-- Judul --}}
        <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 dark:text-white mb-3">
            Modul {{ $modul ?? '' }} Belum Tersedia
        </h1>

        {{-- Deskripsi --}}
        <p class="text-gray-500 dark:text-gray-400 max-w-md mb-2 leading-relaxed">
            {{ $deskripsi ?? 'Fitur ini sedang dalam proses pengembangan dan akan segera tersedia.' }}
        </p>
        <p class="text-gray-400 dark:text-gray-500 text-sm mb-8">
            Tim pengembang sedang bekerja keras untuk menghadirkan modul ini secepatnya.
        </p>

        {{-- Progress bar dekoratif --}}
        <div class="w-72 mb-8">
            <div class="flex justify-between text-xs text-gray-400 mb-1.5">
                <span>Progress Pengembangan</span>
                <span>{{ $progress ?? '0' }}%</span>
            </div>
            <div class="h-2 rounded-full bg-gray-200 dark:bg-gray-700 overflow-hidden">
                <div class="h-full rounded-full bg-gradient-to-r from-[#0a3d62] to-blue-400 transition-all duration-1000"
                     style="width: {{ $progress ?? 0 }}%"></div>
            </div>
        </div>

        {{-- Tombol --}}
        <div class="flex items-center gap-3">
            <a href="{{ route('dashboard') }}" class="btn-primary">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                </svg>
                Kembali ke Dashboard
            </a>
            <button onclick="history.back()" class="btn-secondary">
                ← Kembali
            </button>
        </div>

        {{-- Info roadmap --}}
        @if(isset($roadmap))
        <div class="mt-10 p-5 rounded-2xl border border-dashed border-gray-200 dark:border-gray-700 max-w-sm">
            <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3">Roadmap Fitur</p>
            <ul class="space-y-2 text-left">
                @foreach($roadmap as $item)
                <li class="flex items-start gap-2 text-sm text-gray-500 dark:text-gray-400">
                    <span class="mt-0.5 text-gray-300 dark:text-gray-600">○</span>
                    {{ $item }}
                </li>
                @endforeach
            </ul>
        </div>
        @endif

    </div>
</x-app-layout>
