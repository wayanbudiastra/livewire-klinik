<div class="space-y-5">
<div class="grid grid-cols-1 xl:grid-cols-5 gap-5">
<div class="xl:col-span-3 space-y-5">

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
                {{-- Tipe Pasien --}}
                <div class="flex gap-2">
                    @foreach (['WNI' => '🇮🇩 WNI', 'WNA' => '🌐 WNA'] as $val => $lbl)
                    <button type="button" wire:click="$set('tipePasienBaru', '{{ $val }}')"
                            @class(['flex-1 py-2 rounded-lg border text-sm font-medium transition-colors',
                                'border-blue-500 bg-blue-50 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300' => $tipePasienBaru === $val,
                                'border-gray-200 text-gray-600 dark:border-gray-600' => $tipePasienBaru !== $val])>
                        {{ $lbl }}
                    </button>
                    @endforeach
                </div>

                <div class="form-group">
                    <label class="form-label dark:text-gray-300">Nama Lengkap <span class="text-red-500">*</span></label>
                    <input wire:model="namaInputBaru" type="text" placeholder="Sesuai KTP / Paspor"
                           class="form-input dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200"/>
                    @error('namaInputBaru') <p class="form-error">{{ $message }}</p> @enderror
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div class="form-group">
                        <label class="form-label dark:text-gray-300">Tempat Lahir <span class="text-red-500">*</span></label>
                        <input wire:model="tempatLahirBaru" type="text" placeholder="Kota / Kabupaten"
                               class="form-input dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200"/>
                        @error('tempatLahirBaru') <p class="form-error">{{ $message }}</p> @enderror
                    </div>
                    <div class="form-group">
                        <label class="form-label dark:text-gray-300">Tanggal Lahir <span class="text-red-500">*</span></label>
                        <input wire:model="tanggalLahirBaru" type="date"
                               class="form-input dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200"/>
                        @error('tanggalLahirBaru') <p class="form-error">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label dark:text-gray-300">Jenis Kelamin</label>
                    <div class="flex gap-4 mt-1">
                        @foreach (['L' => 'Laki-laki', 'P' => 'Perempuan'] as $val => $lbl)
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="radio" wire:model="jenisKelaminBaru" value="{{ $val }}" class="form-radio"/>
                            <span class="text-sm text-gray-700 dark:text-gray-300">{{ $lbl }}</span>
                        </label>
                        @endforeach
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label dark:text-gray-300">Alamat <span class="text-red-500">*</span></label>
                    <textarea wire:model="alamatBaru" rows="2" placeholder="Jl. Nama Jalan, Kelurahan, Kecamatan..."
                              class="form-textarea dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200"></textarea>
                    @error('alamatBaru') <p class="form-error">{{ $message }}</p> @enderror
                </div>

                <div class="grid grid-cols-2 gap-3">
                    @if ($tipePasienBaru === 'WNI')
                    <div class="form-group">
                        <label class="form-label dark:text-gray-300">NIK</label>
                        <input wire:model="nikInputBaru" type="text" maxlength="16" placeholder="16 digit angka"
                               class="form-input font-mono dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200"/>
                    </div>
                    @endif
                    <div class="form-group {{ $tipePasienBaru === 'WNA' ? 'col-span-2' : '' }}">
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
</div>{{-- end xl:col-span-3 --}}

{{-- ── Panel Kanan: List Appointment Hari Ini ─────────── --}}
<div class="xl:col-span-2">
    <div class="card sticky top-4">
        <div class="card-header">
            <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-100">
                Appointment
                <span class="text-xs font-normal text-gray-400 ml-1">
                    {{ $tanggalAppointment ? \Carbon\Carbon::parse($tanggalAppointment)->translatedFormat('d M Y') : 'Hari Ini' }}
                </span>
            </h3>
            <span class="badge-primary">{{ $this->appointmentList->count() }}</span>
        </div>
        <div class="card-body p-0 max-h-[600px] overflow-y-auto scrollbar-thin scrollbar-thumb-gray-200 dark:scrollbar-thumb-gray-700">
            @if ($this->appointmentList->isEmpty())
            <div class="empty-state py-10">
                <p class="empty-state-text">Belum ada appointment</p>
            </div>
            @else
            <div class="divide-y divide-gray-100 dark:divide-gray-700">
                @foreach ($this->appointmentList as $apt)
                <div @class([
                    'p-4 hover:bg-gray-50 dark:hover:bg-gray-700/30 transition-colors',
                    'opacity-60' => $apt->status === 'checked_in',
                ])>
                    <div class="flex items-start justify-between gap-2 mb-2">
                        <div class="flex-1 min-w-0">
                            <p class="font-semibold text-sm text-gray-900 dark:text-gray-100 truncate">
                                {{ $apt->pasien->nama }}
                            </p>
                            <p class="text-xs font-mono text-gray-400">{{ $apt->pasien->nomor_rm }}</p>
                        </div>
                        <span @class([
                            'inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium flex-shrink-0',
                            'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300' => $apt->status === 'booked',
                            'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300' => $apt->status === 'checked_in',
                        ])>
                            {{ $apt->status === 'booked' ? 'Booked' : 'Check-in' }}
                        </span>
                    </div>

                    <div class="text-xs text-gray-500 dark:text-gray-400 space-y-0.5 mb-3">
                        <p>👨‍⚕️ {{ $apt->dokter->user->nama }}</p>
                        <p>🏥 {{ $apt->poli->nama }}</p>
                        <p class="font-mono text-blue-600 dark:text-blue-400">{{ $apt->kode_booking }}</p>
                        @if ($apt->keluhan)
                        <p class="italic truncate">💬 {{ $apt->keluhan }}</p>
                        @endif
                    </div>

                    @if ($apt->status === 'booked')
                    <a href="{{ route('kunjungan.index', ['tab' => 'pendaftaran']) }}?kode={{ $apt->kode_booking }}"
                       class="btn-primary btn-sm w-full text-center">
                        <svg class="w-3.5 h-3.5 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/>
                        </svg>
                        Daftarkan Sekarang
                    </a>
                    @else
                    <span class="text-xs text-emerald-600 dark:text-emerald-400 font-medium">✓ Sudah check-in</span>
                    @endif
                </div>
                @endforeach
            </div>
            @endif
        </div>
    </div>
</div>

</div>{{-- end grid --}}
</div>{{-- end outer div --}}
