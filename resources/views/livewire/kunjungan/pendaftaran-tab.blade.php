<div class="max-w-2xl mx-auto space-y-5">

    {{-- Hasil Pendaftaran --}}
    @if ($showHasil)
    <div class="card border-emerald-300 dark:border-emerald-600 animate-fade-in">
        <div class="card-body text-center py-8">
            <div class="flex h-16 w-16 items-center justify-center rounded-full bg-emerald-100 dark:bg-emerald-900/40 mx-auto mb-4">
                <svg class="h-8 w-8 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
            </div>
            <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-1">Pasien Berhasil Didaftarkan</h3>
            <p class="text-gray-500 dark:text-gray-400 mb-4">{{ $namaPasienHasil }}</p>
            <div class="inline-block bg-[#0a3d62] text-white rounded-xl px-8 py-4 mb-4">
                <p class="text-xs uppercase tracking-widest opacity-70 mb-1">Nomor Antrean</p>
                <p class="text-5xl font-black">{{ $nomorAntrean }}</p>
            </div>
            <div class="flex justify-center gap-3 mt-2">
                <button wire:click="resetForm" class="btn-primary">Daftarkan Berikutnya</button>
                <a href="?tab=list" class="btn-secondary">Lihat List</a>
            </div>
        </div>
    </div>
    @else

    {{-- Pilih Mode --}}
    <div class="card">
        <div class="card-body">
            <div class="flex gap-3">
                @foreach (['appointment' => 'Dari Appointment', 'walkin' => 'Walk-in Langsung'] as $val => $lbl)
                <button type="button" wire:click="$set('mode', '{{ $val }}')"
                        @class([
                            'flex-1 py-3 px-4 rounded-xl border text-sm font-semibold transition-colors',
                            'border-[#0a3d62] bg-[#0a3d62] text-white' => $mode === $val,
                            'border-gray-200 text-gray-600 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-400' => $mode !== $val,
                        ])>
                    {{ $lbl }}
                </button>
                @endforeach
            </div>
        </div>
    </div>

    {{-- Skenario A: Dari Appointment --}}
    @if ($mode === 'appointment')
    <div class="card">
        <div class="card-header">
            <h3 class="text-sm font-semibold dark:text-white">Cari Appointment</h3>
        </div>
        <div class="card-body space-y-3">
            <div class="flex gap-2">
                <input wire:model="kodeBooking" type="text" placeholder="Kode Booking (BK-XXXXXXXX)"
                       class="form-input uppercase font-mono flex-1 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200"/>
                <button wire:click="cariAppointment" class="btn-primary" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="cariAppointment">Cari</span>
                    <span wire:loading wire:target="cariAppointment">...</span>
                </button>
            </div>
            @error('kodeBooking') <p class="form-error">{{ $message }}</p> @enderror

            @if ($appointmentData)
            <div class="rounded-lg border border-blue-200 bg-blue-50 dark:border-blue-700 dark:bg-blue-900/20 p-4 space-y-2 text-sm">
                <div class="flex justify-between">
                    <span class="text-gray-500">Pasien</span>
                    <span class="font-semibold text-gray-800 dark:text-gray-200">{{ $appointmentData->pasien->nama }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-500">No. RM</span>
                    <span class="font-mono text-gray-600 dark:text-gray-400">{{ $appointmentData->pasien->nomor_rm }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-500">Dokter</span>
                    <span class="text-gray-700 dark:text-gray-300">{{ $appointmentData->dokter->user->nama }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-500">Poli</span>
                    <span class="text-gray-700 dark:text-gray-300">{{ $appointmentData->poli->nama }}</span>
                </div>
                @if ($appointmentData->jadwalPraktek)
                <div class="flex justify-between">
                    <span class="text-gray-500">Jadwal</span>
                    <span class="text-gray-700 dark:text-gray-300">
                        {{ ucfirst($appointmentData->jadwalPraktek->hari) }}
                        {{ substr($appointmentData->jadwalPraktek->jam_mulai,0,5) }}–{{ substr($appointmentData->jadwalPraktek->jam_selesai,0,5) }}
                    </span>
                </div>
                @endif
            </div>
            @endif
        </div>
    </div>
    @endif

    {{-- Skenario B: Walk-in --}}
    @if ($mode === 'walkin')
    <div class="card">
        <div class="card-header">
            <h3 class="text-sm font-semibold dark:text-white">Cari Pasien</h3>
        </div>
        <div class="card-body space-y-3">
            <div class="form-group relative">
                <input wire:model.live.debounce.400ms="searchPasien" type="text"
                       placeholder="Cari nama / No. RM pasien..."
                       class="form-input dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200"/>
                @error('searchPasien') <p class="form-error">{{ $message }}</p> @enderror

                @if ($this->pasienSuggestions->isNotEmpty() && !$pasienId)
                <div class="absolute z-20 top-full left-0 right-0 mt-1 bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-600 shadow-lg max-h-48 overflow-y-auto">
                    @foreach ($this->pasienSuggestions as $p)
                    <button type="button" wire:click="pilihPasien({{ $p->id }}, '{{ addslashes($p->nama) }}')"
                            class="w-full text-left px-4 py-2.5 hover:bg-gray-50 dark:hover:bg-gray-700 text-sm">
                        <span class="font-medium text-gray-800 dark:text-gray-200">{{ $p->nama }}</span>
                        <span class="text-gray-400 ml-2 font-mono text-xs">{{ $p->nomor_rm }}</span>
                    </button>
                    @endforeach
                </div>
                @endif
            </div>

            @if ($pasienId)
            <div class="flex items-center justify-between rounded-lg bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-700 px-4 py-2">
                <span class="text-sm font-medium text-emerald-700 dark:text-emerald-400">✓ {{ $namaPasien }}</span>
                <button wire:click="$set('pasienId', null); $set('namaPasien', ''); $set('searchPasien', '')"
                        class="text-xs text-red-400 hover:text-red-600">Ganti</button>
            </div>
            @endif
        </div>
    </div>

    {{-- Pilih Dokter --}}
    <div class="card">
        <div class="card-header">
            <h3 class="text-sm font-semibold dark:text-white">Pilih Dokter (Jadwal Aktif Hari Ini)</h3>
        </div>
        <div class="card-body space-y-3">
            <select wire:model.live="filterSpesialisasi"
                    class="form-select w-48 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200">
                <option value="">Semua Spesialisasi</option>
                @foreach ($this->spesialisasiList as $s)
                    <option value="{{ $s }}">{{ $s }}</option>
                @endforeach
            </select>

            @if ($this->dokterList->isEmpty())
            <p class="text-sm text-center text-gray-400 py-4">Tidak ada dokter dengan jadwal aktif hari ini</p>
            @else
            <div class="space-y-2">
                @foreach ($this->dokterList as $d)
                    @foreach ($d->dokterPoli->filter(fn($dp) => $dp->jadwalPraktek->isNotEmpty()) as $dp)
                    @php $jadwal = $dp->jadwalPraktek->first(); @endphp
                    <button type="button"
                            wire:click="pilihDokter({{ $d->id }}, {{ $dp->poli_id }})"
                            @class([
                                'w-full flex items-center gap-3 rounded-xl border p-3 text-left transition-colors',
                                'border-[#0a3d62] bg-[#0a3d62]/5 dark:border-blue-500 dark:bg-blue-900/20' => $dokterId === $d->id && $poliId === $dp->poli_id,
                                'border-gray-200 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700' => !($dokterId === $d->id && $poliId === $dp->poli_id),
                            ])>
                        <div class="h-9 w-9 flex-shrink-0 rounded-full bg-[#0a3d62] flex items-center justify-center text-white font-bold text-sm">
                            {{ substr($d->user->nama, 0, 1) }}
                        </div>
                        <div class="flex-1">
                            <p class="font-medium text-sm text-gray-800 dark:text-gray-200">{{ $d->user->nama }}</p>
                            <p class="text-xs text-gray-500">{{ $dp->poli->nama }} · {{ $d->spesialisasi ?? 'Umum' }}</p>
                        </div>
                        <div class="text-right text-xs text-gray-500 dark:text-gray-400">
                            <p>{{ ucfirst($jadwal->hari) }}</p>
                            <p>{{ substr($jadwal->jam_mulai,0,5) }}–{{ substr($jadwal->jam_selesai,0,5) }}</p>
                            <p class="text-emerald-600">Kuota: {{ $jadwal->kuota_pasien }}</p>
                        </div>
                    </button>
                    @endforeach
                @endforeach
            </div>
            @endif
            @error('dokterId') <p class="form-error">{{ $message }}</p> @enderror
        </div>
    </div>
    @endif

    {{-- Data Tambahan --}}
    @if (($mode === 'appointment' && $appointmentData) || ($mode === 'walkin' && $dokterId))
    <div class="card">
        <div class="card-header">
            <h3 class="text-sm font-semibold dark:text-white">Data Pendaftaran</h3>
        </div>
        <div class="card-body space-y-4">
            <div class="form-group">
                <label class="form-label dark:text-gray-300">Penjamin <span class="text-red-500">*</span></label>
                <div class="flex gap-3">
                    @foreach (['umum' => 'Umum', 'bpjs' => 'BPJS', 'asuransi' => 'Asuransi'] as $val => $lbl)
                    <button type="button" wire:click="$set('tipePembayaran', '{{ $val }}')"
                            @class([
                                'flex-1 py-2 rounded-lg border text-sm font-medium transition-colors',
                                'border-[#0a3d62] bg-[#0a3d62] text-white' => $tipePembayaran === $val,
                                'border-gray-200 text-gray-600 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-400' => $tipePembayaran !== $val,
                            ])>{{ $lbl }}</button>
                    @endforeach
                </div>
            </div>

            <div class="form-group">
                <label class="form-label dark:text-gray-300">Keluhan</label>
                <textarea wire:model="keluhan" rows="2" placeholder="Keluhan utama pasien..."
                          class="form-textarea dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200"></textarea>
            </div>

            <button type="button" wire:click="daftar" class="btn-primary w-full py-3" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="daftar">
                    <svg class="w-4 h-4 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/>
                    </svg>
                    Daftarkan Pasien
                </span>
                <span wire:loading wire:target="daftar" class="flex items-center justify-center gap-2">
                    <div class="spinner h-4 w-4 border-white border-t-transparent"></div> Mendaftarkan...
                </span>
            </button>
        </div>
    </div>
    @endif

    @endif {{-- end !showHasil --}}
</div>
