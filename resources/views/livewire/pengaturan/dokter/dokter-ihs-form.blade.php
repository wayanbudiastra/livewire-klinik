<div>
    @if($satusehatAktif && $dokter)
    <div class="space-y-5 max-w-2xl">

        {{-- Header badge environment --}}
        <div class="flex items-center gap-3">
            <span class="text-xs px-2.5 py-1 rounded-full font-medium
                {{ $environment === 'production'
                    ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300'
                    : 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300' }}">
                {{ strtoupper($environment) }}
            </span>
            <p class="text-xs text-gray-400">Endpoint: <span class="font-mono">{{ $baseUrl }}</span></p>
        </div>

        {{-- Status IHS --}}
        <div class="card">
            <div class="card-header">
                <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-100">Status IHS</h3>
            </div>
            <div class="card-body space-y-3 text-sm">
                <div class="flex justify-between items-center">
                    <span class="text-gray-500">Status</span>
                    @if($dokter->ihs_status === 'ditemukan')
                        <span class="inline-flex items-center gap-1.5 text-emerald-600 dark:text-emerald-400 font-medium">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                            </svg>
                            Ditemukan
                        </span>
                    @elseif($dokter->ihs_status === 'tidak_ditemukan')
                        <span class="inline-flex items-center gap-1.5 text-amber-600 dark:text-amber-400">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                            </svg>
                            Tidak ditemukan
                        </span>
                    @elseif($dokter->ihs_status === 'error')
                        <span class="inline-flex items-center gap-1.5 text-red-500 dark:text-red-400">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                            </svg>
                            Error
                        </span>
                    @else
                        <span class="text-gray-400">Belum diambil</span>
                    @endif
                </div>

                @if($dokter->ihs_id)
                <div class="flex justify-between items-center">
                    <span class="text-gray-500">IHS ID</span>
                    <span class="font-mono text-xs font-semibold text-indigo-600 dark:text-indigo-400 bg-indigo-50 dark:bg-indigo-900/30 px-2 py-0.5 rounded">
                        {{ $dokter->ihs_id }}
                    </span>
                </div>
                @endif

                @if($dokter->ihs_error_msg && $dokter->ihs_status !== 'ditemukan')
                <div class="text-xs text-red-500 dark:text-red-400 bg-red-50 dark:bg-red-900/20 rounded p-2">
                    {{ $dokter->ihs_error_msg }}
                </div>
                @endif

                @if($dokter->ihs_synced_at)
                <div class="flex justify-between text-xs">
                    <span class="text-gray-400">Terakhir sync</span>
                    <span class="text-gray-500">{{ $dokter->ihs_synced_at->format('d/m/Y H:i') }}</span>
                </div>
                @endif
            </div>
        </div>

        {{-- NIK & Fetch --}}
        <div class="card">
            <div class="card-header">
                <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-100">NIK Dokter</h3>
                <p class="text-xs text-gray-400">Digunakan untuk pencarian Practitioner di FHIR SatuSehat</p>
            </div>
            <div class="card-body space-y-4">
                <div>
                    <label class="form-label">NIK (16 digit)</label>
                    <div class="flex gap-2">
                        <input wire:model="nik" type="text" inputmode="numeric" maxlength="16"
                               placeholder="Kosongkan jika tidak ada"
                               class="form-input font-mono flex-1"/>
                        <button wire:click="simpanNik"
                                wire:loading.attr="disabled"
                                wire:target="simpanNik"
                                class="btn-primary whitespace-nowrap">
                            <span wire:loading.remove wire:target="simpanNik">Simpan NIK</span>
                            <span wire:loading wire:target="simpanNik">Menyimpan...</span>
                        </button>
                    </div>
                    @error('nik')
                    <p class="form-error">{{ $message }}</p>
                    @enderror
                    <p class="text-xs text-gray-400 mt-1">NIK digunakan untuk pencarian IHS via endpoint FHIR Practitioner.</p>
                </div>

                @if(!$dokter->nik)
                <div class="rounded-lg bg-amber-50 dark:bg-amber-900/10 border border-amber-200 dark:border-amber-700 p-3">
                    <p class="text-xs text-amber-700 dark:text-amber-300">
                        NIK belum diisi. Isi dan simpan NIK terlebih dahulu sebelum mengambil IHS.
                    </p>
                </div>
                @else
                <button wire:click="fetchIhs"
                        wire:loading.attr="disabled"
                        wire:target="fetchIhs"
                        class="w-full btn-secondary text-sm flex items-center justify-center gap-2">
                    <svg wire:loading wire:target="fetchIhs" class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                    </svg>
                    <svg wire:loading.remove wire:target="fetchIhs" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                    </svg>
                    <span wire:loading.remove wire:target="fetchIhs">
                        {{ $dokter->ihs_status === 'ditemukan' ? 'Refresh IHS' : 'Ambil IHS dari SatuSehat' }}
                    </span>
                    <span wire:loading wire:target="fetchIhs">Menghubungi SatuSehat...</span>
                </button>
                @endif
            </div>
        </div>

    </div>
    @else
    <div class="rounded-xl border border-gray-200 dark:border-gray-700 p-8 text-center text-sm text-gray-400">
        Integrasi SatuSehat belum diaktifkan. Aktifkan terlebih dahulu di
        <a href="{{ route('pengaturan.satusehat') }}" class="text-indigo-500 hover:underline">Konfigurasi SatuSehat</a>.
    </div>
    @endif
</div>
