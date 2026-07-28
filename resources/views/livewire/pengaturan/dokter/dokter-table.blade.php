<div>
    <div class="mb-4 flex flex-col sm:flex-row gap-3 justify-between">
        <div class="flex flex-wrap gap-2">
            <div class="relative">
                <span class="absolute inset-y-0 left-3 flex items-center text-gray-400 pointer-events-none">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 11A6 6 0 1 1 5 11a6 6 0 0 1 12 0z"/>
                    </svg>
                </span>
                <input wire:model.live.debounce.400ms="search" type="text"
                       placeholder="Cari nama, email..."
                       class="form-input pl-9 w-64 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200"/>
            </div>
            <select wire:model.live="filterSip"
                    class="form-select w-44 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200">
                <option value="">Semua Status SIP</option>
                <option value="aktif">SIP Aktif</option>
                <option value="segera_expired">Segera Expired</option>
                <option value="expired">SIP Expired</option>
                <option value="belum_setup">Belum Setup Profil</option>
            </select>
        </div>

        {{-- Tombol Ambil Semua IHS --}}
        @if($satusehatAktif)
        <button wire:click="fetchIhsSemua"
                wire:loading.attr="disabled"
                wire:target="fetchIhsSemua"
                @disabled($ihsRunning)
                class="btn-secondary text-sm flex items-center gap-2 whitespace-nowrap">
            <svg wire:loading wire:target="fetchIhsSemua" class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
            </svg>
            <svg wire:loading.remove wire:target="fetchIhsSemua" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
            </svg>
            <span wire:loading.remove wire:target="fetchIhsSemua">Ambil Semua IHS Dokter</span>
            <span wire:loading wire:target="fetchIhsSemua">Memproses...</span>
        </button>
        @endif
    </div>

    {{-- Progress IHS bulk --}}
    @if($satusehatAktif && ($ihsRunning || $ihsSelesai))
    <div class="mb-4 rounded-xl border p-4 text-sm
        {{ $ihsSelesai ? 'bg-emerald-50 border-emerald-200 dark:bg-emerald-900/10 dark:border-emerald-700' : 'bg-blue-50 border-blue-200 dark:bg-blue-900/10 dark:border-blue-700' }}">
        <div class="flex items-center justify-between mb-2">
            <span class="font-medium {{ $ihsSelesai ? 'text-emerald-700 dark:text-emerald-400' : 'text-blue-700 dark:text-blue-400' }}">
                {{ $ihsSelesai ? '✓ Selesai' : 'Mengambil IHS Dokter...' }}
            </span>
            @if($ihsSelesai)
            <button wire:click="resetIhsBulk" class="text-xs text-gray-400 hover:text-gray-600">Tutup</button>
            @endif
        </div>
        @if($ihsTotal > 0)
        <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2 mb-2">
            <div class="h-2 rounded-full transition-all {{ $ihsSelesai ? 'bg-emerald-500' : 'bg-blue-500' }}"
                 style="width: {{ $ihsTotal > 0 ? round(($ihsDone / $ihsTotal) * 100) : 0 }}%"></div>
        </div>
        <div class="flex gap-4 text-xs text-gray-600 dark:text-gray-400">
            <span>{{ $ihsDone }}/{{ $ihsTotal }} diproses</span>
            <span class="text-emerald-600 dark:text-emerald-400">✓ {{ $ihsOk }} berhasil</span>
            @if($ihsGagal > 0)<span class="text-red-500">✗ {{ $ihsGagal }} gagal</span>@endif
        </div>
        @endif
    </div>
    @endif

    <div class="table-wrapper">
        <table class="table">
            <thead>
                <tr>
                    <th>Nama Dokter</th>
                    <th>NIK / No. SIP</th>
                    <th>Status SIP</th>
                    <th>Spesialisasi</th>
                    <th>Poli</th>
                    @if($satusehatAktif)<th>IHS Status</th>@endif
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($this->dokter as $user)
                <tr wire:key="dok-{{ $user->id }}">
                    <td>
                        <div class="flex items-center gap-3">
                            <div class="h-9 w-9 flex-shrink-0 rounded-full bg-[#0a3d62] flex items-center justify-center text-white text-sm font-bold uppercase">
                                {{ substr($user->nama, 0, 1) }}
                            </div>
                            <div>
                                <p class="font-medium text-gray-900 dark:text-gray-100">{{ $user->nama }}</p>
                                <p class="text-xs text-gray-400">{{ $user->email }}</p>
                            </div>
                        </div>
                    </td>
                    <td class="text-sm">
                        @if ($user->dokter)
                            <p class="font-mono text-xs text-gray-600 dark:text-gray-400">{{ $user->dokter->nik ?? '-' }}</p>
                            <p class="text-xs text-gray-400">{{ $user->dokter->no_sip ?? 'SIP belum diisi' }}</p>
                        @else
                            <span class="text-xs text-gray-400 italic">Profil belum dibuat</span>
                        @endif
                    </td>
                    <td>
                        @if ($user->dokter)
                            <x-sip-status :dokter="$user->dokter" />
                        @else
                            <span class="badge-gray">Belum Setup</span>
                        @endif
                    </td>
                    <td class="text-sm text-gray-600 dark:text-gray-400">
                        {{ $user->dokter ? ($user->dokter->spesialisasi ?? '-') : '-' }}
                    </td>
                    <td>
                        @if ($user->dokter)
                            <div class="flex flex-wrap gap-1">
                                @foreach ($user->dokter->poli->take(3) as $p)
                                    <span class="badge-primary">{{ $p->kode }}</span>
                                @endforeach
                                @if ($user->dokter->poli->count() > 3)
                                    <span class="badge-gray">+{{ $user->dokter->poli->count() - 3 }}</span>
                                @endif
                                @if ($user->dokter->poli->isEmpty())
                                    <span class="badge-warning text-xs">Belum mapping</span>
                                @endif
                            </div>
                        @else
                            <span class="text-xs text-gray-400">—</span>
                        @endif
                    </td>

                    {{-- IHS Status --}}
                    @if($satusehatAktif)
                    <td>
                        @if($user->dokter)
                            @if($user->dokter->ihs_status === 'ditemukan')
                                <span class="inline-flex items-center gap-1 text-xs text-emerald-700 dark:text-emerald-400 font-mono">
                                    <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                    </svg>
                                    {{ Str::limit($user->dokter->ihs_id, 10) }}
                                </span>
                            @elseif($user->dokter->ihs_status === 'tidak_ditemukan')
                                <span class="text-xs text-amber-600">Tidak ditemukan</span>
                            @elseif($user->dokter->ihs_status === 'error')
                                <span class="text-xs text-red-500" title="{{ $user->dokter->ihs_error_msg }}">Error</span>
                            @else
                                <span class="text-xs text-gray-400">Belum diambil</span>
                            @endif
                        @else
                            <span class="text-xs text-gray-300">—</span>
                        @endif
                    </td>
                    @endif

                    <td>
                        <div class="flex items-center gap-1">
                            @if ($user->dokter)
                                <a href="{{ route('pengaturan.dokter.show', $user->dokter->id) }}" class="btn-info btn-sm">Detail</a>
                                @can('masterdata.edit')
                                <button wire:click="$dispatch('open-dokter-profil', { id: {{ $user->dokter->id }} })" class="btn-warning btn-sm">Edit</button>
                                @endcan
                                @if($satusehatAktif)
                                <button wire:click="fetchIhsSatu({{ $user->dokter->id }})"
                                        wire:loading.attr="disabled"
                                        wire:target="fetchIhsSatu({{ $user->dokter->id }})"
                                        title="Ambil / Refresh IHS"
                                        class="btn-sm inline-flex items-center justify-center w-7 h-7 rounded bg-indigo-50 text-indigo-600 hover:bg-indigo-100 dark:bg-indigo-900/30 dark:text-indigo-400 border border-indigo-200 dark:border-indigo-700">
                                    <svg wire:loading.remove wire:target="fetchIhsSatu({{ $user->dokter->id }})" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                                    </svg>
                                    <svg wire:loading wire:target="fetchIhsSatu({{ $user->dokter->id }})" class="animate-spin w-3.5 h-3.5" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                                    </svg>
                                </button>
                                @endif
                            @else
                                @can('masterdata.edit')
                                <button wire:click="setupProfil({{ $user->id }})" wire:loading.attr="disabled" class="btn-primary btn-sm">
                                    <span wire:loading.remove wire:target="setupProfil({{ $user->id }})">Setup Profil</span>
                                    <span wire:loading wire:target="setupProfil({{ $user->id }})">...</span>
                                </button>
                                @endcan
                            @endif
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="{{ $satusehatAktif ? 7 : 6 }}">
                        <div class="empty-state">
                            <svg class="empty-state-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                            </svg>
                            <p class="empty-state-text">Belum ada user dengan role Dokter</p>
                        </div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4 flex items-center justify-between text-sm text-gray-500 dark:text-gray-400">
        @if ($this->dokter->total() > 0)
        <span>{{ $this->dokter->total() }} dokter terdaftar</span>
        {{ $this->dokter->links() }}
        @endif
    </div>
</div>
