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
            </select>
        </div>
    </div>

    <div class="table-wrapper">
        <table class="table">
            <thead>
                <tr>
                    <th>Dokter</th>
                    <th>NIK / No. SIP</th>
                    <th>Status SIP</th>
                    <th>Spesialisasi</th>
                    <th>Poli</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($this->dokter as $d)
                <tr wire:key="dok-{{ $d->id }}">
                    <td>
                        <div class="flex items-center gap-3">
                            <div class="h-9 w-9 flex-shrink-0 rounded-full bg-[#0a3d62]
                                        flex items-center justify-center text-white text-sm font-bold uppercase">
                                {{ substr($d->user->nama, 0, 1) }}
                            </div>
                            <div>
                                <p class="font-medium text-gray-900 dark:text-gray-100">{{ $d->user->nama }}</p>
                                <p class="text-xs text-gray-400">{{ $d->user->email }}</p>
                            </div>
                        </div>
                    </td>
                    <td class="text-sm text-gray-600 dark:text-gray-400">
                        <p class="font-mono text-xs">{{ $d->nik ?? '-' }}</p>
                        <p class="text-xs text-gray-400">{{ $d->no_sip ?? 'SIP belum diisi' }}</p>
                    </td>
                    <td><x-sip-status :dokter="$d" /></td>
                    <td class="text-sm text-gray-600 dark:text-gray-400">
                        {{ $d->spesialisasi ?? 'Umum' }}
                    </td>
                    <td>
                        <div class="flex flex-wrap gap-1">
                            @foreach ($d->poli->take(3) as $p)
                                <span class="badge-primary">{{ $p->kode }}</span>
                            @endforeach
                            @if ($d->poli->count() > 3)
                                <span class="badge-gray">+{{ $d->poli->count() - 3 }}</span>
                            @endif
                            @if ($d->poli->isEmpty())
                                <span class="badge-danger">Belum mapping</span>
                            @endif
                        </div>
                    </td>
                    <td>
                        <div class="flex items-center gap-1">
                            <a href="{{ route('pengaturan.dokter.show', $d) }}"
                               class="btn-info btn-sm">Detail</a>
                            @can('masterdata.edit')
                            <button wire:click="$dispatch('open-dokter-profil', { id: {{ $d->id }} })"
                                    class="btn-warning btn-sm">Edit Profil</button>
                            @endcan
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6">
                        <div class="empty-state">
                            <p class="empty-state-text">Belum ada data dokter</p>
                        </div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $this->dokter->links() }}</div>
</div>
