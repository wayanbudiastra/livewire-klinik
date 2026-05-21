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
                       placeholder="Nama, No. RM, NIK, telepon..."
                       class="form-input pl-9 w-72 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200"/>
            </div>
            <select wire:model.live="filterTipe"
                    class="form-select w-32 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200">
                <option value="">Semua Tipe</option>
                <option value="WNI">WNI</option>
                <option value="WNA">WNA</option>
            </select>
        </div>
    </div>

    <div wire:loading.delay class="mb-2 text-sm text-gray-400 flex items-center gap-2">
        <div class="spinner"></div> Memuat...
    </div>

    <div class="table-wrapper">
        <table class="table">
            <thead>
                <tr>
                    <th>
                        <button wire:click="sort('nomor_rm')" class="table-sortable flex items-center gap-1">
                            No. RM @if($sortBy==='nomor_rm') <span class="text-primary-600">{{ $sortDir==='asc'?'↑':'↓' }}</span> @endif
                        </button>
                    </th>
                    <th>
                        <button wire:click="sort('nama')" class="table-sortable flex items-center gap-1">
                            Nama @if($sortBy==='nama') <span class="text-primary-600">{{ $sortDir==='asc'?'↑':'↓' }}</span> @endif
                        </button>
                    </th>
                    <th>Tipe / Identitas</th>
                    <th>Tgl. Lahir / Umur</th>
                    <th>Telepon</th>
                    <th>Kontak Darurat</th>
                    <th>Status</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($this->pasien as $p)
                <tr wire:key="p-{{ $p->id }}">
                    <td class="font-mono text-xs font-semibold text-[#0a3d62] dark:text-blue-400">
                        {{ $p->nomor_rm }}
                    </td>
                    <td>
                        <p class="font-medium text-gray-900 dark:text-gray-100">{{ $p->nama }}</p>
                        <p class="text-xs text-gray-400">{{ $p->jenis_kelamin_label }}</p>
                    </td>
                    <td>
                        <x-tipe-pasien :tipe="$p->tipe_pasien" />
                        <p class="text-xs text-gray-400 mt-1 font-mono">
                            {{ $p->tipe_pasien === 'WNI' ? ($p->nik ?? '-') : ($p->no_paspor ?? '-') }}
                        </p>
                    </td>
                    <td class="text-sm">
                        <p>{{ $p->tanggal_lahir->format('d/m/Y') }}</p>
                        <p class="text-xs text-gray-400">{{ $p->umur }} tahun</p>
                    </td>
                    <td class="text-sm text-gray-600 dark:text-gray-400">{{ $p->telepon }}</td>
                    <td class="text-xs text-gray-500">
                        @if ($p->kontakPrimary)
                            <p class="font-medium text-gray-700 dark:text-gray-300">{{ $p->kontakPrimary->nama }}</p>
                            <p class="text-gray-400">{{ $p->kontakPrimary->nomor_hp }}</p>
                        @else
                            <span class="text-gray-300">—</span>
                        @endif
                    </td>
                    <td>
                        @can('pasien.edit')
                        <button
                            wire:click="toggleActive({{ $p->id }}, {{ $p->is_active ? 'false' : 'true' }})"
                            wire:confirm="{{ $p->is_active ? 'Nonaktifkan' : 'Aktifkan' }} pasien {{ $p->nama }}?"
                            @class([
                                'inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium transition-colors',
                                'bg-emerald-100 text-emerald-700 hover:bg-emerald-200 dark:bg-emerald-900/40 dark:text-emerald-300' => $p->is_active,
                                'bg-red-100 text-red-700 hover:bg-red-200 dark:bg-red-900/40 dark:text-red-300' => !$p->is_active,
                            ])>
                            <span class="h-1.5 w-1.5 rounded-full {{ $p->is_active ? 'bg-emerald-500' : 'bg-red-500' }}"></span>
                            {{ $p->is_active ? 'Aktif' : 'Nonaktif' }}
                        </button>
                        @endcan
                    </td>
                    <td>
                        <div class="flex items-center gap-1">
                            <a href="{{ route('pasien.show', $p) }}" class="btn-info btn-sm">Detail</a>
                            @can('pasien.edit')
                            <a href="{{ route('pasien.edit', $p) }}" class="btn-warning btn-sm">Edit</a>
                            @endcan
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="8">
                        <div class="empty-state">
                            <svg class="empty-state-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                      d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                            </svg>
                            <p class="empty-state-text">Tidak ada data pasien ditemukan</p>
                        </div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4 flex items-center justify-between text-sm text-gray-500 dark:text-gray-400">
        @if ($this->pasien->total() > 0)
        <span>Menampilkan {{ $this->pasien->firstItem() }}–{{ $this->pasien->lastItem() }} dari {{ $this->pasien->total() }} pasien</span>
        {{ $this->pasien->links() }}
        @endif
    </div>
</div>
