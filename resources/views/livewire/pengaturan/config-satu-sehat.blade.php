<div class="space-y-6">

    {{-- ═══════════════════════════════════════════════════════════════ --}}
    {{-- STATUS INTEGRASI                                               --}}
    {{-- ═══════════════════════════════════════════════════════════════ --}}
    <div class="card">
        <div class="card-header">
            <div>
                <h3 class="text-sm font-semibold text-gray-900">Status Integrasi</h3>
                <p class="text-xs text-gray-400 mt-0.5">Aktifkan atau nonaktifkan koneksi ke platform SatuSehat Kemkes</p>
            </div>
        </div>
        <div class="card-body">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-800">Aktifkan Integrasi SatuSehat</p>
                    <p class="text-xs text-gray-500 mt-0.5">
                        Data kunjungan dan rekam medis akan dikirim ke platform
                        <a href="https://satusehat.kemkes.go.id" target="_blank" class="text-indigo-600 hover:underline">SatuSehat Kemkes</a>
                    </p>
                </div>
                <button
                    type="button"
                    wire:click="$toggle('isActive')"
                    class="relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                    :class="{{ $isActive ? 'true' : 'false' }} ? 'bg-indigo-600' : 'bg-gray-200'"
                    x-data
                    @click="$el.classList.toggle('bg-indigo-600'); $el.classList.toggle('bg-gray-200')"
                    role="switch"
                    aria-checked="{{ $isActive ? 'true' : 'false' }}">
                    <span class="sr-only">Aktifkan SatuSehat</span>
                    <span
                        aria-hidden="true"
                        class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"
                        :class="{{ $isActive ? 'true' : 'false' }} ? 'translate-x-5' : 'translate-x-0'"
                        @click.stop></span>
                </button>
            </div>

            @if($isActive)
            <div class="mt-4 pt-4 border-t border-gray-100">
                <label class="text-xs font-medium text-gray-600 mb-2 block">Lingkungan Aktif</label>
                <div class="flex gap-4">
                    <label class="flex items-center gap-2.5 cursor-pointer group">
                        <input type="radio" wire:model.live="environment" value="sandbox"
                            class="h-4 w-4 text-amber-500 border-gray-300 focus:ring-amber-500">
                        <span class="text-sm font-medium text-gray-700 group-hover:text-amber-600">
                            Sandbox
                            <span class="ml-1 text-xs bg-amber-100 text-amber-700 px-1.5 py-0.5 rounded-full font-normal">Testing</span>
                        </span>
                    </label>
                    <label class="flex items-center gap-2.5 cursor-pointer group">
                        <input type="radio" wire:model.live="environment" value="production"
                            class="h-4 w-4 text-emerald-600 border-gray-300 focus:ring-emerald-500">
                        <span class="text-sm font-medium text-gray-700 group-hover:text-emerald-600">
                            Production
                            <span class="ml-1 text-xs bg-emerald-100 text-emerald-700 px-1.5 py-0.5 rounded-full font-normal">Live</span>
                        </span>
                    </label>
                </div>

                @if($environment === 'production')
                <div class="mt-3 flex items-start gap-2 rounded-lg bg-amber-50 border border-amber-200 p-3 text-xs text-amber-800">
                    <svg class="w-4 h-4 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                    <span>Mode <strong>Production</strong> aktif — data akan dikirim ke sistem SatuSehat resmi. Pastikan kredensial sudah terverifikasi sebelum mengaktifkan.</span>
                </div>
                @endif
            </div>
            @endif
        </div>
    </div>

    {{-- ═══════════════════════════════════════════════════════════════ --}}
    {{-- IDENTITAS FASKES                                               --}}
    {{-- ═══════════════════════════════════════════════════════════════ --}}
    <div class="card">
        <div class="card-header">
            <div>
                <h3 class="text-sm font-semibold text-gray-900">Identitas Faskes</h3>
                <p class="text-xs text-gray-400 mt-0.5">ID organisasi faskes Anda di platform SatuSehat</p>
            </div>
        </div>
        <div class="card-body">
            <div class="form-group max-w-md">
                <label class="form-label">
                    Organization ID
                    <span class="ml-1 text-xs text-gray-400 font-normal">(IHS Organization ID)</span>
                </label>
                <input type="text" wire:model="organizationId"
                    placeholder="Contoh: 10000004"
                    class="form-input font-mono" />
                <p class="text-xs text-gray-400 mt-1">
                    Dapatkan ID ini dari
                    <a href="https://platform.satusehat.kemkes.go.id" target="_blank" class="text-indigo-500 hover:underline">platform.satusehat.kemkes.go.id</a>
                    → menu Organisasi → Manajemen Faskes.
                </p>
            </div>
        </div>
    </div>

    {{-- ═══════════════════════════════════════════════════════════════ --}}
    {{-- KREDENSIAL SANDBOX                                             --}}
    {{-- ═══════════════════════════════════════════════════════════════ --}}
    <div class="card border-amber-200">
        <div class="card-header bg-amber-50">
            <div class="flex items-center justify-between w-full">
                <div class="flex items-center gap-2">
                    <span class="inline-flex items-center gap-1 text-xs bg-amber-100 text-amber-700 px-2 py-0.5 rounded-full font-semibold">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"/>
                        </svg>
                        SANDBOX
                    </span>
                    <h3 class="text-sm font-semibold text-amber-800">Kredensial Sandbox (Testing)</h3>
                </div>

                {{-- Status ping sandbox --}}
                @if($sandboxPingAt)
                <div class="flex items-center gap-1.5 text-xs {{ $sandboxPingStatus === 'ok' ? 'text-emerald-600' : 'text-red-500' }}">
                    <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 24 24">
                        @if($sandboxPingStatus === 'ok')
                        <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        @else
                        <path d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        @endif
                    </svg>
                    <span>Ping {{ $sandboxPingAt->diffForHumans() }}</span>
                </div>
                @endif
            </div>
        </div>
        <div class="card-body space-y-4">
            <div class="text-xs text-gray-500 font-mono bg-gray-50 rounded px-3 py-2 border border-gray-100">
                Base URL: <span class="text-indigo-600">{{ $baseUrlSandbox }}</span>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="form-group">
                    <label class="form-label">Client ID <span class="text-amber-600 text-xs">(Sandbox)</span></label>
                    <input type="text" wire:model="sandboxClientId"
                        placeholder="Contoh: abcdef123456"
                        class="form-input font-mono text-sm" />
                </div>
                <div class="form-group">
                    <label class="form-label">Client Secret <span class="text-amber-600 text-xs">(Sandbox)</span></label>
                    <div x-data="{ show: false }" class="relative">
                        <input :type="show ? 'text' : 'password'" wire:model="sandboxClientSecret"
                            placeholder="••••••••••••••••"
                            class="form-input font-mono text-sm pr-10" />
                        <button type="button" @click="show = !show"
                            class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600">
                            <svg x-show="!show" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                            </svg>
                            <svg x-show="show" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="display:none">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>
                            </svg>
                        </button>
                    </div>
                </div>
            </div>

            {{-- Hasil ping sandbox --}}
            @if($pingEnv === 'sandbox' && $pingStatus)
            <div class="rounded-lg p-3 text-sm flex items-start gap-2
                {{ $pingStatus === 'ok' ? 'bg-emerald-50 border border-emerald-200 text-emerald-800' : 'bg-red-50 border border-red-200 text-red-700' }}">
                <svg class="w-4 h-4 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    @if($pingStatus === 'ok')
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    @else
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    @endif
                </svg>
                <span>{{ $pingMessage }}</span>
            </div>
            @endif

            <div>
                <button
                    wire:click="testKoneksi('sandbox')"
                    wire:loading.attr="disabled"
                    wire:target="testKoneksi"
                    wire:loading.class="opacity-75 cursor-wait"
                    class="btn-secondary text-sm flex items-center gap-2">
                    <svg wire:loading wire:target="testKoneksi('sandbox')" class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                    </svg>
                    <svg wire:loading.remove wire:target="testKoneksi('sandbox')" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                    </svg>
                    <span wire:loading.remove wire:target="testKoneksi('sandbox')">Tes Koneksi Sandbox</span>
                    <span wire:loading wire:target="testKoneksi('sandbox')">Menghubungi server...</span>
                </button>
            </div>
        </div>
    </div>

    {{-- ═══════════════════════════════════════════════════════════════ --}}
    {{-- KREDENSIAL PRODUCTION                                          --}}
    {{-- ═══════════════════════════════════════════════════════════════ --}}
    <div class="card border-emerald-200">
        <div class="card-header bg-emerald-50">
            <div class="flex items-center justify-between w-full">
                <div class="flex items-center gap-2">
                    <span class="inline-flex items-center gap-1 text-xs bg-emerald-100 text-emerald-700 px-2 py-0.5 rounded-full font-semibold">
                        <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 24 24">
                            <path fill-rule="evenodd" d="M2.25 12c0-5.385 4.365-9.75 9.75-9.75s9.75 4.365 9.75 9.75-4.365 9.75-9.75 9.75S2.25 17.385 2.25 12zm13.36-1.814a.75.75 0 10-1.22-.872l-3.236 4.53L9.53 12.22a.75.75 0 00-1.06 1.06l2.25 2.25a.75.75 0 001.14-.094l3.75-5.25z" clip-rule="evenodd"/>
                        </svg>
                        PRODUCTION
                    </span>
                    <h3 class="text-sm font-semibold text-emerald-800">Kredensial Production (Live)</h3>
                </div>

                {{-- Status ping production --}}
                @if($prodPingAt)
                <div class="flex items-center gap-1.5 text-xs {{ $prodPingStatus === 'ok' ? 'text-emerald-600' : 'text-red-500' }}">
                    <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 24 24">
                        @if($prodPingStatus === 'ok')
                        <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        @else
                        <path d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        @endif
                    </svg>
                    <span>Ping {{ $prodPingAt->diffForHumans() }}</span>
                </div>
                @endif
            </div>
        </div>
        <div class="card-body space-y-4">
            <div class="text-xs text-gray-500 font-mono bg-gray-50 rounded px-3 py-2 border border-gray-100">
                Base URL: <span class="text-emerald-600">{{ $baseUrlProd }}</span>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="form-group">
                    <label class="form-label">Client ID <span class="text-emerald-600 text-xs">(Production)</span></label>
                    <input type="text" wire:model="prodClientId"
                        placeholder="Contoh: abcdef123456"
                        class="form-input font-mono text-sm" />
                </div>
                <div class="form-group">
                    <label class="form-label">Client Secret <span class="text-emerald-600 text-xs">(Production)</span></label>
                    <div x-data="{ show: false }" class="relative">
                        <input :type="show ? 'text' : 'password'" wire:model="prodClientSecret"
                            placeholder="••••••••••••••••"
                            class="form-input font-mono text-sm pr-10" />
                        <button type="button" @click="show = !show"
                            class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600">
                            <svg x-show="!show" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                            </svg>
                            <svg x-show="show" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="display:none">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>
                            </svg>
                        </button>
                    </div>
                </div>
            </div>

            {{-- Hasil ping production --}}
            @if($pingEnv === 'production' && $pingStatus)
            <div class="rounded-lg p-3 text-sm flex items-start gap-2
                {{ $pingStatus === 'ok' ? 'bg-emerald-50 border border-emerald-200 text-emerald-800' : 'bg-red-50 border border-red-200 text-red-700' }}">
                <svg class="w-4 h-4 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    @if($pingStatus === 'ok')
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    @else
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    @endif
                </svg>
                <span>{{ $pingMessage }}</span>
            </div>
            @endif

            <div>
                <button
                    wire:click="testKoneksi('production')"
                    wire:loading.attr="disabled"
                    wire:target="testKoneksi"
                    wire:loading.class="opacity-75 cursor-wait"
                    class="btn-secondary text-sm flex items-center gap-2">
                    <svg wire:loading wire:target="testKoneksi('production')" class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                    </svg>
                    <svg wire:loading.remove wire:target="testKoneksi('production')" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                    </svg>
                    <span wire:loading.remove wire:target="testKoneksi('production')">Tes Koneksi Production</span>
                    <span wire:loading wire:target="testKoneksi('production')">Menghubungi server...</span>
                </button>
            </div>
        </div>
    </div>

    {{-- ═══════════════════════════════════════════════════════════════ --}}
    {{-- CATATAN INTERNAL                                               --}}
    {{-- ═══════════════════════════════════════════════════════════════ --}}
    <div class="card">
        <div class="card-header">
            <div>
                <h3 class="text-sm font-semibold text-gray-900">Catatan Internal</h3>
                <p class="text-xs text-gray-400 mt-0.5">Catatan admin untuk keperluan internal (tidak dikirim ke SatuSehat)</p>
            </div>
        </div>
        <div class="card-body">
            <textarea wire:model="catatan" rows="3"
                placeholder="Contoh: Konfigurasi diperbarui oleh admin pada 2026-07-28. Integrasi masih dalam tahap uji coba sandbox."
                class="form-input resize-none"></textarea>
        </div>
    </div>

    {{-- ═══════════════════════════════════════════════════════════════ --}}
    {{-- PANDUAN INTEGRASI                                              --}}
    {{-- ═══════════════════════════════════════════════════════════════ --}}
    <div class="card bg-blue-50 border-blue-200">
        <div class="card-body">
            <h4 class="text-sm font-semibold text-blue-800 mb-3 flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                Cara Mendapatkan Kredensial SatuSehat
            </h4>
            <ol class="text-xs text-blue-700 space-y-1.5 list-decimal list-inside">
                <li>Login ke <strong>platform.satusehat.kemkes.go.id</strong> dengan akun faskes Anda</li>
                <li>Buka menu <strong>Pengembangan Aplikasi → Manajemen Aplikasi</strong></li>
                <li>Buat aplikasi baru atau pilih aplikasi yang sudah ada</li>
                <li>Salin <strong>Client ID</strong> dan <strong>Client Secret</strong> ke form di atas</li>
                <li>Organization ID dapat ditemukan di menu <strong>Manajemen Faskes</strong></li>
                <li>Gunakan <strong>Sandbox</strong> terlebih dahulu untuk pengujian sebelum beralih ke Production</li>
            </ol>
        </div>
    </div>

    {{-- ═══════════════════════════════════════════════════════════════ --}}
    {{-- TOMBOL SIMPAN                                                  --}}
    {{-- ═══════════════════════════════════════════════════════════════ --}}
    <div class="flex justify-end">
        <button
            wire:click="simpan"
            wire:loading.attr="disabled"
            wire:loading.class="opacity-75"
            wire:target="simpan"
            class="btn-primary flex items-center gap-2">
            <svg wire:loading wire:target="simpan" class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
            </svg>
            <span wire:loading.remove wire:target="simpan">Simpan Konfigurasi</span>
            <span wire:loading wire:target="simpan">Menyimpan...</span>
        </button>
    </div>

</div>
