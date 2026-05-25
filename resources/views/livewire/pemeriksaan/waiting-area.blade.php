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

                            {{-- Lihat: riwayat pemeriksaan (read-only) --}}
                            @if($k->status === 'selesai')
                            <button wire:click="openView({{ $k->id }})" type="button"
                                class="btn-secondary btn-sm inline-flex items-center gap-1">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                </svg>
                                Lihat
                            </button>
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

{{-- ══════════════════════════════════════════════════════════════
     MODAL HISTORY PEMERIKSAAN (Read-Only)
══════════════════════════════════════════════════════════════ --}}
@if($viewKunjunganId && $this->viewKunjungan)
@php $vk = $this->viewKunjungan; $soap = $vk->soapNote; $asesmen = $vk->asesmenPerawat; @endphp
<div class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4"
     x-data="{ tab: 'soap', soapTab: 'a' }"
     wire:key="modal-view-{{ $viewKunjunganId }}"
     @keydown.escape.window="$wire.closeView()">

    <div class="relative flex flex-col w-full max-w-4xl bg-white dark:bg-gray-900 rounded-2xl shadow-2xl"
         style="max-height: 90vh">

        {{-- ── Header ──────────────────────────────────────────────── --}}
        <div class="flex items-start justify-between px-6 py-4 border-b border-gray-200 dark:border-gray-700 shrink-0">
            <div>
                <div class="flex items-center gap-2">
                    <h2 class="text-lg font-bold text-gray-900 dark:text-white">
                        {{ $vk->pasien?->nama ?? '-' }}
                    </h2>
                    <span class="font-mono text-xs text-gray-400 bg-gray-100 dark:bg-gray-800 px-2 py-0.5 rounded">
                        {{ $vk->pasien?->nomor_rm }}
                    </span>
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Selesai
                    </span>
                </div>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">
                    {{ $vk->tanggal->format('d/m/Y H:i') }} &middot;
                    No. Antrean: <span class="font-mono font-bold">{{ $vk->nomor_antrean }}</span>
                </p>
            </div>
            <button wire:click="closeView" type="button"
                class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 transition ml-4 text-2xl leading-none">&times;</button>
        </div>

        {{-- ── Info strip ──────────────────────────────────────────── --}}
        <div class="px-6 py-3 bg-gray-50 dark:bg-gray-800/50 border-b border-gray-200 dark:border-gray-700 shrink-0">
            <div class="flex flex-wrap gap-x-6 gap-y-1 text-sm text-gray-600 dark:text-gray-300">
                <span><span class="text-gray-400 text-xs">Dokter</span><br>
                    <span class="font-medium">{{ $vk->dokter?->user?->nama ?? '-' }}</span></span>
                <span><span class="text-gray-400 text-xs">Poli</span><br>
                    <span class="font-medium">{{ $vk->poli?->nama ?? '-' }}</span></span>
                <span><span class="text-gray-400 text-xs">Penjamin</span><br>
                    <span class="font-medium uppercase">{{ $vk->tipe_pembayaran ?? 'Umum' }}</span></span>
                @if($vk->keluhan)
                <span class="flex-1"><span class="text-gray-400 text-xs">Keluhan Utama</span><br>
                    <span class="font-medium">{{ $vk->keluhan }}</span></span>
                @endif
            </div>

            {{-- Vitals --}}
            @if($asesmen)
            @php
                $vitals = array_filter([
                    'TD'   => $asesmen->tekanan_darah ?: null,
                    'Nadi' => $asesmen->nadi ? $asesmen->nadi . ' bpm' : null,
                    'Suhu' => $asesmen->suhu ? $asesmen->suhu . '°C' : null,
                    'SpO₂' => $asesmen->saturasi ? $asesmen->saturasi . '%' : null,
                    'BB'   => $asesmen->berat_badan ? $asesmen->berat_badan . ' kg' : null,
                    'TB'   => $asesmen->tinggi_badan ? $asesmen->tinggi_badan . ' cm' : null,
                    'GDS'  => $asesmen->gds ? $asesmen->gds . ' mg/dL' : null,
                ]);
            @endphp
            @if(count($vitals))
            <div class="mt-2 flex flex-wrap gap-3">
                @foreach($vitals as $label => $val)
                <div class="flex items-center gap-1 rounded-lg bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 px-3 py-1 text-xs">
                    <span class="text-blue-500 font-semibold">{{ $label }}</span>
                    <span class="text-blue-900 dark:text-blue-200 font-bold">{{ $val }}</span>
                </div>
                @endforeach
            </div>
            @endif
            @endif
        </div>

        {{-- ── Tab bar ──────────────────────────────────────────────── --}}
        <div class="flex gap-0 border-b border-gray-200 dark:border-gray-700 px-6 shrink-0">
            @php
                $tabs = [
                    'soap'     => 'SOAP Note',
                    'diagnosa' => 'Diagnosa ICD',
                    'resep'    => 'Resep Obat',
                    'tindakan' => 'Tindakan',
                ];
                $resepCount    = $vk->resep->sum(fn($r) => $r->itemResep->count() + $r->racikan->count());
                $tindakanCount = $vk->tindakan->count();
                $diagnosaCount = $soap ? count($soap->icd_codes ?? []) : 0;
            @endphp
            @foreach($tabs as $key => $label)
            <button type="button" @click="tab = '{{ $key }}'"
                :class="tab === '{{ $key }}'
                    ? 'border-b-2 border-primary-600 text-primary-700 dark:text-primary-400 font-semibold'
                    : 'text-gray-500 hover:text-gray-700 dark:text-gray-400'"
                class="px-4 py-3 text-sm transition-colors whitespace-nowrap">
                {{ $label }}
                @if($key === 'diagnosa' && $diagnosaCount > 0)
                    <span class="ml-1 inline-flex items-center justify-center w-4 h-4 rounded-full text-[10px] bg-primary-600 text-white">{{ $diagnosaCount }}</span>
                @elseif($key === 'resep' && $resepCount > 0)
                    <span class="ml-1 inline-flex items-center justify-center w-4 h-4 rounded-full text-[10px] bg-emerald-600 text-white">{{ $resepCount }}</span>
                @elseif($key === 'tindakan' && $tindakanCount > 0)
                    <span class="ml-1 inline-flex items-center justify-center w-4 h-4 rounded-full text-[10px] bg-purple-600 text-white">{{ $tindakanCount }}</span>
                @endif
            </button>
            @endforeach
        </div>

        {{-- ── Tab Content (scrollable) ─────────────────────────────── --}}
        <div class="overflow-y-auto flex-1 px-6 py-5">

            {{-- ── Tab: SOAP Note ──────────────────────────────────── --}}
            <div x-show="tab === 'soap'" x-cloak>
                @if($soap)
                {{-- Sub-tab S/O/A/P --}}
                <div class="flex gap-0 border-b border-gray-200 dark:border-gray-700 mb-4">
                    @foreach(['s' => 'Subjective', 'o' => 'Objective', 'a' => 'Assessment', 'p' => 'Planning'] as $sk => $sl)
                    <button type="button" @click="soapTab = '{{ $sk }}'"
                        :class="soapTab === '{{ $sk }}'
                            ? 'border-b-2 border-amber-500 text-amber-700 dark:text-amber-400 font-semibold'
                            : 'text-gray-400 hover:text-gray-600'"
                        class="px-4 py-2 text-xs transition-colors">{{ $sl }}</button>
                    @endforeach
                </div>

                {{-- Subjective --}}
                <div x-show="soapTab === 's'" x-cloak class="space-y-3 text-sm">
                    @foreach([
                        'CC / HPI'              => $soap->s_cc_hpi,
                        'Past Medical History'   => $soap->s_past_medical,
                        'Past Surgical History'  => $soap->s_past_surgical,
                        'Allergies'              => $soap->s_allergies,
                        'Other (Subjective)'     => $soap->s_other,
                    ] as $label => $val)
                    @if($val)
                    <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-3">
                        <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 mb-1">{{ $label }}</p>
                        <p class="text-gray-800 dark:text-gray-200 whitespace-pre-wrap">{{ $val }}</p>
                    </div>
                    @endif
                    @endforeach
                    @if(!$soap->s_cc_hpi && !$soap->s_past_medical && !$soap->s_allergies)
                    <p class="text-gray-400 italic text-center py-4">Tidak ada data subjective.</p>
                    @endif
                </div>

                {{-- Objective --}}
                <div x-show="soapTab === 'o'" x-cloak class="space-y-3 text-sm">
                    @foreach([
                        'Physical Examination' => $soap->o_physical_exam,
                        'Systemic Examination' => $soap->o_systemic_exam,
                        'Observation'          => $soap->o_observation,
                        'Other (Objective)'    => $soap->o_other,
                    ] as $label => $val)
                    @if($val)
                    <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-3">
                        <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 mb-1">{{ $label }}</p>
                        <p class="text-gray-800 dark:text-gray-200 whitespace-pre-wrap">{{ $val }}</p>
                    </div>
                    @endif
                    @endforeach
                    @if(!$soap->o_physical_exam && !$soap->o_systemic_exam)
                    <p class="text-gray-400 italic text-center py-4">Tidak ada data objective.</p>
                    @endif
                </div>

                {{-- Assessment --}}
                <div x-show="soapTab === 'a'" x-cloak class="space-y-3 text-sm">
                    {{-- ICD Codes --}}
                    @if(count($soap->icd_codes ?? []) > 0)
                    <div class="rounded-lg border border-amber-200 dark:border-amber-800 bg-amber-50 dark:bg-amber-900/10 p-3">
                        <p class="text-xs font-semibold text-amber-700 dark:text-amber-400 mb-2">Diagnosa ICD-10</p>
                        <div class="space-y-1.5">
                            @foreach($soap->icd_codes as $icd)
                            <div class="flex items-center gap-2">
                                @if($icd['is_primary'] ?? false)
                                <span class="text-[10px] font-bold px-1.5 py-0.5 rounded bg-amber-500 text-white shrink-0">Utama</span>
                                @else
                                <span class="text-[10px] font-bold px-1.5 py-0.5 rounded bg-gray-200 text-gray-600 dark:bg-gray-700 dark:text-gray-300 shrink-0">Lain</span>
                                @endif
                                <span class="font-mono font-bold text-amber-800 dark:text-amber-300">{{ $icd['kode'] ?? $icd['code'] ?? '-' }}</span>
                                <span class="text-gray-700 dark:text-gray-300">{{ $icd['nama'] ?? $icd['name'] ?? '' }}</span>
                            </div>
                            @endforeach
                        </div>
                    </div>
                    @endif
                    @foreach([
                        'Problems'      => $soap->a_problems,
                        'Progress Note' => $soap->a_progress_note,
                        'Other (Assessment)' => $soap->a_other,
                    ] as $label => $val)
                    @if($val)
                    <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-3">
                        <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 mb-1">{{ $label }}</p>
                        <p class="text-gray-800 dark:text-gray-200 whitespace-pre-wrap">{{ $val }}</p>
                    </div>
                    @endif
                    @endforeach
                    @if(count($soap->icd_codes ?? []) === 0 && !$soap->a_problems)
                    <p class="text-gray-400 italic text-center py-4">Belum ada data assessment/diagnosa.</p>
                    @endif
                </div>

                {{-- Planning --}}
                <div x-show="soapTab === 'p'" x-cloak class="space-y-3 text-sm">
                    @foreach([
                        'Advice / Instruksi' => $soap->p_advice,
                        'Other (Planning)'   => $soap->p_other,
                    ] as $label => $val)
                    @if($val)
                    <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-3">
                        <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 mb-1">{{ $label }}</p>
                        <p class="text-gray-800 dark:text-gray-200 whitespace-pre-wrap">{{ $val }}</p>
                    </div>
                    @endif
                    @endforeach
                    @if(!$soap->p_advice && !$soap->p_other)
                    <p class="text-gray-400 italic text-center py-4">Tidak ada data planning.</p>
                    @endif
                </div>

                @if($soap->is_final)
                <div class="mt-4 flex items-center gap-2 text-xs text-emerald-600 dark:text-emerald-400">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                    </svg>
                    SOAP Note telah difinalisasi
                    @if($soap->finalizedAt ?? null)
                        pada {{ $soap->finalized_at->format('d/m/Y H:i') }}
                    @endif
                </div>
                @endif
                @else
                <div class="flex flex-col items-center justify-center py-12 text-gray-400">
                    <svg class="w-12 h-12 mb-3 opacity-30" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                            d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    <p class="text-sm">SOAP Note belum diisi oleh dokter.</p>
                </div>
                @endif
            </div>

            {{-- ── Tab: Diagnosa ICD ────────────────────────────────── --}}
            <div x-show="tab === 'diagnosa'" x-cloak>
                @if($soap && count($soap->icd_codes ?? []) > 0)
                <div class="overflow-hidden rounded-xl border border-gray-200 dark:border-gray-700">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 dark:bg-gray-800 text-xs font-semibold uppercase text-gray-500">
                            <tr>
                                <th class="px-4 py-3 text-left">Tipe</th>
                                <th class="px-4 py-3 text-left">Kode ICD</th>
                                <th class="px-4 py-3 text-left">Nama Diagnosa</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            @foreach($soap->icd_codes as $icd)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                                <td class="px-4 py-3">
                                    @if($icd['is_primary'] ?? false)
                                    <span class="inline-block px-2 py-0.5 rounded-full text-xs font-bold bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300">Utama</span>
                                    @else
                                    <span class="inline-block px-2 py-0.5 rounded-full text-xs font-bold bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300">Lainnya</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 font-mono font-bold text-amber-700 dark:text-amber-400">
                                    {{ $icd['kode'] ?? $icd['code'] ?? '-' }}
                                </td>
                                <td class="px-4 py-3 text-gray-700 dark:text-gray-300">
                                    {{ $icd['nama'] ?? $icd['name'] ?? '-' }}
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @else
                <div class="flex flex-col items-center justify-center py-12 text-gray-400">
                    <svg class="w-12 h-12 mb-3 opacity-30" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                            d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                    </svg>
                    <p class="text-sm">Belum ada diagnosa ICD-10 yang dicatat.</p>
                </div>
                @endif
            </div>

            {{-- ── Tab: Resep Obat ──────────────────────────────────── --}}
            <div x-show="tab === 'resep'" x-cloak>
                @forelse($vk->resep as $resep)
                <div class="mb-4 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
                    <div class="flex items-center justify-between bg-emerald-50 dark:bg-emerald-900/20 px-4 py-2 border-b border-emerald-200 dark:border-emerald-800">
                        <div class="flex items-center gap-2">
                            <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/>
                            </svg>
                            <span class="text-sm font-semibold text-emerald-800 dark:text-emerald-300">Resep #{{ $loop->iteration }}</span>
                        </div>
                        <div class="flex items-center gap-2">
                            @if($resep->is_locked)
                            <span class="text-xs font-semibold px-2 py-0.5 rounded-full bg-emerald-600 text-white">Dikonfirmasi Farmasi</span>
                            @else
                            <span class="text-xs font-semibold px-2 py-0.5 rounded-full bg-amber-100 text-amber-700">{{ ucfirst($resep->status) }}</span>
                            @endif
                        </div>
                    </div>

                    @if($resep->itemResep->count() > 0)
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 dark:bg-gray-800 text-xs font-semibold text-gray-500 uppercase">
                            <tr>
                                <th class="px-4 py-2 text-left">Obat</th>
                                <th class="px-4 py-2 text-center">Jumlah</th>
                                <th class="px-4 py-2 text-left">Aturan Pakai</th>
                                <th class="px-4 py-2 text-left">Catatan</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            @foreach($resep->itemResep as $item)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/40">
                                <td class="px-4 py-2.5 font-medium text-gray-800 dark:text-gray-200">
                                    {{ $item->obat?->nama_obat ?? '-' }}
                                    <span class="text-xs text-gray-400 ml-1">{{ $item->obat?->satuan }}</span>
                                </td>
                                <td class="px-4 py-2.5 text-center font-bold text-gray-700 dark:text-gray-300">
                                    {{ $item->jumlah }}
                                </td>
                                <td class="px-4 py-2.5 text-gray-600 dark:text-gray-400 text-xs">
                                    {{ $item->aturan_pakai ?? '-' }}
                                </td>
                                <td class="px-4 py-2.5 text-gray-500 dark:text-gray-400 text-xs">
                                    {{ $item->catatan ?? '-' }}
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                    @endif

                    @if($resep->racikan->count() > 0)
                    <div class="px-4 py-2 bg-purple-50 dark:bg-purple-900/10 border-t border-purple-100 dark:border-purple-800">
                        <p class="text-xs font-semibold text-purple-700 dark:text-purple-400 mb-2">Racikan</p>
                        @foreach($resep->racikan as $rac)
                        <div class="mb-2 pl-3 border-l-2 border-purple-300">
                            <p class="text-xs font-bold text-purple-700 dark:text-purple-300">{{ $rac->nama ?? 'Racikan #'.$loop->iteration }}</p>
                            @foreach($rac->bahanRacikan as $bahan)
                            <p class="text-xs text-gray-600 dark:text-gray-400 ml-2">
                                &bull; {{ $bahan->obat?->nama_obat }} — {{ $bahan->jumlah }} {{ $bahan->satuan }}
                            </p>
                            @endforeach
                        </div>
                        @endforeach
                    </div>
                    @endif

                    @if($resep->catatan)
                    <div class="px-4 py-2 border-t border-gray-100 dark:border-gray-700 text-xs text-gray-500 dark:text-gray-400 bg-gray-50 dark:bg-gray-800/30">
                        <span class="font-semibold">Catatan Dokter:</span> {{ $resep->catatan }}
                    </div>
                    @endif
                </div>
                @empty
                <div class="flex flex-col items-center justify-center py-12 text-gray-400">
                    <svg class="w-12 h-12 mb-3 opacity-30" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                            d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"/>
                    </svg>
                    <p class="text-sm">Tidak ada resep pada kunjungan ini.</p>
                </div>
                @endforelse
            </div>

            {{-- ── Tab: Tindakan ────────────────────────────────────── --}}
            <div x-show="tab === 'tindakan'" x-cloak>
                @if($vk->tindakan->count() > 0)
                <div class="overflow-hidden rounded-xl border border-gray-200 dark:border-gray-700">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 dark:bg-gray-800 text-xs font-semibold uppercase text-gray-500">
                            <tr>
                                <th class="px-4 py-3 text-left">Tindakan</th>
                                <th class="px-4 py-3 text-center">Jumlah</th>
                                <th class="px-4 py-3 text-right">Tarif</th>
                                <th class="px-4 py-3 text-right">Total</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            @foreach($vk->tindakan as $t)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/40">
                                <td class="px-4 py-2.5 font-medium text-gray-800 dark:text-gray-200">
                                    {{ $t->masterTindakan?->nama ?? '-' }}
                                </td>
                                <td class="px-4 py-2.5 text-center text-gray-700 dark:text-gray-300">
                                    {{ $t->jumlah ?? 1 }}
                                </td>
                                <td class="px-4 py-2.5 text-right text-gray-600 dark:text-gray-400">
                                    Rp {{ number_format($t->masterTindakan?->tarif ?? 0, 0, ',', '.') }}
                                </td>
                                <td class="px-4 py-2.5 text-right font-semibold text-gray-800 dark:text-gray-200">
                                    Rp {{ number_format(($t->jumlah ?? 1) * ($t->masterTindakan?->tarif ?? 0), 0, ',', '.') }}
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="bg-gray-50 dark:bg-gray-800 border-t border-gray-200 dark:border-gray-700">
                            <tr>
                                <td colspan="3" class="px-4 py-2 text-right text-sm font-bold text-gray-700 dark:text-gray-300">Total Tindakan</td>
                                <td class="px-4 py-2 text-right text-sm font-bold text-primary-700 dark:text-primary-400">
                                    Rp {{ number_format($vk->tindakan->sum(fn($t) => ($t->jumlah ?? 1) * ($t->masterTindakan?->tarif ?? 0)), 0, ',', '.') }}
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                @else
                <div class="flex flex-col items-center justify-center py-12 text-gray-400">
                    <svg class="w-12 h-12 mb-3 opacity-30" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                            d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                    </svg>
                    <p class="text-sm">Tidak ada tindakan pada kunjungan ini.</p>
                </div>
                @endif
            </div>

        </div>

        {{-- ── Footer ───────────────────────────────────────────────── --}}
        <div class="flex justify-end px-6 py-3 border-t border-gray-200 dark:border-gray-700 shrink-0 bg-gray-50 dark:bg-gray-800/50 rounded-b-2xl">
            <button wire:click="closeView" type="button"
                class="px-5 py-2 text-sm font-medium bg-gray-200 hover:bg-gray-300 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-200 rounded-lg transition">
                Tutup
            </button>
        </div>

    </div>
</div>
@endif
