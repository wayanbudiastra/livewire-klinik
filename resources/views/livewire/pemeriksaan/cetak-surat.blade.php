<div>
    {{-- ── Tombol dropdown Cetak Surat ──────────────────────── --}}
    @canany(['surat.cetak'])
    <div class="relative" x-data="{ open: false }">
        <button @click="open = !open" @click.outside="open = false"
                type="button"
                class="inline-flex items-center gap-1.5 px-4 py-2 bg-[#0a3d62] hover:bg-[#1a5a8a] text-white text-sm font-medium rounded-lg transition-colors shadow-sm">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
            Cetak Surat
            <svg class="w-3 h-3 transition-transform duration-150" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
            </svg>
        </button>

        <div x-show="open" x-transition:enter="transition ease-out duration-100"
             x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
             x-transition:leave="transition ease-in duration-75"
             x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95"
             class="absolute right-0 mt-1 w-52 bg-white dark:bg-gray-800 rounded-xl shadow-lg border border-gray-200 dark:border-gray-700 z-30 overflow-hidden"
             style="display:none;">
            @foreach([
                ['tipe' => 'keterangan_sehat', 'label' => 'Keterangan Sehat',  'icon' => 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z', 'color' => 'text-emerald-600'],
                ['tipe' => 'keterangan_sakit', 'label' => 'Keterangan Sakit',  'icon' => 'M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z', 'color' => 'text-red-500'],
                ['tipe' => 'rujukan',          'label' => 'Surat Rujukan',      'icon' => 'M17 8l4 4m0 0l-4 4m4-4H3', 'color' => 'text-blue-600'],
                ['tipe' => 'kontrol',          'label' => 'Jadwal Kontrol',     'icon' => 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z', 'color' => 'text-amber-600'],
            ] as $item)
            <button type="button"
                    wire:click="buka('{{ $item['tipe'] }}')"
                    @click="open = false"
                    class="w-full flex items-center gap-3 px-4 py-2.5 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                <svg class="w-4 h-4 {{ $item['color'] }} shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $item['icon'] }}"/>
                </svg>
                {{ $item['label'] }}
            </button>
            @endforeach
        </div>
    </div>
    @endcanany

    {{-- ── Modal Cetak Surat ─────────────────────────────────── --}}
    @if($showModal)
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50" wire:click.self="$set('showModal', false)">
        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl w-full max-w-lg max-h-[90vh] overflow-y-auto">

            {{-- Header modal --}}
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                <div class="flex items-center gap-2">
                    <svg class="w-5 h-5 text-[#0a3d62]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    <h3 class="text-base font-semibold text-gray-900 dark:text-white">
                        {{ match($tipe) {
                            'keterangan_sehat' => 'Surat Keterangan Sehat',
                            'keterangan_sakit' => 'Surat Keterangan Sakit',
                            'rujukan'          => 'Surat Rujukan',
                            'kontrol'          => 'Surat Jadwal Kontrol',
                            default            => 'Cetak Surat',
                        } }}
                    </h3>
                </div>
                <button type="button" wire:click="$set('showModal', false)" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <div class="px-6 py-5 space-y-4">

                {{-- Error --}}
                @if($errorMsg)
                <div class="flex gap-2 p-3 bg-red-50 border border-red-300 rounded-lg text-sm text-red-700">
                    <svg class="w-4 h-4 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    {{ $errorMsg }}
                </div>
                @endif

                {{-- Info pasien --}}
                @if($kunjungan)
                <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-3 text-sm">
                    <span class="font-semibold text-gray-800 dark:text-gray-100">{{ $kunjungan->pasien->nama }}</span>
                    <span class="text-gray-500 dark:text-gray-400 ml-2 text-xs">{{ $kunjungan->pasien->no_rekam_medis }}</span>
                    <div class="text-xs text-gray-500 mt-0.5">
                        {{ $kunjungan->pasien->tanggal_lahir ? \Carbon\Carbon::parse($kunjungan->pasien->tanggal_lahir)->age . ' tahun' : '' }}
                        · Kunjungan: {{ \Carbon\Carbon::parse($kunjungan->tanggal_kunjungan ?? $kunjungan->created_at)->translatedFormat('d M Y') }}
                    </div>
                </div>

                @if(!$kunjungan->soapNote?->is_final)
                <div class="flex gap-2 p-3 bg-amber-50 border border-amber-300 rounded-lg text-sm text-amber-700">
                    <svg class="w-4 h-4 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                    SOAP Note belum final. Finalisasi pemeriksaan sebelum menerbitkan surat.
                </div>
                @endif
                @endif

                {{-- Dokter penandatangan --}}
                <div>
                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Dokter Penandatangan <span class="text-red-500">*</span>
                    </label>
                    <select wire:model="dokterId" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm focus:ring-[#0a3d62] focus:border-[#0a3d62]">
                        <option value="">-- Pilih Dokter --</option>
                        @foreach($this->dokterList as $d)
                        <option value="{{ $d->id }}">{{ $d->user->nama }}</option>
                        @endforeach
                    </select>
                    @error('dokterId') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                </div>

                {{-- Field per tipe surat --}}
                @if($tipe === 'keterangan_sehat')
                <div>
                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Keperluan <span class="text-gray-400 text-xs font-normal">(opsional)</span></label>
                    <input type="text" wire:model="keperluan" placeholder="mis. melamar pekerjaan, persyaratan sekolah..."
                           class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm focus:ring-[#0a3d62] focus:border-[#0a3d62]">
                </div>

                @elseif($tipe === 'keterangan_sakit')
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Tanggal Mulai <span class="text-red-500">*</span></label>
                        <input type="date" wire:model="tanggalMulai"
                               class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm focus:ring-[#0a3d62] focus:border-[#0a3d62]">
                        @error('tanggalMulai') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Lama Istirahat (hari) <span class="text-red-500">*</span></label>
                        <input type="number" wire:model="lamaHari" min="1" max="365"
                               class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm focus:ring-[#0a3d62] focus:border-[#0a3d62]">
                        @error('lamaHari') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <input type="checkbox" id="tampilkan_dx" wire:model="tampilkanDiagnosa" class="rounded border-gray-300 text-[#0a3d62] focus:ring-[#0a3d62]">
                    <label for="tampilkan_dx" class="text-sm text-gray-700 dark:text-gray-300">Cantumkan diagnosa di surat</label>
                </div>

                @elseif($tipe === 'rujukan')
                <div>
                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Tujuan Fasilitas/RS <span class="text-red-500">*</span></label>
                    <input type="text" wire:model="tujuanFasilitas" placeholder="mis. RS Sanglah Denpasar"
                           class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm focus:ring-[#0a3d62] focus:border-[#0a3d62]">
                    @error('tujuanFasilitas') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Dokter Tujuan <span class="text-gray-400 text-xs font-normal">(opsional)</span></label>
                    <input type="text" wire:model="tujuanDokter" placeholder="mis. dr. Spesialis Bedah"
                           class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm focus:ring-[#0a3d62] focus:border-[#0a3d62]">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Indikasi / Alasan Rujukan <span class="text-red-500">*</span></label>
                    <textarea wire:model="indikasi" rows="3" placeholder="Tuliskan indikasi medis rujukan..."
                              class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm focus:ring-[#0a3d62] focus:border-[#0a3d62]"></textarea>
                    @error('indikasi') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                </div>
                <div class="flex items-center gap-2">
                    <input type="checkbox" id="sertakan_penunjang" wire:model="sertakanPenunjang" class="rounded border-gray-300 text-[#0a3d62] focus:ring-[#0a3d62]">
                    <label for="sertakan_penunjang" class="text-sm text-gray-700 dark:text-gray-300">Sertakan riwayat pemeriksaan penunjang</label>
                </div>

                @elseif($tipe === 'kontrol')
                <div>
                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Tanggal Kontrol <span class="text-red-500">*</span></label>
                    <input type="date" wire:model="tanggalKontrol" min="{{ now()->addDay()->toDateString() }}"
                           class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm focus:ring-[#0a3d62] focus:border-[#0a3d62]">
                    @error('tanggalKontrol') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Instruksi/Pesan <span class="text-gray-400 text-xs font-normal">(opsional)</span></label>
                    <textarea wire:model="instruksi" rows="3" placeholder="Instruksi atau pesan dokter untuk pasien..."
                              class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm focus:ring-[#0a3d62] focus:border-[#0a3d62]"></textarea>
                </div>
                @endif

            </div>

            {{-- Footer modal --}}
            <div class="flex justify-end gap-3 px-6 py-4 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50 rounded-b-2xl">
                <button type="button" wire:click="$set('showModal', false)"
                        class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors">
                    Batal
                </button>
                <button type="button" wire:click="cetak" wire:loading.attr="disabled"
                        @if(!$kunjungan?->soapNote?->is_final) disabled @endif
                        class="inline-flex items-center gap-2 px-4 py-2 bg-[#0a3d62] hover:bg-[#1a5a8a] disabled:opacity-50 disabled:cursor-not-allowed text-white text-sm font-medium rounded-lg transition-colors">
                    <span wire:loading.remove wire:target="cetak">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                    </span>
                    <span wire:loading wire:target="cetak">
                        <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
                        </svg>
                    </span>
                    <span wire:loading.remove wire:target="cetak">Cetak PDF</span>
                    <span wire:loading wire:target="cetak">Memproses...</span>
                </button>
            </div>
        </div>
    </div>
    @endif

    {{-- ── Riwayat surat ─────────────────────────────────────── --}}
    @if($this->riwayatSurat->isNotEmpty())
    <div class="mt-4">
        <h4 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-2">Riwayat Surat Diterbitkan</h4>
        <div class="space-y-1.5">
            @foreach($this->riwayatSurat as $s)
            <div class="flex items-center justify-between px-3 py-2 bg-gray-50 dark:bg-gray-700/50 rounded-lg text-xs">
                <div class="flex items-center gap-2">
                    <span @class([
                        'px-2 py-0.5 rounded text-xs font-medium',
                        'bg-emerald-100 text-emerald-700' => $s->tipe === 'keterangan_sehat',
                        'bg-red-100 text-red-700'         => $s->tipe === 'keterangan_sakit',
                        'bg-blue-100 text-blue-700'       => $s->tipe === 'rujukan',
                        'bg-amber-100 text-amber-700'     => $s->tipe === 'kontrol',
                    ])>{{ $s->label_tipe }}</span>
                    <span class="font-mono text-gray-600 dark:text-gray-300">{{ $s->nomor_surat }}</span>
                </div>
                <div class="flex items-center gap-3 text-gray-400">
                    <span>{{ $s->dicetak_pada->format('d/m/Y H:i') }}</span>
                    <a href="{{ route('pemeriksaan.surat.unduh', $s->id) }}" target="_blank"
                       class="text-[#0a3d62] hover:text-[#1a5a8a] dark:text-blue-400 font-medium transition-colors">
                        Unduh Ulang
                    </a>
                </div>
            </div>
            @endforeach
        </div>
    </div>
    @endif
</div>
