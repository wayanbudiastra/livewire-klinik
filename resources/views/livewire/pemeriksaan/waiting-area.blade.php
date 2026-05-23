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
                    class="form-select w-44 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200">
                <option value="aktif">Aktif (Menunggu + Diperiksa)</option>
                <option value="menunggu">Menunggu</option>
                <option value="dalam_pemeriksaan">Dalam Pemeriksaan</option>
                <option value="selesai">Selesai</option>
                <option value="">Semua Status</option>
            </select>
        </div>
        <div class="text-sm text-gray-500 dark:text-gray-400 flex items-center gap-1">
            Total: <span class="font-semibold">{{ $this->kunjungan->total() }}</span>
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
                    <th>Waktu Tunggu</th>
                    <th>Penjamin</th>
                    <th>Status</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($this->kunjungan as $k)
                @php
                    $isAppointment = str_starts_with($k->nomor_antrean, 'A-');
                    $penjamin      = $k->tipe_pembayaran ?? 'umum';
                    $adaAlergi     = !empty($k->pasien?->alergi);
                @endphp
                <tr wire:key="wa-{{ $k->id }}" @class([
                    'border-l-4 border-l-blue-400'            => $isAppointment,
                    'border-l-4 border-l-gray-200 dark:border-l-gray-600' => !$isAppointment,
                ])>
                    <td>
                        <div class="flex flex-col items-start gap-1">
                            <span @class([
                                'font-mono text-xl font-black',
                                'text-[#0a3d62] dark:text-blue-400' => $isAppointment,
                                'text-gray-600 dark:text-gray-400'  => !$isAppointment,
                            ])>{{ $k->nomor_antrean }}</span>
                            @if($isAppointment)
                            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-bold
                                         bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300 uppercase">
                                ★ Prioritas
                            </span>
                            @endif
                        </div>
                    </td>
                    <td>
                        <p class="font-medium text-gray-900 dark:text-gray-100">{{ $k->pasien?->nama ?? '-' }}</p>
                        <p class="text-xs font-mono text-gray-400">{{ $k->pasien?->nomor_rm }}</p>
                        @if($adaAlergi)
                        <span class="inline-flex items-center gap-1 mt-1 px-2 py-0.5 rounded-full text-[10px] font-bold
                                     bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300">
                            ⚠ ALERGI
                        </span>
                        @endif
                    </td>
                    <td>
                        <p class="text-sm text-gray-700 dark:text-gray-300">{{ $k->dokter?->user?->nama ?? '-' }}</p>
                        <p class="text-xs text-gray-400">{{ $k->poli?->nama ?? '-' }}</p>
                    </td>
                    <td class="text-sm text-gray-600 dark:text-gray-400">
                        {{ $k->tanggal->format('H:i') }}
                    </td>
                    <td>
                        @if($k->waktu_panggil)
                        <span class="text-xs text-gray-500 dark:text-gray-400">{{ $k->waktu_tunggu }}</span>
                        @elseif($k->status === 'menunggu')
                        @php $mnt = $k->tanggal->diffInMinutes(now()); @endphp
                        <span @class(['text-xs font-semibold',
                            'text-red-600'   => $mnt > 30,
                            'text-amber-600' => $mnt > 15 && $mnt <= 30,
                            'text-gray-500 dark:text-gray-400' => $mnt <= 15,
                        ])>{{ $mnt }} mnt</span>
                        @else
                        <span class="text-xs text-gray-400">—</span>
                        @endif
                    </td>
                    <td>
                        <span @class(['badge',
                            'badge-primary' => $penjamin === 'umum',
                            'badge-success' => $penjamin === 'bpjs',
                            'badge-warning' => $penjamin === 'asuransi',
                        ])>{{ strtoupper($penjamin) }}</span>
                    </td>
                    <td><x-badge-status :status="$k->status" /></td>
                    <td>
                        <div class="flex gap-1 flex-wrap">
                            {{-- Panggil: hanya untuk status menunggu --}}
                            @if($k->status === 'menunggu')
                            <x-confirm-button
                                action="panggil({{ $k->id }})"
                                title="Panggil Pasien?"
                                text="Pasien {{ $k->pasien?->nama }} akan dipanggil ke ruang pemeriksaan."
                                confirm="Ya, Panggil"
                                type="success"
                                class="btn-success btn-sm">
                                Panggil
                            </x-confirm-button>
                            @endif

                            {{-- Periksa: link ke dashboard detail --}}
                            @if(in_array($k->status, ['menunggu', 'dalam_pemeriksaan']))
                            <a href="{{ route('pemeriksaan.index', ['tab' => 'detail', 'kunjunganId' => $k->id]) }}"
                               class="btn-primary btn-sm">
                                Periksa
                            </a>
                            @endif

                            {{-- Selesai: untuk status dalam_pemeriksaan --}}
                            @if($k->status === 'dalam_pemeriksaan')
                            <x-confirm-button
                                action="selesai({{ $k->id }})"
                                title="Selesaikan Pemeriksaan?"
                                text="Status kunjungan {{ $k->pasien?->nama }} akan diubah ke Selesai."
                                confirm="Ya, Selesai"
                                type="success"
                                class="btn-info btn-sm">
                                Selesai
                            </x-confirm-button>
                            @endif
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="8">
                        <div class="empty-state py-12">
                            <svg class="empty-state-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                      d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                            </svg>
                            <p class="empty-state-text">Tidak ada pasien dalam antrean</p>
                        </div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $this->kunjungan->links() }}</div>
</div>
