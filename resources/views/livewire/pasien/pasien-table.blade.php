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
            <span wire:loading.remove wire:target="fetchIhsSemua">Ambil Semua IHS</span>
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
                {{ $ihsSelesai ? '✓ Selesai' : 'Mengambil IHS...' }}
            </span>
            @if($ihsSelesai)
            <button wire:click="resetIhsBulk" class="text-xs text-gray-400 hover:text-gray-600">Tutup</button>
            @endif
        </div>

        @if($ihsTotal > 0)
        <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2 mb-2">
            <div class="h-2 rounded-full transition-all duration-300
                {{ $ihsSelesai ? 'bg-emerald-500' : 'bg-blue-500' }}"
                 style="width: {{ $ihsTotal > 0 ? round(($ihsDone / $ihsTotal) * 100) : 0 }}%">
            </div>
        </div>
        <div class="flex gap-4 text-xs text-gray-600 dark:text-gray-400">
            <span>{{ $ihsDone }}/{{ $ihsTotal }} diproses</span>
            <span class="text-emerald-600 dark:text-emerald-400">✓ {{ $ihsOk }} berhasil</span>
            @if($ihsGagal > 0)
            <span class="text-red-500">✗ {{ $ihsGagal }} gagal</span>
            @endif
        </div>
        @endif
    </div>
    @endif

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
                    @if($satusehatAktif)
                    <th>IHS Status</th>
                    @endif
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

                    {{-- Kolom IHS (hanya saat SatuSehat aktif) --}}
                    @if($satusehatAktif)
                    <td>
                        @if($p->ihs_status === 'ditemukan')
                            <span class="inline-flex items-center gap-1 text-xs text-emerald-700 dark:text-emerald-400 font-mono">
                                <svg class="w-3.5 h-3.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                </svg>
                                {{ Str::limit($p->ihs_id, 10) }}
                            </span>
                        @elseif($p->ihs_status === 'tidak_ditemukan')
                            <span class="inline-flex items-center gap-1 text-xs text-amber-600 dark:text-amber-400">
                                <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                </svg>
                                Tidak ditemukan
                            </span>
                        @elseif($p->ihs_status === 'error')
                            <span class="inline-flex items-center gap-1 text-xs text-red-500 dark:text-red-400" title="{{ $p->ihs_error_msg }}">
                                <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                                </svg>
                                Error
                            </span>
                        @else
                            <span class="text-xs text-gray-400">Belum diambil</span>
                        @endif
                    </td>
                    @endif

                    <td>
                        @can('pasien.edit')
                        <x-confirm-button
                            action="toggleActive({{ $p->id }}, {{ $p->is_active ? 'false' : 'true' }})"
                            title="{{ $p->is_active ? 'Nonaktifkan Pasien?' : 'Aktifkan Pasien?' }}"
                            text="Pasien: {{ $p->nama }}"
                            icon="{{ $p->is_active ? 'warning' : 'question' }}"
                            confirm="{{ $p->is_active ? 'Ya, Nonaktifkan' : 'Ya, Aktifkan' }}"
                            type="{{ $p->is_active ? 'danger' : 'success' }}"
                            @class([
                                'inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium transition-colors',
                                'bg-emerald-100 text-emerald-700 hover:bg-emerald-200 dark:bg-emerald-900/40 dark:text-emerald-300' => $p->is_active,
                                'bg-red-100 text-red-700 hover:bg-red-200 dark:bg-red-900/40 dark:text-red-300' => !$p->is_active,
                            ])>
                            <span class="h-1.5 w-1.5 rounded-full {{ $p->is_active ? 'bg-emerald-500' : 'bg-red-500' }}"></span>
                            {{ $p->is_active ? 'Aktif' : 'Nonaktif' }}
                        </x-confirm-button>
                        @endcan
                    </td>
                    <td>
                        <div class="flex items-center gap-1">
                            <a href="{{ route('pasien.show', $p) }}" class="btn-info btn-sm">Detail</a>
                            @can('pasien.edit')
                            <a href="{{ route('pasien.edit', $p) }}" class="btn-warning btn-sm">Edit</a>
                            @endcan
                            @if($satusehatAktif)
                            <button wire:click="fetchIhsSatu({{ $p->id }})"
                                    wire:loading.attr="disabled"
                                    wire:target="fetchIhsSatu({{ $p->id }})"
                                    title="Ambil / Refresh IHS"
                                    class="btn-sm inline-flex items-center justify-center w-7 h-7 rounded bg-indigo-50 text-indigo-600 hover:bg-indigo-100 dark:bg-indigo-900/30 dark:text-indigo-400 border border-indigo-200 dark:border-indigo-700">
                                <svg wire:loading.remove wire:target="fetchIhsSatu({{ $p->id }})" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                                </svg>
                                <svg wire:loading wire:target="fetchIhsSatu({{ $p->id }})" class="animate-spin w-3.5 h-3.5" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                                </svg>
                            </button>
                            @endif
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="{{ $satusehatAktif ? 9 : 8 }}">
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
