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
    </div>

    <div class="table-wrapper">
        <table class="table">
            <thead>
                <tr>
                    <th>Nama Dokter</th>
                    <th>NIK / No. SIP</th>
                    <th>Status SIP</th>
                    <th>Spesialisasi</th>
                    <th>Poli</th>
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
                    <td>
                        <div class="flex items-center gap-1">
                            @if ($user->dokter)
                                <a href="{{ route('pengaturan.dokter.show', $user->dokter->id) }}" class="btn-info btn-sm">Detail</a>
                                @can('masterdata.edit')
                                <button wire:click="$dispatch('open-dokter-profil', { id: {{ $user->dokter->id }} })" class="btn-warning btn-sm">Edit</button>
                                @endcan
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
                    <td colspan="6">
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
