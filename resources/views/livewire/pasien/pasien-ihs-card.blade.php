<div>
    @if($satusehatAktif && $pasien)
    <div class="card">
        <div class="card-header">
            <div class="flex items-center gap-2">
                <svg class="w-4 h-4 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                </svg>
                <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-100">IHS SatuSehat</h3>
            </div>
            <span class="text-xs px-2 py-0.5 rounded-full font-medium
                {{ $environment === 'production'
                    ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300'
                    : 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300' }}">
                {{ strtoupper($environment) }}
            </span>
        </div>
        <div class="card-body space-y-3 text-sm">

            {{-- Status --}}
            <div class="flex justify-between items-start">
                <span class="text-gray-500">Status</span>
                @if($pasien->ihs_status === 'ditemukan')
                    <span class="inline-flex items-center gap-1 text-emerald-600 dark:text-emerald-400 font-medium">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                        </svg>
                        Ditemukan
                    </span>
                @elseif($pasien->ihs_status === 'tidak_ditemukan')
                    <span class="inline-flex items-center gap-1 text-amber-600 dark:text-amber-400">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                        </svg>
                        Tidak ditemukan
                    </span>
                @elseif($pasien->ihs_status === 'error')
                    <span class="inline-flex items-center gap-1 text-red-500 dark:text-red-400">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                        </svg>
                        Error
                    </span>
                @else
                    <span class="text-gray-400">Belum diambil</span>
                @endif
            </div>

            {{-- IHS ID --}}
            @if($pasien->ihs_id)
            <div class="flex justify-between items-center">
                <span class="text-gray-500">IHS ID</span>
                <span class="font-mono text-xs font-semibold text-indigo-600 dark:text-indigo-400 bg-indigo-50 dark:bg-indigo-900/30 px-2 py-0.5 rounded">
                    {{ $pasien->ihs_id }}
                </span>
            </div>
            @endif

            {{-- Error message --}}
            @if($pasien->ihs_error_msg && $pasien->ihs_status !== 'ditemukan')
            <div class="text-xs text-red-500 dark:text-red-400 bg-red-50 dark:bg-red-900/20 rounded p-2">
                {{ $pasien->ihs_error_msg }}
            </div>
            @endif

            {{-- Last sync --}}
            @if($pasien->ihs_synced_at)
            <div class="flex justify-between text-xs">
                <span class="text-gray-400">Terakhir sync</span>
                <span class="text-gray-500">{{ $pasien->ihs_synced_at->format('d/m/Y H:i') }}</span>
            </div>
            @endif

            {{-- Tombol fetch --}}
            @if(!$pasien->nik && $pasien->tipe_pasien === 'WNI')
            <div class="pt-2 border-t border-gray-100 dark:border-gray-700">
                <p class="text-xs text-amber-600 dark:text-amber-400">
                    NIK belum diisi.
                    <a href="{{ route('pasien.edit', $pasien) }}" class="underline">Edit data pasien</a>
                    untuk mengisi NIK sebelum mengambil IHS.
                </p>
            </div>
            @else
            <div class="pt-2 border-t border-gray-100 dark:border-gray-700">
                <button wire:click="fetchIhs"
                        wire:loading.attr="disabled"
                        wire:target="fetchIhs"
                        class="w-full btn-secondary text-xs flex items-center justify-center gap-2">
                    <svg wire:loading wire:target="fetchIhs" class="animate-spin h-3.5 w-3.5" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                    </svg>
                    <svg wire:loading.remove wire:target="fetchIhs" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                    </svg>
                    <span wire:loading.remove wire:target="fetchIhs">
                        {{ $pasien->ihs_status === 'ditemukan' ? 'Refresh IHS' : 'Ambil IHS' }}
                    </span>
                    <span wire:loading wire:target="fetchIhs">Menghubungi SatuSehat...</span>
                </button>
            </div>
            @endif

        </div>
    </div>
    @endif
</div>
