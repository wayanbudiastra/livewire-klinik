<div class="max-w-2xl mx-auto space-y-5">

    {{-- Hasil Appointment --}}
    @if ($showHasil)
    <div class="card border-emerald-300 dark:border-emerald-600 animate-fade-in">
        <div class="card-body text-center py-8">
            <div class="flex h-16 w-16 items-center justify-center rounded-full bg-emerald-100 dark:bg-emerald-900/40 mx-auto mb-4">
                <svg class="h-8 w-8 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
            </div>
            <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-2">Appointment Berhasil Dibuat</h3>
            <div class="inline-block bg-[#0a3d62] text-white rounded-xl px-8 py-4 mb-4">
                <p class="text-xs uppercase tracking-widest opacity-70 mb-1">Kode Booking</p>
                <p class="text-2xl font-black font-mono">{{ $kodeBooking }}</p>
            </div>
            <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">
                Tunjukkan kode ini saat check-in di loket pendaftaran.
            </p>
            <div class="flex justify-center gap-3">
                <button wire:click="resetForm" class="btn-primary">Buat Appointment Baru</button>
                <a href="?tab=pendaftaran" class="btn-secondary">Lanjut Daftarkan</a>
            </div>
        </div>
    </div>
    @else

    {{-- Step 1: Pilih Tanggal & Jadwal Dokter --}}
    <div class="card">
        <div class="card-header">
            <h3 class="text-sm font-semibold dark:text-white">Pilih Jadwal Dokter</h3>
        </div>
        <div class="card-body space-y-4">
            <div class="grid grid-cols-3 gap-3">
                <div class="form-group">
                    <label class="form-label dark:text-gray-300">Tanggal</label>
                    <input wire:model.live="tanggalAppointment" type="date"
                           min="{{ now()->toDateString() }}"
                           class="form-input dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200"/>
                    @error('tanggalAppointment') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                <div class="form-group col-span-2">
                    <label class="form-label dark:text-gray-300">Spesialisasi</label>
                    <select wire:model.live="filterSpesialisasi"
                            class="form-select dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200">
                        <option value="">Semua Spesialisasi</option>
                        @foreach ($this->spesialisasiList as $s)
                            <option value="{{ $s }}">{{ $s }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            @if ($this->dokterList->isEmpty())
            <p class="text-sm text-center text-gray-400 py-6">
                Tidak ada dokter dengan jadwal aktif pada tanggal / spesialisasi yang dipilih
            </p>
            @else
            <div class="space-y-2">
                @foreach ($this->dokterList as $d)
                    @foreach ($d->dokterPoli->filter(fn($dp) => $dp->jadwalPraktek->isNotEmpty()) as $dp)
                    @foreach ($dp->jadwalPraktek as $jadwal)
                    <button type="button"
                            wire:click="pilihJadwal({{ $jadwal->id }}, {{ $d->id }}, {{ $dp->poli_id }})"
                            @class([
                                'w-full flex items-center gap-3 rounded-xl border p-3 text-left transition-colors',
                                'border-[#0a3d62] bg-[#0a3d62]/5 dark:border-blue-500' => $selectedJadwalId === $jadwal->id,
                                'border-gray-200 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700' => $selectedJadwalId !== $jadwal->id,
                            ])>
                        <div class="h-10 w-10 flex-shrink-0 rounded-full bg-[#0a3d62] flex items-center justify-center text-white font-bold">
                            {{ substr($d->user->nama, 0, 1) }}
                        </div>
                        <div class="flex-1">
                            <p class="font-semibold text-sm text-gray-800 dark:text-gray-200">{{ $d->user->nama }}</p>
                            <p class="text-xs text-gray-500">{{ $dp->poli->nama }} · {{ $d->spesialisasi ?? 'Umum' }}</p>
                        </div>
                        <div class="text-right text-xs">
                            <p class="text-gray-600 dark:text-gray-400">{{ ucfirst($jadwal->hari) }}</p>
                            <p class="text-gray-500">{{ substr($jadwal->jam_mulai,0,5) }}–{{ substr($jadwal->jam_selesai,0,5) }}</p>
                            @if ($selectedJadwalId === $jadwal->id && $sisaKuota !== '')
                            <p @class(['font-semibold', 'text-emerald-600' => (int)$sisaKuota > 5, 'text-amber-500' => (int)$sisaKuota <= 5 && (int)$sisaKuota > 0, 'text-red-500' => (int)$sisaKuota === 0])>
                                Sisa: {{ $sisaKuota }}
                            </p>
                            @else
                            <p class="text-gray-400">Kuota: {{ $jadwal->kuota_pasien }}</p>
                            @endif
                        </div>
                    </button>
                    @endforeach
                    @endforeach
                @endforeach
            </div>
            @endif
            @error('selectedJadwalId') <p class="form-error">{{ $message }}</p> @enderror
        </div>
    </div>

    {{-- Step 2: Data Pasien --}}
    @if ($selectedJadwalId)
    <div class="card">
        <div class="card-header">
            <h3 class="text-sm font-semibold dark:text-white">Data Pasien</h3>
            <div class="flex gap-2">
                @foreach (['lama' => 'Pasien Lama', 'baru' => 'Pasien Baru'] as $val => $lbl)
                <button type="button" wire:click="$set('modePasien', '{{ $val }}')"
                        @class([
                            'px-3 py-1 rounded-lg text-xs font-medium border transition-colors',
                            'border-[#0a3d62] bg-[#0a3d62] text-white' => $modePasien === $val,
                            'border-gray-200 text-gray-600 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-400' => $modePasien !== $val,
                        ])>{{ $lbl }}</button>
                @endforeach
            </div>
        </div>
        <div class="card-body space-y-3">

            @if ($modePasien === 'lama')
            <div class="form-group relative">
                <label class="form-label dark:text-gray-300">Cari Pasien (No. RM / Nama)</label>
                <input wire:model.live.debounce.400ms="searchPasien" type="text"
                       placeholder="Ketik minimal 2 karakter..."
                       class="form-input dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200"/>
                @error('searchPasien') <p class="form-error">{{ $message }}</p> @enderror

                @if ($this->pasienSuggestions->isNotEmpty() && !$pasienId)
                <div class="absolute z-20 top-full left-0 right-0 mt-1 bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-600 shadow-lg max-h-48 overflow-y-auto">
                    @foreach ($this->pasienSuggestions as $p)
                    <button type="button" wire:click="pilihPasien({{ $p->id }}, '{{ addslashes($p->nama) }}')"
                            class="w-full text-left px-4 py-2.5 hover:bg-gray-50 dark:hover:bg-gray-700 text-sm border-b border-gray-100 dark:border-gray-700 last:border-0">
                        <span class="font-medium text-gray-800 dark:text-gray-200">{{ $p->nama }}</span>
                        <span class="text-gray-400 ml-2 text-xs font-mono">{{ $p->nomor_rm }}</span>
                        <x-tipe-pasien :tipe="$p->tipe_pasien" />
                    </button>
                    @endforeach
                </div>
                @endif

                @if ($pasienId)
                <div class="mt-2 flex items-center justify-between rounded-lg bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 px-4 py-2">
                    <span class="text-sm font-medium text-emerald-700 dark:text-emerald-400">✓ {{ $namaPasien }}</span>
                    <button wire:click="$set('pasienId', null); $set('namaPasien', ''); $set('searchPasien', '')"
                            class="text-xs text-red-400 hover:text-red-600">Ganti</button>
                </div>
                @endif
            </div>
            @else
            <div class="space-y-3">
                <div class="form-group">
                    <label class="form-label dark:text-gray-300">Nama Lengkap <span class="text-red-500">*</span></label>
                    <input wire:model="namaInputBaru" type="text" placeholder="Nama pasien"
                           class="form-input dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200"/>
                    @error('namaInputBaru') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div class="form-group">
                        <label class="form-label dark:text-gray-300">NIK (opsional)</label>
                        <input wire:model="nikInputBaru" type="text" maxlength="16" placeholder="16 digit"
                               class="form-input dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200"/>
                    </div>
                    <div class="form-group">
                        <label class="form-label dark:text-gray-300">No. HP <span class="text-red-500">*</span></label>
                        <input wire:model="hpInputBaru" type="tel" placeholder="08xxxxxxxxxx"
                               class="form-input dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200"/>
                        @error('hpInputBaru') <p class="form-error">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>
            @endif

            <div class="form-group">
                <label class="form-label dark:text-gray-300">Keluhan Utama</label>
                <textarea wire:model="keluhan" rows="2" placeholder="Tuliskan keluhan pasien (opsional)"
                          class="form-textarea dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200"></textarea>
            </div>

            <button type="button" wire:click="buatAppointment"
                    class="btn-primary w-full py-3" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="buatAppointment">
                    <svg class="w-4 h-4 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                    Buat Appointment
                </span>
                <span wire:loading wire:target="buatAppointment" class="flex items-center justify-center gap-2">
                    <div class="spinner h-4 w-4 border-white border-t-transparent"></div> Menyimpan...
                </span>
            </button>
        </div>
    </div>
    @endif

    @endif {{-- end !showHasil --}}
</div>
