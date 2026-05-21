<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'EMR System') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    {{-- Anti-flash dark mode --}}
    <script>
        (function () {
            const theme = localStorage.getItem('theme');
            if (theme === 'dark' || (!theme && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                document.documentElement.classList.add('dark');
            }
        })();
    </script>
</head>
<body class="h-full font-sans antialiased bg-white dark:bg-gray-900">

<div class="flex min-h-screen">

    {{-- ── SISI KIRI — Profil Klinik ──────────────────────── --}}
    <div class="hidden lg:flex lg:w-1/2 xl:w-3/5 relative flex-col justify-between
                bg-[#0a3d62] overflow-hidden">

        {{-- Pattern dekoratif --}}
        <div class="absolute inset-0 opacity-10">
            <svg width="100%" height="100%" xmlns="http://www.w3.org/2000/svg">
                <defs>
                    <pattern id="grid" width="60" height="60" patternUnits="userSpaceOnUse">
                        <path d="M 60 0 L 0 0 0 60" fill="none" stroke="white" stroke-width="1"/>
                    </pattern>
                </defs>
                <rect width="100%" height="100%" fill="url(#grid)"/>
            </svg>
        </div>

        {{-- Lingkaran dekoratif --}}
        <div class="absolute -top-32 -left-32 w-96 h-96 bg-white/5 rounded-full"></div>
        <div class="absolute -bottom-40 -right-20 w-[30rem] h-[30rem] bg-white/5 rounded-full"></div>
        <div class="absolute top-1/2 right-0 w-64 h-64 bg-blue-400/10 rounded-full -translate-y-1/2 translate-x-1/2"></div>

        {{-- Konten utama kiri --}}
        <div class="relative z-10 flex flex-col justify-center h-full px-12 xl:px-16 py-12">

            {{-- Logo & nama --}}
            <div class="mb-12">
                <div class="flex items-center gap-4 mb-8">
                    <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-white/20 backdrop-blur">
                        <svg class="h-8 w-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                        </svg>
                    </div>
                    <div>
                        <h1 class="text-2xl font-bold text-white tracking-wide">EMR System</h1>
                        <p class="text-blue-200 text-xs uppercase tracking-widest">Sistem Rekam Medis Elektronik</p>
                    </div>
                </div>

                <h2 class="text-3xl xl:text-4xl font-bold text-white leading-tight mb-4">
                    Klinik Sehat<br>
                    <span class="text-blue-300">Bersama</span>
                </h2>
                <p class="text-blue-100/80 text-base leading-relaxed max-w-md">
                    Platform digital terintegrasi untuk manajemen rekam medis, pendaftaran pasien,
                    dan pelayanan kesehatan yang lebih efisien.
                </p>
            </div>

            {{-- Fitur unggulan --}}
            <div class="space-y-4">
                @foreach ([
                    ['icon' => 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z', 'label' => 'Rekam Medis Digital', 'desc' => 'SOAP Note & riwayat lengkap'],
                    ['icon' => 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z', 'label' => 'Manajemen Pasien', 'desc' => 'Pendaftaran & antrean real-time'],
                    ['icon' => 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z', 'label' => 'Laporan & Analitik', 'desc' => 'Export PDF & Excel otomatis'],
                ] as $f)
                <div class="flex items-center gap-4">
                    <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-xl bg-white/10">
                        <svg class="h-5 w-5 text-blue-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $f['icon'] }}"/>
                        </svg>
                    </div>
                    <div>
                        <p class="text-white text-sm font-semibold">{{ $f['label'] }}</p>
                        <p class="text-blue-200/70 text-xs">{{ $f['desc'] }}</p>
                    </div>
                </div>
                @endforeach
            </div>
        </div>

        {{-- Footer kiri --}}
        <div class="relative z-10 px-12 xl:px-16 py-6 border-t border-white/10">
            <p class="text-blue-200/50 text-xs">
                &copy; {{ date('Y') }} EMR System. Versi 1.0.0
            </p>
        </div>
    </div>

    {{-- ── SISI KANAN — Form Login ─────────────────────────── --}}
    <div class="flex flex-1 flex-col justify-center px-6 py-12 lg:px-12 xl:px-16
                bg-white dark:bg-gray-900">

        {{-- Dark mode toggle --}}
        <div x-data="{ dark: document.documentElement.classList.contains('dark') }"
             class="absolute top-5 right-5">
            <button
                @click="dark = !dark; dark ? (document.documentElement.classList.add('dark'), localStorage.setItem('theme','dark')) : (document.documentElement.classList.remove('dark'), localStorage.setItem('theme','light'))"
                class="rounded-lg p-2 text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors"
                :title="dark ? 'Mode Terang' : 'Mode Gelap'"
            >
                <svg x-show="dark" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364-6.364l-.707.707M6.343 17.657l-.707.707M17.657 17.657l-.707-.707M6.343 6.343l-.707-.707M12 7a5 5 0 110 10A5 5 0 0112 7z"/>
                </svg>
                <svg x-show="!dark" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/>
                </svg>
            </button>
        </div>

        <div class="w-full max-w-md mx-auto">

            {{-- Logo mobile (tampil hanya di kecil) --}}
            <div class="flex items-center gap-3 mb-10 lg:hidden">
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-[#0a3d62]">
                    <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                    </svg>
                </div>
                <span class="text-lg font-bold text-gray-900 dark:text-white">EMR System</span>
            </div>

            {{-- Heading --}}
            <div class="mb-8">
                <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-1">
                    Selamat Datang
                </h2>
                <p class="text-gray-500 dark:text-gray-400 text-sm">
                    Masuk ke akun Anda untuk melanjutkan
                </p>
            </div>

            {{-- Slot konten (form login) --}}
            {{ $slot }}

            {{-- Footer kanan --}}
            <p class="mt-10 text-center text-xs text-gray-400 dark:text-gray-600">
                &copy; {{ date('Y') }} EMR System &mdash; Klinik Sehat Bersama
            </p>
        </div>
    </div>
</div>

</body>
</html>
