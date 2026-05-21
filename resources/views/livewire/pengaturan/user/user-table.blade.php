<div>
    {{-- Toolbar --}}
    <div class="mb-4 flex flex-col sm:flex-row gap-3 justify-between">
        <div class="flex flex-wrap gap-2">

            {{-- Search --}}
            <div class="relative">
                <span class="absolute inset-y-0 left-3 flex items-center text-gray-400 pointer-events-none">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M21 21l-4.35-4.35M17 11A6 6 0 1 1 5 11a6 6 0 0 1 12 0z"/>
                    </svg>
                </span>
                <input
                    wire:model.live.debounce.400ms="search"
                    type="text"
                    placeholder="Cari nama, email, NIP..."
                    class="form-input pl-9 w-64 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200"
                />
            </div>

            {{-- Filter Role --}}
            <select wire:model.live="filterRole"
                    class="form-select w-44 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200">
                <option value="">Semua Role</option>
                @foreach (['admin','dokter','perawat','apoteker','kasir','rekam_medis'] as $r)
                    <option value="{{ $r }}">{{ ucfirst(str_replace('_', ' ', $r)) }}</option>
                @endforeach
            </select>

            {{-- Filter Status --}}
            <select wire:model.live="filterStatus"
                    class="form-select w-36 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200">
                <option value="">Semua Status</option>
                <option value="1">Aktif</option>
                <option value="0">Nonaktif</option>
            </select>
        </div>

        @can('user.create')
        <button wire:click="$dispatch('open-create-user')" class="btn-primary whitespace-nowrap">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            Tambah Pengguna
        </button>
        @endcan
    </div>

    {{-- Loading overlay --}}
    <div wire:loading.delay class="mb-3">
        <div class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400">
            <div class="spinner"></div>
            <span>Memuat data...</span>
        </div>
    </div>

    {{-- Tabel --}}
    <div class="table-wrapper">
        <table class="table">
            <thead>
                <tr>
                    <th>
                        <button wire:click="sort('nama')" class="table-sortable flex items-center gap-1">
                            Nama
                            @if ($sortBy === 'nama')
                                <span class="text-primary-600">{{ $sortDir === 'asc' ? '↑' : '↓' }}</span>
                            @endif
                        </button>
                    </th>
                    <th>Email / NIP</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>
                        <button wire:click="sort('last_login_at')" class="table-sortable flex items-center gap-1">
                            Login Terakhir
                            @if ($sortBy === 'last_login_at')
                                <span class="text-primary-600">{{ $sortDir === 'asc' ? '↑' : '↓' }}</span>
                            @endif
                        </button>
                    </th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($this->users as $user)
                <tr wire:key="user-{{ $user->id }}">
                    {{-- Nama --}}
                    <td>
                        <div class="flex items-center gap-3">
                            <div class="h-9 w-9 flex-shrink-0 rounded-full bg-[#0a3d62]
                                        flex items-center justify-center
                                        text-white text-sm font-bold uppercase">
                                {{ substr($user->nama, 0, 1) }}
                            </div>
                            <div>
                                <p class="font-medium text-gray-900 dark:text-gray-100">{{ $user->nama }}</p>
                                @if ($user->telepon)
                                    <p class="text-xs text-gray-400">{{ $user->telepon }}</p>
                                @endif
                            </div>
                        </div>
                    </td>

                    {{-- Email / NIP --}}
                    <td>
                        <p class="text-sm text-gray-700 dark:text-gray-300">{{ $user->email }}</p>
                        @if ($user->nip)
                            <p class="text-xs text-gray-400 font-mono">NIP: {{ $user->nip }}</p>
                        @endif
                    </td>

                    {{-- Role --}}
                    <td>
                        @php $roleName = $user->roles->first() ? $user->roles->first()->name : '-'; @endphp
                        <span @class([
                            'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium',
                            'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300'     => $roleName === 'dokter',
                            'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300' => $roleName === 'perawat',
                            'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300'   => $roleName === 'apoteker',
                            'bg-violet-100 text-violet-700 dark:bg-violet-900/40 dark:text-violet-300' => $roleName === 'kasir',
                            'bg-sky-100 text-sky-700 dark:bg-sky-900/40 dark:text-sky-300'           => $roleName === 'admin',
                            'bg-rose-100 text-rose-700 dark:bg-rose-900/40 dark:text-rose-300'       => $roleName === 'rekam_medis',
                            'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300'          => !in_array($roleName, ['dokter','perawat','apoteker','kasir','admin','rekam_medis']),
                        ])>
                            {{ ucfirst(str_replace('_', ' ', $roleName)) }}
                        </span>
                    </td>

                    {{-- Status toggle --}}
                    <td>
                        @can('update', $user)
                        <x-confirm-button
                            action="toggleActive({{ $user->id }}, {{ $user->is_active ? 'false' : 'true' }})"
                            title="{{ $user->is_active ? 'Nonaktifkan User?' : 'Aktifkan User?' }}"
                            text="{{ $user->nama }}"
                            icon="{{ $user->is_active ? 'warning' : 'question' }}"
                            confirm="{{ $user->is_active ? 'Ya, Nonaktifkan' : 'Ya, Aktifkan' }}"
                            type="{{ $user->is_active ? 'danger' : 'success' }}"
                            @class([
                                'inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium transition-colors',
                                'bg-emerald-100 text-emerald-700 hover:bg-emerald-200 dark:bg-emerald-900/40 dark:text-emerald-300' => $user->is_active,
                                'bg-red-100 text-red-700 hover:bg-red-200 dark:bg-red-900/40 dark:text-red-300' => !$user->is_active,
                            ])>
                            <span class="h-1.5 w-1.5 rounded-full {{ $user->is_active ? 'bg-emerald-500' : 'bg-red-500' }}"></span>
                            {{ $user->is_active ? 'Aktif' : 'Nonaktif' }}
                        </x-confirm-button>
                        @else
                        <span @class([
                            'inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium',
                            'bg-emerald-100 text-emerald-700' => $user->is_active,
                            'bg-red-100 text-red-700'         => !$user->is_active,
                        ])>
                            {{ $user->is_active ? 'Aktif' : 'Nonaktif' }}
                        </span>
                        @endcan
                    </td>

                    {{-- Login Terakhir --}}
                    <td class="text-xs text-gray-400 dark:text-gray-500">
                        {{ $user->last_login_at ? $user->last_login_at->diffForHumans() : 'Belum pernah' }}
                    </td>

                    {{-- Aksi --}}
                    <td>
                        <div class="flex items-center gap-1">
                            @can('update', $user)
                            <button
                                wire:click="$dispatch('open-edit-user', { userId: {{ $user->id }} })"
                                class="btn-info btn-sm"
                                title="Edit">
                                Edit
                            </button>
                            @endcan

                            @can('resetPassword', $user)
                            <button
                                wire:click="$dispatch('open-reset-password', { userId: {{ $user->id }} })"
                                class="btn-warning btn-sm"
                                title="Reset Password">
                                Reset PW
                            </button>
                            @endcan

                            @can('delete', $user)
                            <x-confirm-button
                                action="deleteUser({{ $user->id }})"
                                title="Hapus User?"
                                text="User {{ $user->nama }} akan dihapus permanen dan tidak dapat dikembalikan."
                                confirm="Ya, Hapus Permanen"
                                type="danger"
                                class="btn-danger btn-sm">
                                Hapus
                            </x-confirm-button>
                            @endcan
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6">
                        <div class="empty-state">
                            <svg class="empty-state-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                      d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                            </svg>
                            <p class="empty-state-text">Tidak ada pengguna ditemukan</p>
                            @if ($search || $filterRole || $filterStatus !== '')
                                <button wire:click="$set('search', ''); $set('filterRole', ''); $set('filterStatus', '')"
                                        class="mt-2 text-xs text-primary-600 hover:underline dark:text-primary-400">
                                    Reset filter
                                </button>
                            @endif
                        </div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Pagination --}}
    @if ($this->users->hasPages())
    <div class="mt-4 flex items-center justify-between text-sm text-gray-500 dark:text-gray-400">
        <span>
            Menampilkan {{ $this->users->firstItem() }}–{{ $this->users->lastItem() }}
            dari {{ $this->users->total() }} pengguna
        </span>
        {{ $this->users->links() }}
    </div>
    @else
    <div class="mt-3 text-xs text-gray-400 dark:text-gray-500">
        Total {{ $this->users->total() }} pengguna
    </div>
    @endif
</div>
