<div>
    {{-- Toolbar --}}
    <div class="mb-4 flex flex-col sm:flex-row gap-3 justify-between">
        <div class="flex flex-wrap gap-2">
            <input wire:model.live="tanggal" type="date"
                   class="form-input w-44 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200"/>
            <div class="relative">
                <span class="absolute inset-y-0 left-3 flex items-center text-gray-400 pointer-events-none">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 11A6 6 0 1 1 5 11a6 6 0 0 1 12 0z"/>
                    </svg>
                </span>
                <input wire:model.live.debounce.400ms="search" type="text"
                       placeholder="Nama / No. RM..."
                       class="form-input pl-9 w-52 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200"/>
            </div>
            <select wire:model.live="filterStatus"
                    class="form-select w-40 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200">
                <option value="">Semua Status</option>
                <option value="menunggu">Menunggu</option>
                <option value="dalam_pemeriksaan">Diperiksa</option>
                <option value="selesai">Selesai</option>
                <option value="dibatalkan">Dibatalkan</option>
            </select>
        </div>
        <div class="text-sm text-gray-500 dark:text-gray-400 flex items-center">
            Total: <span class="font-semibold ml-1">{{ $this->kunjungan->total() }}</span>
        </div>
    </div>

    <div class="table-wrapper">
        <table class="table">
            <thead>
                <tr>
                    <th>No. Antrean</th>
                    <th>Pasien</th>
                    <th>Dokter / Poli</th>
                    <th>Jam Daftar</th>
                    <th>Penjamin</th>
                    <th>Sumber</th>
                    <th>Status</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($this->kunjungan as $k)
                @php $isAppointment = str_starts_with($k->nomor_antrean, 'A-'); @endphp
                <tr wire:key="k-{{ $k->id }}" @class([
                    'border-l-4 border-l-blue-400' => $isAppointment,
                    'border-l-4 border-l-gray-200 dark:border-l-gray-600' => !$isAppointment,
                ])>
                    <td>
                        <div class="flex flex-col items-start gap-1">
                            <span @class([
                                'font-mono text-xl font-black',
                                'text-[#0a3d62] dark:text-blue-400' => $isAppointment,
                                'text-gray-600 dark:text-gray-400'  => !$isAppointment,
                            ])>
                                {{ $k->nomor_antrean }}
                            </span>
                            @if ($isAppointment)
                            <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] font-bold
                                         bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300 uppercase tracking-wide">
                                ★ Prioritas
                            </span>
                            @endif
                        </div>
                    </td>
                    <td>
                        <p class="font-medium text-gray-900 dark:text-gray-100">{{ $k->pasien?->nama ?? '-' }}</p>
                        <p class="text-xs font-mono text-gray-400">{{ $k->pasien?->nomor_rm }}</p>
                    </td>
                    <td>
                        <p class="text-sm text-gray-700 dark:text-gray-300">{{ $k->dokter?->user?->nama ?? '-' }}</p>
                        <p class="text-xs text-gray-400">{{ $k->poli?->nama ?? '-' }}</p>
                    </td>
                    <td class="text-sm text-gray-600 dark:text-gray-400">
                        {{ $k->tanggal->format('H:i') }}
                    </td>
                    <td>
                        @php $penjamin = $k->tipe_pembayaran ?? 'umum'; @endphp
                        <span @class([
                            'badge',
                            'badge-primary' => $penjamin === 'umum',
                            'badge-success' => $penjamin === 'bpjs',
                            'badge-warning' => $penjamin === 'asuransi',
                        ])>{{ strtoupper($penjamin) }}</span>
                    </td>
                    <td class="text-xs text-gray-500 dark:text-gray-400">
                        @if ($isAppointment)
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium
                                         bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300">
                                📅 Appointment
                            </span>
                            <p class="font-mono text-xs mt-0.5 text-gray-400">{{ $k->appointment?->kode_booking }}</p>
                        @else
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium
                                         bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400">
                                🚶 Walk-in
                            </span>
                        @endif
                    </td>
                    <td><x-badge-status :status="$k->status" /></td>
                    <td>
                        @if ($k->status === 'menunggu')
                        @can('kunjungan.edit')
                        <x-confirm-button
                            action="cancel({{ $k->id }})"
                            title="Batalkan Kunjungan?"
                            text="Kunjungan pasien {{ $k->pasien?->nama }} akan dibatalkan."
                            confirm="Ya, Batalkan"
                            type="danger"
                            class="btn-danger btn-sm">
                            Cancel
                        </x-confirm-button>
                        @endcan
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="8">
                        <div class="empty-state py-12">
                            <svg class="empty-state-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                      d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/>
                            </svg>
                            <p class="empty-state-text">Belum ada pendaftaran hari ini</p>
                        </div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $this->kunjungan->links() }}</div>
</div>
