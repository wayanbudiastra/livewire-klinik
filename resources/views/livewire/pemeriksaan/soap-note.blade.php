<div class="space-y-4">

    {{-- Header SOAP + Status --}}
    <div class="flex items-center justify-between">
        <div>
            <h3 class="text-sm font-semibold text-gray-900 dark:text-white">SOAP Note Dokter</h3>
            @if($isFinal)
            <div class="flex items-center gap-2 mt-1">
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold
                             bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                    </svg>
                    FINALISASI — Read Only
                </span>
            </div>
            @endif
        </div>

        @if(!$isFinal)
        <div class="flex gap-2">
            <button wire:click="simpan" class="btn-secondary btn-sm" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="simpan">Simpan Draft</span>
                <span wire:loading wire:target="simpan" class="flex items-center gap-1">
                    <div class="spinner h-3 w-3 border-gray-500 border-t-transparent"></div> Menyimpan...
                </span>
            </button>
            <x-confirm-button
                action="finalisasi"
                title="Finalisasi SOAP Note?"
                text="Data akan dikunci dan tidak dapat diubah lagi. Pastikan semua data sudah benar."
                confirm="Ya, Finalisasi"
                type="success"
                class="btn-primary btn-sm">
                Simpan & Finalisasi
            </x-confirm-button>
        </div>
        @endif
    </div>

    @error('diagnoses')
    <div class="rounded-lg border border-red-400 bg-red-50 dark:bg-red-900/20 px-4 py-2 text-sm text-red-700 dark:text-red-400">
        ⚠ {{ $message }}
    </div>
    @enderror

    {{-- Tab Navigasi S / O / A / P --}}
    <div class="border-b border-gray-200 dark:border-gray-700">
        <nav class="flex gap-0 -mb-px">
            @foreach([
                's' => ['label' => 'S — Subjective', 'color' => 'blue'],
                'o' => ['label' => 'O — Objective',  'color' => 'green'],
                'a' => ['label' => 'A — Assessment',  'color' => 'amber'],
                'p' => ['label' => 'P — Planning',    'color' => 'purple'],
            ] as $key => $tab)
            <button type="button" wire:click="$set('activeSection', '{{ $key }}')"
                    @class([
                        'px-4 py-2.5 text-sm font-medium border-b-2 transition-colors whitespace-nowrap',
                        'border-[#0a3d62] text-[#0a3d62] dark:border-blue-400 dark:text-blue-400' => $activeSection === $key,
                        'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400'  => $activeSection !== $key,
                    ])>
                {{ $tab['label'] }}
                @if($key === 'a' && count($diagnoses) > 0)
                <span class="ml-1.5 inline-flex items-center justify-center h-4 w-4 rounded-full text-[10px] font-bold bg-[#0a3d62] text-white">
                    {{ count($diagnoses) }}
                </span>
                @endif
            </button>
            @endforeach
        </nav>
    </div>

    {{-- ══════════ SUBJECTIVE ══════════ --}}
    @if($activeSection === 's')
    <div class="space-y-3">
        {{-- Vitals ringkas dari asesmen perawat --}}
        @if(count($this->vitals) > 0)
        <div class="rounded-lg border border-blue-200 dark:border-blue-800 bg-blue-50/50 dark:bg-blue-900/10 px-4 py-2">
            <p class="text-[10px] uppercase tracking-wider text-blue-500 dark:text-blue-400 mb-1.5">Tanda Vital (dari Asesmen Perawat)</p>
            <div class="flex flex-wrap gap-3">
                @foreach($this->vitals as $label => $val)
                <span class="text-xs text-blue-800 dark:text-blue-300">
                    <span class="font-semibold">{{ $label }}:</span> {{ $val }}
                </span>
                @endforeach
            </div>
        </div>
        @endif

        <div class="grid grid-cols-1 gap-3">
            <div class="form-group">
                <label class="form-label dark:text-gray-300">Chief Complaint & History of Present Illness (CC + HPI)</label>
                <textarea wire:model="{{ $isFinal ? '' : 'sCcHpi' }}" rows="3"
                          @if($isFinal) readonly @endif
                          placeholder="Pasien datang dengan keluhan... sejak... disertai..."
                          class="form-input dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200 {{ $isFinal ? 'bg-gray-50 cursor-not-allowed' : '' }}">{{ $sCcHpi }}</textarea>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div class="form-group">
                    <label class="form-label dark:text-gray-300">Past Medical History</label>
                    <textarea wire:model="{{ $isFinal ? '' : 'sPastMedical' }}" rows="2"
                              @if($isFinal) readonly @endif
                              placeholder="Hipertensi, DM, penyakit lain..."
                              class="form-input dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200 {{ $isFinal ? 'bg-gray-50 cursor-not-allowed' : '' }}">{{ $sPastMedical }}</textarea>
                </div>
                <div class="form-group">
                    <label class="form-label dark:text-gray-300">Past Surgical History</label>
                    <textarea wire:model="{{ $isFinal ? '' : 'sPastSurgical' }}" rows="2"
                              @if($isFinal) readonly @endif
                              placeholder="Riwayat operasi..."
                              class="form-input dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200 {{ $isFinal ? 'bg-gray-50 cursor-not-allowed' : '' }}">{{ $sPastSurgical }}</textarea>
                </div>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div class="form-group">
                    <label class="form-label dark:text-gray-300">
                        Allergies
                        @if(!empty($this->kunjungan?->pasien?->alergi))
                        <span class="text-red-500 text-xs ml-1">(Data dari rekam medis: {{ $this->kunjungan->pasien->alergi }})</span>
                        @endif
                    </label>
                    <textarea wire:model="{{ $isFinal ? '' : 'sAllergies' }}" rows="2"
                              @if($isFinal) readonly @endif
                              placeholder="Alergi obat, makanan, lainnya..."
                              class="form-input dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200 {{ $isFinal ? 'bg-gray-50 cursor-not-allowed' : '' }}">{{ $sAllergies }}</textarea>
                </div>
                <div class="form-group">
                    <label class="form-label dark:text-gray-300">Other (Subjective)</label>
                    <textarea wire:model="{{ $isFinal ? '' : 'sOther' }}" rows="2"
                              @if($isFinal) readonly @endif
                              placeholder="Catatan subjektif lainnya..."
                              class="form-input dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200 {{ $isFinal ? 'bg-gray-50 cursor-not-allowed' : '' }}">{{ $sOther }}</textarea>
                </div>
            </div>
        </div>
    </div>

    {{-- ══════════ OBJECTIVE ══════════ --}}
    @elseif($activeSection === 'o')
    <div class="space-y-3">
        {{-- Vitals tampilan dari asesmen --}}
        @if(count($this->vitals) > 0)
        <div class="card p-3">
            <p class="text-[10px] uppercase tracking-wider text-gray-400 dark:text-gray-500 mb-2">Tanda Vital (Auto dari Asesmen Perawat)</p>
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                @foreach($this->vitals as $label => $val)
                <div class="text-center">
                    <p class="text-[10px] text-gray-400 uppercase tracking-wide">{{ $label }}</p>
                    <p class="font-bold text-sm text-gray-800 dark:text-gray-200">{{ $val }}</p>
                </div>
                @endforeach
            </div>
            @if(count($this->vitals) === 0)
            <p class="text-xs text-gray-400 italic">Belum ada data vital dari asesmen perawat.</p>
            @endif
        </div>
        @else
        <div class="rounded-lg border border-amber-200 bg-amber-50 dark:bg-amber-900/10 px-3 py-2 text-xs text-amber-700 dark:text-amber-400">
            Belum ada data vital dari asesmen perawat untuk kunjungan ini.
        </div>
        @endif

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div class="form-group">
                <label class="form-label dark:text-gray-300">Physical Examination</label>
                <textarea wire:model="{{ $isFinal ? '' : 'oPhysicalExam' }}" rows="3"
                          @if($isFinal) readonly @endif
                          placeholder="Keadaan umum: baik, composmentis. Vital signs: ..."
                          class="form-input dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200 {{ $isFinal ? 'bg-gray-50 cursor-not-allowed' : '' }}">{{ $oPhysicalExam }}</textarea>
            </div>
            <div class="form-group">
                <label class="form-label dark:text-gray-300">Systemic Examination</label>
                <textarea wire:model="{{ $isFinal ? '' : 'oSystemicExam' }}" rows="3"
                          @if($isFinal) readonly @endif
                          placeholder="Kepala, Toraks, Abdomen, Ekstremitas..."
                          class="form-input dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200 {{ $isFinal ? 'bg-gray-50 cursor-not-allowed' : '' }}">{{ $oSystemicExam }}</textarea>
            </div>
            <div class="form-group">
                <label class="form-label dark:text-gray-300">Observation</label>
                <textarea wire:model="{{ $isFinal ? '' : 'oObservation' }}" rows="2"
                          @if($isFinal) readonly @endif
                          placeholder="Observasi umum pasien..."
                          class="form-input dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200 {{ $isFinal ? 'bg-gray-50 cursor-not-allowed' : '' }}">{{ $oObservation }}</textarea>
            </div>
            <div class="form-group">
                <label class="form-label dark:text-gray-300">Other (Objective)</label>
                <textarea wire:model="{{ $isFinal ? '' : 'oOther' }}" rows="2"
                          @if($isFinal) readonly @endif
                          placeholder="Catatan objektif lainnya..."
                          class="form-input dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200 {{ $isFinal ? 'bg-gray-50 cursor-not-allowed' : '' }}">{{ $oOther }}</textarea>
            </div>
        </div>
    </div>

    {{-- ══════════ ASSESSMENT ══════════ --}}
    @elseif($activeSection === 'a')
    <div class="space-y-4">
        {{-- ICD-10 Search --}}
        <div class="form-group">
            <label class="form-label dark:text-gray-300">
                Diagnosis ICD-10
                <span class="text-red-500">*</span>
                <span class="text-xs text-gray-400 ml-1">(wajib minimal 1 diagnosa utama)</span>
            </label>

            @if(!$isFinal)
            <div class="relative">
                <div class="relative">
                    <span class="absolute inset-y-0 left-3 flex items-center text-gray-400 pointer-events-none">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 11A6 6 0 1 1 5 11a6 6 0 0 1 12 0z"/>
                        </svg>
                    </span>
                    <input wire:model.live.debounce.350ms="searchIcd" type="text"
                           placeholder="Cari kode (J06.9) atau nama penyakit..."
                           class="form-input pl-9 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200"/>
                </div>

                {{-- Dropdown ICD-10 --}}
                @if($this->icdSuggestions->isNotEmpty())
                <div class="absolute z-30 top-full left-0 right-0 mt-1 bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-600 shadow-xl max-h-64 overflow-y-auto">
                    @foreach($this->icdSuggestions as $icd)
                    <button type="button" wire:click="addDiagnosis('{{ $icd->kode }}', @js($icd->nama))"
                            class="w-full text-left px-4 py-2.5 hover:bg-blue-50 dark:hover:bg-gray-700 border-b border-gray-100 dark:border-gray-700 last:border-0 transition-colors">
                        <div class="flex items-start gap-3">
                            <span class="font-mono font-bold text-[#0a3d62] dark:text-blue-400 text-sm flex-shrink-0 mt-0.5">{{ $icd->kode }}</span>
                            <div>
                                <span class="text-sm text-gray-900 dark:text-gray-100">{{ $icd->nama }}</span>
                                @if($icd->kategori)
                                <span class="block text-[10px] text-gray-400 mt-0.5">{{ $icd->kategori }}</span>
                                @endif
                            </div>
                        </div>
                    </button>
                    @endforeach
                </div>
                @endif
            </div>
            @endif

            {{-- Daftar Diagnosa Terpilih --}}
            @if(count($diagnoses) > 0)
            <div class="mt-3 space-y-2">
                @foreach($diagnoses as $i => $d)
                <div wire:key="dx-{{ $i }}" class="flex items-center gap-2 rounded-lg border px-3 py-2
                     {{ $d['is_primary'] ? 'border-[#0a3d62] bg-blue-50/50 dark:bg-blue-900/10' : 'border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-700/50' }}">
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2">
                            <span class="font-mono font-bold text-[#0a3d62] dark:text-blue-400 text-sm">{{ $d['kode'] }}</span>
                            @if($d['is_primary'])
                            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-bold
                                         bg-[#0a3d62] text-white uppercase">UTAMA</span>
                            @else
                            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium
                                         bg-gray-200 text-gray-600 dark:bg-gray-600 dark:text-gray-300 uppercase">Sekunder</span>
                            @endif
                        </div>
                        <p class="text-xs text-gray-600 dark:text-gray-400 truncate mt-0.5">{{ $d['nama'] }}</p>
                    </div>
                    @if(!$isFinal)
                    <div class="flex gap-1.5 flex-shrink-0">
                        @if(!$d['is_primary'])
                        <button wire:click="setPrimary({{ $i }})" title="Jadikan Utama"
                                class="text-xs text-blue-500 hover:text-blue-700 px-1.5 py-0.5 rounded border border-blue-200 hover:bg-blue-50">
                            ★ Utama
                        </button>
                        @endif
                        <button wire:click="removeDiagnosis({{ $i }})"
                                class="text-gray-400 hover:text-red-500 transition-colors p-1">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>
                    @endif
                </div>
                @endforeach
            </div>
            @elseif(!$isFinal)
            <p class="mt-2 text-xs text-gray-400 italic">Belum ada diagnosa. Cari dan pilih dari ICD-10 di atas.</p>
            @endif
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div class="form-group">
                <label class="form-label dark:text-gray-300">Problems</label>
                <textarea wire:model="{{ $isFinal ? '' : 'aProblems' }}" rows="2"
                          @if($isFinal) readonly @endif
                          placeholder="Daftar masalah klinis..."
                          class="form-input dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200 {{ $isFinal ? 'bg-gray-50 cursor-not-allowed' : '' }}">{{ $aProblems }}</textarea>
            </div>
            <div class="form-group">
                <label class="form-label dark:text-gray-300">Progress Note</label>
                <textarea wire:model="{{ $isFinal ? '' : 'aProgressNote' }}" rows="2"
                          @if($isFinal) readonly @endif
                          placeholder="Perkembangan klinis..."
                          class="form-input dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200 {{ $isFinal ? 'bg-gray-50 cursor-not-allowed' : '' }}">{{ $aProgressNote }}</textarea>
            </div>
            <div class="form-group sm:col-span-2">
                <label class="form-label dark:text-gray-300">Other (Assessment)</label>
                <textarea wire:model="{{ $isFinal ? '' : 'aOther' }}" rows="2"
                          @if($isFinal) readonly @endif
                          placeholder="Catatan asesmen lainnya..."
                          class="form-input dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200 {{ $isFinal ? 'bg-gray-50 cursor-not-allowed' : '' }}">{{ $aOther }}</textarea>
            </div>
        </div>
    </div>

    {{-- ══════════ PLANNING ══════════ --}}
    @elseif($activeSection === 'p')
    <div class="space-y-3">
        <div class="form-group">
            <label class="form-label dark:text-gray-300">Advice (Saran & Instruksi untuk Pasien)</label>
            <textarea wire:model="{{ $isFinal ? '' : 'pAdvice' }}" rows="4"
                      @if($isFinal) readonly @endif
                      placeholder="Anjuran diet, aktivitas, kontrol ulang, rujukan... "
                      class="form-input dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200 {{ $isFinal ? 'bg-gray-50 cursor-not-allowed' : '' }}">{{ $pAdvice }}</textarea>
        </div>
        <div class="form-group">
            <label class="form-label dark:text-gray-300">Other (Planning)</label>
            <textarea wire:model="{{ $isFinal ? '' : 'pOther' }}" rows="2"
                      @if($isFinal) readonly @endif
                      placeholder="Catatan perencanaan lainnya..."
                      class="form-input dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200 {{ $isFinal ? 'bg-gray-50 cursor-not-allowed' : '' }}">{{ $pOther }}</textarea>
        </div>
    </div>
    @endif

    {{-- Footer navigation --}}
    @if(!$isFinal)
    <div class="flex justify-between pt-2 border-t border-gray-100 dark:border-gray-700">
        <div>
            @if($activeSection !== 's')
            <button wire:click="$set('activeSection', '{{ ['o'=>'s','a'=>'o','p'=>'a'][$activeSection] }}')"
                    class="btn-secondary btn-sm">← Sebelumnya</button>
            @endif
        </div>
        <div>
            @if($activeSection !== 'p')
            <button wire:click="$set('activeSection', '{{ ['s'=>'o','o'=>'a','a'=>'p'][$activeSection] }}')"
                    class="btn-primary btn-sm">Selanjutnya →</button>
            @else
            <div class="flex gap-2">
                <button wire:click="simpan" class="btn-secondary btn-sm" wire:loading.attr="disabled">
                    Simpan Draft
                </button>
                <x-confirm-button
                    action="finalisasi"
                    title="Finalisasi SOAP Note?"
                    text="Data akan dikunci dan tidak dapat diubah lagi."
                    confirm="Ya, Finalisasi"
                    type="success"
                    class="btn-primary btn-sm">
                    Simpan & Finalisasi
                </x-confirm-button>
            </div>
            @endif
        </div>
    </div>
    @endif

</div>
