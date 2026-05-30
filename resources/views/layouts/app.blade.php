<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'EMR System') }} — {{ $title ?? 'Dashboard' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles

    {{-- Anti-flash: terapkan dark mode sebelum render agar tidak berkedip --}}
    <script>
        (function () {
            const theme = localStorage.getItem('theme');
            if (theme === 'dark' || (!theme && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                document.documentElement.classList.add('dark');
            }
        })();
    </script>
</head>
<body class="h-full bg-gray-100 font-sans antialiased dark:bg-gray-900">

<div
    x-data="{
        sidebarOpen: false,
        dark: localStorage.getItem('theme') === 'dark'
            || (!localStorage.getItem('theme') && window.matchMedia('(prefers-color-scheme: dark)').matches),
        toggleDark() {
            this.dark = !this.dark;
            if (this.dark) {
                document.documentElement.classList.add('dark');
                localStorage.setItem('theme', 'dark');
            } else {
                document.documentElement.classList.remove('dark');
                localStorage.setItem('theme', 'light');
            }
        }
    }"
    class="flex h-full overflow-hidden"
>

    {{-- ── Overlay mobile ─────────────────────── --}}
    <div
        x-show="sidebarOpen"
        x-transition.opacity
        @click="sidebarOpen = false"
        class="fixed inset-0 z-30 bg-black/50 md:hidden"
    ></div>

    {{-- ── SIDEBAR ─────────────────────────────── --}}
    <aside
        :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
        class="fixed inset-y-0 left-0 z-40 flex w-64 flex-col
               bg-[#0a3d62] text-white
               transition-transform duration-300 ease-in-out
               md:relative md:translate-x-0 md:flex-shrink-0"
    >
        {{-- Logo --}}
        <div class="flex h-16 items-center gap-3 border-b border-white/10 px-5">
            <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-white/20">
                <svg class="h-5 w-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                </svg>
            </div>
            <div class="leading-tight">
                <p class="text-sm font-bold tracking-wide">EMR System</p>
                <p class="text-[10px] text-white/60 uppercase tracking-widest">Rekam Medis</p>
            </div>
        </div>

        {{-- Nav --}}
        <nav class="flex-1 overflow-y-auto px-3 py-4 space-y-0.5 scrollbar-thin scrollbar-thumb-white/10 scrollbar-track-transparent">

            <p class="px-3 pt-2 pb-1 text-[10px] font-semibold uppercase tracking-widest text-white/40">Menu Utama</p>

            <x-sidebar-item route="dashboard" icon="chart-bar">Dashboard</x-sidebar-item>
            <x-sidebar-item route="pasien.index" icon="users" permission="pasien.view">Manajemen Pasien</x-sidebar-item>
            <x-sidebar-item route="kunjungan.index" icon="clipboard-list" permission="kunjungan.view">Pendaftaran & Antrean</x-sidebar-item>

            <p class="px-3 pt-4 pb-1 text-[10px] font-semibold uppercase tracking-widest text-white/40">Klinis</p>

            <x-sidebar-item route="pemeriksaan.index" icon="document-text" permission="asesmen.view">Pemeriksaan</x-sidebar-item>
            <x-sidebar-item route="rawat-inap.index" icon="office-building">Rawat Inap</x-sidebar-item>
            <x-sidebar-item route="farmasi.stok.index" icon="beaker" permission="obat.view">Farmasi</x-sidebar-item>
            <x-sidebar-item route="farmasi.ritel.index" icon="shopping-bag" permission="obat.view">Penjualan Ritel</x-sidebar-item>

            <p class="px-3 pt-4 pb-1 text-[10px] font-semibold uppercase tracking-widest text-white/40">Administrasi</p>

            <x-sidebar-item route="billing.index" icon="cash" permission="billing.view">Billing & Kasir</x-sidebar-item>
            <x-sidebar-item route="inventory.index" icon="office-building" permission="obat.view">Inventory</x-sidebar-item>
            <x-sidebar-item route="inventory.kartu-stok.index" icon="clipboard-check" permission="obat.view">Kartu Stok</x-sidebar-item>
            @can('obat.view')
            <x-sidebar-item route="inventory.bhp.index" icon="beaker">Pemakaian BHP</x-sidebar-item>
            <x-sidebar-item route="inventory.opname.index" icon="clipboard-list">Stok Opname</x-sidebar-item>
            @endcan
            @canany(['laporan.registrasi.view','laporan.pemeriksaan.view','laporan.kasir.view','laporan.pharmacy.view','laporan.view'])
            <div x-data="{ laporanOpen: {{ request()->routeIs('laporan.*') ? 'true' : 'false' }} }">
                <button
                    @click="laporanOpen = !laporanOpen"
                    @class([
                        'w-full flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-colors duration-150',
                        'bg-white/10 text-white' => request()->routeIs('laporan.*'),
                        'text-white/70 hover:bg-white/10 hover:text-white' => !request()->routeIs('laporan.*'),
                    ])
                >
                    <svg class="h-5 w-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 8v8m-4-5v5m-4-2v2m-2 4h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                    <span class="flex-1 text-left">Laporan</span>
                    <svg
                        class="h-4 w-4 flex-shrink-0 transition-transform duration-200"
                        :class="laporanOpen ? 'rotate-180' : ''"
                        fill="none" stroke="currentColor" viewBox="0 0 24 24"
                    >
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                </button>

                <div
                    x-show="laporanOpen"
                    x-transition:enter="transition ease-out duration-150"
                    x-transition:enter-start="opacity-0 -translate-y-1"
                    x-transition:enter-end="opacity-100 translate-y-0"
                    x-transition:leave="transition ease-in duration-100"
                    x-transition:leave-start="opacity-100 translate-y-0"
                    x-transition:leave-end="opacity-0 -translate-y-1"
                    class="mt-0.5 space-y-0.5 pl-9"
                >
                    @can('laporan.registrasi.view')
                    <a href="{{ route('laporan.registrasi.kunjungan') }}"
                        @class([
                            'block rounded-lg px-3 py-2 text-sm font-medium transition-colors duration-150',
                            'bg-white/20 text-white' => request()->routeIs('laporan.registrasi.*'),
                            'text-white/70 hover:bg-white/10 hover:text-white' => !request()->routeIs('laporan.registrasi.*'),
                        ])
                    >Registrasi</a>
                    @endcan

                    @can('laporan.pemeriksaan.view')
                    <a href="{{ route('laporan.pemeriksaan.diagnosa') }}"
                        @class([
                            'block rounded-lg px-3 py-2 text-sm font-medium transition-colors duration-150',
                            'bg-white/20 text-white' => request()->routeIs('laporan.pemeriksaan.*'),
                            'text-white/70 hover:bg-white/10 hover:text-white' => !request()->routeIs('laporan.pemeriksaan.*'),
                        ])
                    >Pemeriksaan</a>
                    @endcan

                    @can('laporan.kasir.view')
                    <a href="{{ route('laporan.kasir.transaksi') }}"
                        @class([
                            'block rounded-lg px-3 py-2 text-sm font-medium transition-colors duration-150',
                            'bg-white/20 text-white' => request()->routeIs('laporan.kasir.*'),
                            'text-white/70 hover:bg-white/10 hover:text-white' => !request()->routeIs('laporan.kasir.*'),
                        ])
                    >Kasir</a>
                    @endcan

                    @can('laporan.pharmacy.view')
                    <a href="{{ route('laporan.pharmacy.resep') }}"
                        @class([
                            'block rounded-lg px-3 py-2 text-sm font-medium transition-colors duration-150',
                            'bg-white/20 text-white' => request()->routeIs('laporan.pharmacy.*'),
                            'text-white/70 hover:bg-white/10 hover:text-white' => !request()->routeIs('laporan.pharmacy.*'),
                        ])
                    >Pharmacy</a>
                    @endcan
                </div>
            </div>
            @endcanany
            @canany(['piutang.view', 'piutang.tagih', 'piutang.lunas'])
            <div x-data="{ keuanganOpen: {{ request()->routeIs('keuangan.*') ? 'true' : 'false' }} }">
                <button
                    @click="keuanganOpen = !keuanganOpen"
                    @class([
                        'w-full flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-colors duration-150',
                        'bg-white/10 text-white' => request()->routeIs('keuangan.*'),
                        'text-white/70 hover:bg-white/10 hover:text-white' => !request()->routeIs('keuangan.*'),
                    ])
                >
                    <svg class="h-5 w-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <span class="flex-1 text-left">Keuangan</span>
                    <svg
                        class="h-4 w-4 flex-shrink-0 transition-transform duration-200"
                        :class="keuanganOpen ? 'rotate-180' : ''"
                        fill="none" stroke="currentColor" viewBox="0 0 24 24"
                    >
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                </button>
                <div
                    x-show="keuanganOpen"
                    x-transition:enter="transition ease-out duration-150"
                    x-transition:enter-start="opacity-0 -translate-y-1"
                    x-transition:enter-end="opacity-100 translate-y-0"
                    x-transition:leave="transition ease-in duration-100"
                    x-transition:leave-start="opacity-100 translate-y-0"
                    x-transition:leave-end="opacity-0 -translate-y-1"
                    class="mt-0.5 space-y-0.5 pl-9"
                >
                    @can('piutang.view')
                    <a href="{{ route('keuangan.piutang.index') }}"
                        @class([
                            'block rounded-lg px-3 py-2 text-sm font-medium transition-colors duration-150',
                            'bg-white/20 text-white' => request()->routeIs('keuangan.piutang.*'),
                            'text-white/70 hover:bg-white/10 hover:text-white' => !request()->routeIs('keuangan.piutang.*'),
                        ])
                    >Piutang Asuransi</a>
                    @endcan
                    @can('piutang.tagih')
                    <a href="{{ route('keuangan.penagihan.index') }}"
                        @class([
                            'block rounded-lg px-3 py-2 text-sm font-medium transition-colors duration-150',
                            'bg-white/20 text-white' => request()->routeIs('keuangan.penagihan.*'),
                            'text-white/70 hover:bg-white/10 hover:text-white' => !request()->routeIs('keuangan.penagihan.*'),
                        ])
                    >Penagihan</a>
                    @endcan
                </div>
            </div>
            @endcanany
            @canany(['masterdata.view','masterdata.create','pengaturan.view','asuransi.config_bpjs','asuransi.master.view'])
            <div x-data="{ pengaturanOpen: {{ request()->routeIs('pengaturan.*') ? 'true' : 'false' }} }">
                <button
                    @click="pengaturanOpen = !pengaturanOpen"
                    @class([
                        'w-full flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-colors duration-150',
                        'bg-white/10 text-white' => request()->routeIs('pengaturan.*'),
                        'text-white/70 hover:bg-white/10 hover:text-white' => !request()->routeIs('pengaturan.*'),
                    ])
                >
                    <svg class="h-5 w-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                    <span class="flex-1 text-left">Pengaturan</span>
                    <svg
                        class="h-4 w-4 flex-shrink-0 transition-transform duration-200"
                        :class="pengaturanOpen ? 'rotate-180' : ''"
                        fill="none" stroke="currentColor" viewBox="0 0 24 24"
                    >
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                </button>

                <div
                    x-show="pengaturanOpen"
                    x-transition:enter="transition ease-out duration-150"
                    x-transition:enter-start="opacity-0 -translate-y-1"
                    x-transition:enter-end="opacity-100 translate-y-0"
                    x-transition:leave="transition ease-in duration-100"
                    x-transition:leave-start="opacity-100 translate-y-0"
                    x-transition:leave-end="opacity-0 -translate-y-1"
                    class="mt-0.5 space-y-0.5 pl-9"
                >
                    @can('masterdata.view')
                    <a href="{{ route('pengaturan.masterdata') }}"
                        @class([
                            'block rounded-lg px-3 py-2 text-sm font-medium transition-colors duration-150',
                            'bg-white/20 text-white' => request()->routeIs('pengaturan.masterdata'),
                            'text-white/70 hover:bg-white/10 hover:text-white' => !request()->routeIs('pengaturan.masterdata'),
                        ])
                    >Master Data Klinis</a>
                    @endcan

                    @can('masterdata.view')
                    <a href="{{ route('pengaturan.dokter') }}"
                        @class([
                            'block rounded-lg px-3 py-2 text-sm font-medium transition-colors duration-150',
                            'bg-white/20 text-white' => request()->routeIs('pengaturan.dokter*'),
                            'text-white/70 hover:bg-white/10 hover:text-white' => !request()->routeIs('pengaturan.dokter*'),
                        ])
                    >Data Dokter</a>
                    @endcan

                    @can('masterdata.view')
                    <a href="{{ route('pengaturan.icd.index') }}"
                        @class([
                            'block rounded-lg px-3 py-2 text-sm font-medium transition-colors duration-150',
                            'bg-white/20 text-white' => request()->routeIs('pengaturan.icd.*'),
                            'text-white/70 hover:bg-white/10 hover:text-white' => !request()->routeIs('pengaturan.icd.*'),
                        ])
                    >Data ICD-10</a>
                    @endcan

                    @can('masterdata.create')
                    <a href="{{ route('pengaturan.sumber-informasi') }}"
                        @class([
                            'block rounded-lg px-3 py-2 text-sm font-medium transition-colors duration-150',
                            'bg-white/20 text-white' => request()->routeIs('pengaturan.sumber-informasi'),
                            'text-white/70 hover:bg-white/10 hover:text-white' => !request()->routeIs('pengaturan.sumber-informasi'),
                        ])
                    >Sumber Informasi</a>
                    @endcan

                    @can('asuransi.config_bpjs')
                    <a href="{{ route('pengaturan.asuransi.bpjs') }}"
                        @class([
                            'block rounded-lg px-3 py-2 text-sm font-medium transition-colors duration-150',
                            'bg-white/20 text-white' => request()->routeIs('pengaturan.asuransi.bpjs'),
                            'text-white/70 hover:bg-white/10 hover:text-white' => !request()->routeIs('pengaturan.asuransi.bpjs'),
                        ])
                    >Konfigurasi BPJS</a>
                    @endcan

                    @can('asuransi.master.view')
                    <a href="{{ route('pengaturan.asuransi.index') }}"
                        @class([
                            'block rounded-lg px-3 py-2 text-sm font-medium transition-colors duration-150',
                            'bg-white/20 text-white' => request()->routeIs('pengaturan.asuransi.index') || request()->routeIs('pengaturan.asuransi.create') || request()->routeIs('pengaturan.asuransi.edit'),
                            'text-white/70 hover:bg-white/10 hover:text-white' => !request()->routeIs('pengaturan.asuransi.*'),
                        ])
                    >Master Asuransi</a>
                    @endcan

                    @can('pengaturan.view')
                    <a href="{{ route('pengaturan.pengguna') }}"
                        @class([
                            'block rounded-lg px-3 py-2 text-sm font-medium transition-colors duration-150',
                            'bg-white/20 text-white' => request()->routeIs('pengaturan.pengguna'),
                            'text-white/70 hover:bg-white/10 hover:text-white' => !request()->routeIs('pengaturan.pengguna'),
                        ])
                    >Manajemen Pengguna</a>

                    <a href="{{ route('pengaturan.klinik') }}"
                        @class([
                            'block rounded-lg px-3 py-2 text-sm font-medium transition-colors duration-150',
                            'bg-white/20 text-white' => request()->routeIs('pengaturan.klinik'),
                            'text-white/70 hover:bg-white/10 hover:text-white' => !request()->routeIs('pengaturan.klinik'),
                        ])
                    >Profil Klinik</a>
                    @endcan
                </div>
            </div>
            @endcanany
        </nav>

        {{-- User info --}}
        <div class="border-t border-white/10 px-4 py-3">
            <div class="flex items-center gap-3">
                <div class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full bg-white/20 text-sm font-bold uppercase">
                    {{ substr(auth()->user()->nama ?? 'U', 0, 1) }}
                </div>
                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-medium text-white">{{ auth()->user()->nama ?? '-' }}</p>
                    <p class="truncate text-xs text-white/50">{{ auth()->user()->getRoleNames()->first() ?? '' }}</p>
                </div>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="text-white/50 hover:text-white transition-colors" title="Logout">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                        </svg>
                    </button>
                </form>
            </div>
        </div>
    </aside>

    {{-- ── MAIN AREA ────────────────────────────── --}}
    <div class="flex flex-1 flex-col overflow-hidden">

        {{-- Navbar top --}}
        <header class="flex h-16 flex-shrink-0 items-center justify-between border-b border-gray-200 bg-white px-5 shadow-sm
                        dark:bg-gray-800 dark:border-gray-700">
            {{-- Hamburger (mobile) --}}
            <button
                @click="sidebarOpen = !sidebarOpen"
                class="rounded-lg p-2 text-gray-500 hover:bg-gray-100 md:hidden dark:text-gray-400 dark:hover:bg-gray-700"
            >
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                </svg>
            </button>

            {{-- Page title --}}
            <div class="hidden md:block">
                <h1 class="text-base font-semibold text-gray-800 dark:text-white">{{ $title ?? 'Dashboard' }}</h1>
            </div>

            {{-- Right side --}}
            <div class="flex items-center gap-2">

                {{-- Dark mode toggle --}}
                <button
                    @click="toggleDark()"
                    class="relative rounded-lg p-2 text-gray-500 hover:bg-gray-100 transition-colors
                           dark:text-gray-400 dark:hover:bg-gray-700"
                    :title="dark ? 'Mode Terang' : 'Mode Gelap'"
                >
                    {{-- Sun icon (tampil saat dark mode aktif) --}}
                    <svg x-show="dark" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364-6.364l-.707.707M6.343 17.657l-.707.707M17.657 17.657l-.707-.707M6.343 6.343l-.707-.707M12 7a5 5 0 110 10A5 5 0 0112 7z"/>
                    </svg>
                    {{-- Moon icon (tampil saat light mode) --}}
                    <svg x-show="!dark" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/>
                    </svg>
                </button>

                {{-- Notifikasi --}}
                <button class="relative rounded-lg p-2 text-gray-500 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-700">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                    </svg>
                </button>

                {{-- User avatar --}}
                <div class="flex items-center gap-2 pl-1 text-sm text-gray-700 dark:text-gray-300">
                    <div class="flex h-8 w-8 items-center justify-center rounded-full bg-[#0a3d62] text-xs font-bold uppercase text-white">
                        {{ substr(auth()->user()->nama ?? 'U', 0, 1) }}
                    </div>
                    <span class="hidden font-medium sm:block">{{ auth()->user()->nama ?? '-' }}</span>
                </div>
            </div>
        </header>

        {{-- Content --}}
        <main class="flex-1 overflow-y-auto p-6">
            <x-alert />
            {{ $slot }}
        </main>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js" defer></script>
@stack('scripts')
@livewireScripts
</body>
</html>
