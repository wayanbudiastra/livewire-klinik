<div class="contents">
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
                ['tipe' => 'resume_medis',     'label' => 'Resume Medis',       'icon' => 'M9 12h6m-6 4h6m2-13H7a2 2 0 00-2 2v14a2 2 0 002 2h10a2 2 0 002-2V8l-6-5z', 'color' => 'text-purple-600'],
            ] as $item)
            @php $terkunci = in_array($item['tipe'], $this->tipeTerkunci); @endphp
            <button type="button"
                    @if($terkunci)
                        disabled
                        title="Sudah diterbitkan & kunjungan sudah Selesai -- pakai Unduh Ulang di Riwayat Surat"
                    @else
                        wire:click="buka('{{ $item['tipe'] }}')"
                    @endif
                    @click="open = false"
                    @class([
                        'w-full flex items-center gap-3 px-4 py-2.5 text-sm transition-colors',
                        'text-gray-300 dark:text-gray-600 cursor-not-allowed' => $terkunci,
                        'text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700' => !$terkunci,
                    ])>
                <svg class="w-4 h-4 {{ $terkunci ? 'text-gray-300 dark:text-gray-600' : $item['color'] }} shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $item['icon'] }}"/>
                </svg>
                {{ $item['label'] }}
                @if($terkunci)<span class="ml-auto text-xs italic">terbit</span>@endif
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
                        @if($editingSuratId)Edit @endif{{ match($tipe) {
                            'keterangan_sehat' => 'Surat Keterangan Sehat',
                            'keterangan_sakit' => 'Surat Keterangan Sakit',
                            'rujukan'          => 'Surat Rujukan',
                            'kontrol'          => 'Surat Jadwal Kontrol',
                            'resume_medis'     => 'Resume Medis',
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
                @if($this->kunjungan)
                <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-3 text-sm">
                    <span class="font-semibold text-gray-800 dark:text-gray-100">{{ $this->kunjungan->pasien->nama }}</span>
                    <span class="text-gray-500 dark:text-gray-400 ml-2 text-xs">{{ $this->kunjungan->pasien->no_rekam_medis }}</span>
                    <div class="text-xs text-gray-500 mt-0.5">
                        {{ $this->kunjungan->pasien->tanggal_lahir ? \Carbon\Carbon::parse($this->kunjungan->pasien->tanggal_lahir)->age . ' tahun' : '' }}
                        · Kunjungan: {{ \Carbon\Carbon::parse($this->kunjungan->tanggal_kunjungan ?? $this->kunjungan->created_at)->translatedFormat('d M Y') }}
                    </div>
                </div>

                @if(!$this->kunjungan->soapNote?->is_final)
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
                <div>
                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Pemeriksaan Buta Warna <span class="text-gray-400 text-xs font-normal">(opsional, Colour Blindness)</span></label>
                    <select wire:model="butaWarna" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm focus:ring-[#0a3d62] focus:border-[#0a3d62]">
                        <option value="">-- Tidak diperiksa --</option>
                        <option value="normal">Normal</option>
                        <option value="parsial">Buta Warna Parsial</option>
                        <option value="total">Buta Warna Total</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Bahasa Dokumen</label>
                    <div class="flex gap-4">
                        <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                            <input type="radio" wire:model="bahasa" value="id" class="text-[#0a3d62] focus:ring-[#0a3d62]">
                            Bahasa Indonesia
                        </label>
                        <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                            <input type="radio" wire:model="bahasa" value="en" class="text-[#0a3d62] focus:ring-[#0a3d62]">
                            English
                        </label>
                    </div>
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
                <div>
                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Bahasa Dokumen</label>
                    <div class="flex gap-4">
                        <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                            <input type="radio" wire:model="bahasa" value="id" class="text-[#0a3d62] focus:ring-[#0a3d62]">
                            Bahasa Indonesia
                        </label>
                        <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                            <input type="radio" wire:model="bahasa" value="en" class="text-[#0a3d62] focus:ring-[#0a3d62]">
                            English
                        </label>
                    </div>
                </div>

                @elseif($tipe === 'rujukan')
                <div x-data="{ open: false }">
                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Tujuan Fasilitas/RS <span class="text-red-500">*</span></label>
                    <div class="relative">
                        <input type="text" wire:model.live.debounce.300ms="tujuanFasilitas"
                               @focus="open = true" @click.away="open = false"
                               placeholder="Ketik nama RS/fasilitas -- pilih dari daftar atau tambah baru"
                               autocomplete="off"
                               class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm focus:ring-[#0a3d62] focus:border-[#0a3d62]">
                        @if(strlen($tujuanFasilitas) >= 2)
                        <div x-show="open" x-transition
                             class="absolute z-50 w-full mt-1 bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-600 shadow-lg max-h-48 overflow-y-auto">
                            @forelse($this->tujuanRujukanSuggestions as $tr)
                            <button type="button" wire:click="$set('tujuanFasilitas', @js($tr->nama))" @click="open = false"
                                    class="w-full text-left px-3 py-2 text-sm hover:bg-gray-50 dark:hover:bg-gray-700 border-b border-gray-100 dark:border-gray-700 last:border-0">
                                {{ $tr->nama }}
                            </button>
                            @empty
                            <div class="px-3 py-2 text-xs text-gray-400">
                                Belum ada di daftar -- "<strong>{{ $tujuanFasilitas }}</strong>" akan ditambahkan sebagai tujuan baru saat disimpan.
                            </div>
                            @endforelse
                        </div>
                        @endif
                    </div>
                    <p class="text-[11px] text-gray-400 mt-1">Ketikan baru otomatis masuk daftar supaya bisa direkap konsisten.</p>
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

                @elseif($tipe === 'resume_medis')
                <div>
                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Bahasa Dokumen <span class="text-red-500">*</span></label>
                    <div class="flex gap-4">
                        <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                            <input type="radio" wire:model="bahasa" value="id" class="text-[#0a3d62] focus:ring-[#0a3d62]">
                            Bahasa Indonesia
                        </label>
                        <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                            <input type="radio" wire:model="bahasa" value="en" class="text-[#0a3d62] focus:ring-[#0a3d62]">
                            English
                        </label>
                    </div>
                    @error('bahasa') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                </div>
                <p class="text-xs text-gray-400">Resume otomatis disusun dari data SOAP, tanda vital, diagnosis, tindakan, dan resep pada kunjungan ini. Field di bawah opsional — relevan untuk pasien yang butuh dokumen perjalanan/penerbangan.</p>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Escorted / Pendamping <span class="text-gray-400 text-xs font-normal">(opsional)</span></label>
                        <input type="text" wire:model="escorted" placeholder="mis. -"
                               class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm focus:ring-[#0a3d62] focus:border-[#0a3d62]">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Flight / Penerbangan <span class="text-gray-400 text-xs font-normal">(opsional)</span></label>
                        <input type="text" wire:model="flight" placeholder="mis. -"
                               class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm focus:ring-[#0a3d62] focus:border-[#0a3d62]">
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Recommendation / Rekomendasi <span class="text-gray-400 text-xs font-normal">(opsional)</span></label>
                    <input type="text" wire:model="recommendation" placeholder="mis. Fit to fly"
                           class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm focus:ring-[#0a3d62] focus:border-[#0a3d62]">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Medical Facilities at the Airport <span class="text-gray-400 text-xs font-normal">(opsional)</span></label>
                    <input type="text" wire:model="fasilitasBandara"
                           class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm focus:ring-[#0a3d62] focus:border-[#0a3d62]">
                </div>
                @endif

            </div>

            {{-- Footer modal --}}
            <div class="flex justify-end gap-3 px-6 py-4 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50 rounded-b-2xl">
                <button type="button" wire:click="{{ $editingSuratId ? 'batalEdit' : '$set(\'showModal\', false)' }}"
                        class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors">
                    Batal
                </button>
                <button type="button" wire:click="cetak" wire:loading.attr="disabled"
                        @if(!$editingSuratId && !$this->kunjungan?->soapNote?->is_final) disabled @endif
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
                    <span wire:loading.remove wire:target="cetak">{{ $editingSuratId ? 'Simpan Revisi' : 'Cetak PDF' }}</span>
                    <span wire:loading wire:target="cetak">Memproses...</span>
                </button>
            </div>
        </div>
    </div>
    @endif

    {{-- ── Prompt alasan revisi (edit surat yang sudah terbit) ────────── --}}
    @if($showRevisiPrompt)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" wire:click.self="batalPromptRevisi">
        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl w-full max-w-md p-5 space-y-3">
            <h4 class="text-sm font-bold text-gray-900 dark:text-white">Alasan Revisi Surat</h4>
            <p class="text-xs text-gray-500 dark:text-gray-400">
                Surat ini sudah terbit. Tuliskan alasan revisi -- akan tercatat di jejak audit
                bersama data sebelum & sesudah perubahan. Nomor surat & tanggal terbit tidak berubah.
            </p>
            <textarea wire:model="alasanRevisi" rows="3" placeholder="mis. Salah tanggal mulai istirahat, seharusnya..."
                      class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm focus:ring-[#0a3d62] focus:border-[#0a3d62]"></textarea>
            @error('alasanRevisi') <p class="text-xs text-red-500">{{ $message }}</p> @enderror
            <div class="flex justify-end gap-2 pt-1">
                <button type="button" wire:click="batalPromptRevisi" class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors">Batal</button>
                <button type="button" wire:click="konfirmasiRevisi" wire:loading.attr="disabled"
                        class="px-4 py-2 bg-[#0a3d62] hover:bg-[#1a5a8a] text-white text-sm font-medium rounded-lg transition-colors">
                    Lanjut Edit
                </button>
            </div>
        </div>
    </div>
    @endif

    {{-- ── Riwayat surat ─────────────────────────────────────── --}}
    {{-- w-full + basis-full: paksa item ini ambil baris flex-wrap sendiri
         di "Action Buttons", supaya tidak ikut memengaruhi ukuran
         tombol Batal Registrasi / Pasien Keluar (lihat detail-pemeriksaan.blade.php). --}}
    @if($this->riwayatSurat->isNotEmpty())
    <div class="w-full basis-full mt-4">
        <h4 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-2">Riwayat Surat Diterbitkan</h4>
        <div class="space-y-1.5">
            @foreach($this->riwayatSurat as $s)
            <div class="flex items-center justify-between px-3 py-2 bg-gray-50 dark:bg-gray-700/50 rounded-lg text-xs">
                <div class="flex items-center gap-2 min-w-0">
                    <span @class([
                        'px-2 py-0.5 rounded text-xs font-medium shrink-0',
                        'bg-emerald-100 text-emerald-700' => $s->tipe === 'keterangan_sehat',
                        'bg-red-100 text-red-700'         => $s->tipe === 'keterangan_sakit',
                        'bg-blue-100 text-blue-700'       => $s->tipe === 'rujukan',
                        'bg-amber-100 text-amber-700'     => $s->tipe === 'kontrol',
                        'bg-purple-100 text-purple-700'   => $s->tipe === 'resume_medis',
                    ])>{{ $s->label_tipe }}</span>
                    <span class="font-mono text-gray-600 dark:text-gray-300 shrink-0">{{ $s->nomor_surat }}</span>
                    @if($s->revision_count > 0)
                    <span class="text-amber-600 dark:text-amber-400 italic truncate" title="{{ $s->revision_reason }}">
                        direvisi {{ $s->revision_count }}x oleh {{ $s->revisedBy?->nama ?? '-' }}
                    </span>
                    @endif
                </div>
                <div class="flex items-center gap-3 text-gray-400 shrink-0">
                    <span>{{ $s->dicetak_pada->format('d/m/Y H:i') }}</span>
                    @can('surat.revisi')
                    <button type="button" wire:click="mulaiEdit({{ $s->id }})"
                            class="text-amber-600 hover:text-amber-800 dark:text-amber-400 font-medium transition-colors">
                        Edit
                    </button>
                    @endcan
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
