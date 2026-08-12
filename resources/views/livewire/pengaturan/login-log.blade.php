<div>
    {{-- Filter --}}
    <div class="mb-4 flex flex-wrap items-end gap-3">
        <div class="form-group">
            <label class="form-label dark:text-gray-300">Dari Tanggal</label>
            <input wire:model.live="tanggalMulai" type="date" max="{{ now()->format('Y-m-d') }}"
                   class="form-input dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200"/>
        </div>
        <div class="form-group">
            <label class="form-label dark:text-gray-300">Sampai Tanggal</label>
            <input wire:model.live="tanggalSelesai" type="date" max="{{ now()->format('Y-m-d') }}"
                   class="form-input dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200"/>
        </div>
        <p class="text-xs text-gray-400 pb-2">Rentang maksimal 30 hari.</p>
    </div>

    <div wire:loading.delay class="mb-2 text-sm text-gray-400 flex items-center gap-2">
        <div class="spinner"></div> Memuat...
    </div>

    <div class="table-wrapper">
        <table class="table">
            <thead>
                <tr>
                    <th>Waktu Login</th>
                    <th>Nama</th>
                    <th>Email</th>
                    <th>IP Address</th>
                    <th>User Agent</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($this->logs as $log)
                <tr wire:key="login-log-{{ $log->id }}">
                    <td class="text-sm text-gray-700 dark:text-gray-300 whitespace-nowrap">
                        {{ $log->created_at->format('d/m/Y H:i:s') }}
                    </td>
                    <td class="font-medium text-gray-900 dark:text-gray-100">
                        {{ $log->causer?->nama ?? '(user dihapus)' }}
                    </td>
                    <td class="text-sm text-gray-500">{{ $log->causer?->email ?? '-' }}</td>
                    <td class="font-mono text-xs text-gray-500">{{ $log->getExtraProperty('ip_address') ?? '-' }}</td>
                    <td class="text-xs text-gray-400 max-w-xs truncate" title="{{ $log->getExtraProperty('user_agent') }}">
                        {{ $log->getExtraProperty('user_agent') ?? '-' }}
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="5">
                        <div class="empty-state">
                            <svg class="empty-state-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                      d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"/>
                            </svg>
                            <p class="empty-state-text">Tidak ada data login di rentang tanggal ini</p>
                        </div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $this->logs->links() }}</div>
</div>
