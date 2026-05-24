<div>
    @if(!$kunjunganId || !$this->kunjungan)
    {{-- Empty state: belum pilih pasien --}}
    <div class="card">
        <div class="card-body py-16">
            <div class="empty-state">
                <svg class="empty-state-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                          d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                </svg>
                <p class="empty-state-text">Belum ada pasien dipilih</p>
                <p class="text-sm text-gray-400 mt-1">Pilih pasien dari tab <strong>Waiting Area</strong> lalu klik tombol <strong>Periksa</strong></p>
                <a href="{{ route('pemeriksaan.index', ['tab' => 'waiting']) }}"
                   class="btn-primary mt-4 inline-flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                    Ke Waiting Area
                </a>
            </div>
        </div>
    </div>

    @else
    @php
        $k       = $this->kunjungan;
        $pasien  = $k->pasien;
        $bmi     = $this->getBmi();
        $bmiLabel= $this->getBmiLabel();
        $adaAlergi = !empty($pasien?->alergi);
    @endphp

    {{-- ═══ HEADER PASIEN ═══ --}}
    <div class="card mb-4">
        <div class="card-body">
            <div class="flex flex-col sm:flex-row gap-4 items-start">
                {{-- Avatar --}}
                <div class="flex-shrink-0 h-16 w-16 rounded-full bg-gradient-to-br from-[#0a3d62] to-blue-400
                             flex items-center justify-center text-white text-2xl font-bold shadow">
                    {{ strtoupper(substr($pasien?->nama ?? 'P', 0, 1)) }}
                </div>

                {{-- Info Utama --}}
                <div class="flex-1 min-w-0">
                    <div class="flex flex-wrap items-start gap-3 mb-1">
                        <h3 class="text-lg font-bold text-gray-900 dark:text-white">{{ $pasien?->nama }}</h3>
                        <span class="badge badge-primary">{{ $pasien?->jenis_kelamin === 'L' ? 'Laki-laki' : 'Perempuan' }}</span>
                        <span class="badge badge-gray">{{ $pasien?->umur }} tahun</span>
                        @if($pasien?->golongan_darah)
                        <span class="badge badge-danger">Gol. {{ $pasien->golongan_darah }}</span>
                        @endif
                        <x-badge-status :status="$k->status" />
                    </div>
                    <div class="flex flex-wrap gap-4 text-sm text-gray-600 dark:text-gray-400">
                        <span><span class="text-xs uppercase tracking-wide">RM</span> <strong class="font-mono text-gray-800 dark:text-gray-200">{{ $pasien?->nomor_rm }}</strong></span>
                        <span><span class="text-xs uppercase tracking-wide">Antrean</span> <strong class="font-mono text-[#0a3d62] dark:text-blue-400">{{ $k->nomor_antrean }}</strong></span>
                        <span>Dokter: <strong class="text-gray-800 dark:text-gray-200">{{ $k->dokter?->user?->nama ?? '—' }}</strong></span>
                        <span>Poli: <strong class="text-gray-800 dark:text-gray-200">{{ $k->poli?->nama ?? '—' }}</strong></span>
                        <span>Daftar: <strong>{{ $k->tanggal->format('d/m/Y H:i') }}</strong></span>
                        @if($k->appointment)
                        <span class="text-blue-600 dark:text-blue-400">Booking: {{ $k->appointment->kode_booking }}</span>
                        @endif
                    </div>
                </div>

                {{-- Action Buttons --}}
                <div class="flex gap-2 flex-shrink-0">
                    @if(in_array($k->status, ['menunggu', 'dalam_pemeriksaan']))
                    <x-confirm-button
                        action="batalkanRegistrasi"
                        title="Batalkan Registrasi?"
                        text="Kunjungan pasien {{ $pasien?->nama }} akan dibatalkan."
                        confirm="Ya, Batalkan"
                        type="danger"
                        class="btn-danger btn-sm">
                        Batal Registrasi
                    </x-confirm-button>
                    @endif

                    @if($k->status === 'dalam_pemeriksaan')
                    <x-confirm-button
                        action="selesaiPemeriksaan"
                        title="Selesaikan Pemeriksaan?"
                        text="Status akan diubah ke Selesai. Pastikan data vital sudah disimpan."
                        confirm="Ya, Selesai"
                        type="success"
                        class="btn-success btn-sm">
                        Pasien Keluar / Selesai
                    </x-confirm-button>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- ═══ ALERT ALERGI & CATATAN PENTING ═══ --}}
    @if($adaAlergi || $k->catatan_penting)
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
        @if($adaAlergi)
        <div class="rounded-xl border-2 border-red-400 bg-red-50 dark:bg-red-900/20 px-4 py-3">
            <div class="flex items-center gap-2 mb-1">
                <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                </svg>
                <span class="font-bold text-red-700 dark:text-red-400 text-sm uppercase tracking-wide">ALERGI</span>
            </div>
            <p class="text-sm text-red-800 dark:text-red-300 font-medium">{{ $pasien->alergi }}</p>
        </div>
        @endif
        @if($k->catatan_penting)
        <div class="rounded-xl border-2 border-amber-400 bg-amber-50 dark:bg-amber-900/20 px-4 py-3">
            <div class="flex items-center gap-2 mb-1">
                <svg class="w-5 h-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <span class="font-bold text-amber-700 dark:text-amber-400 text-sm uppercase tracking-wide">Catatan Penting</span>
            </div>
            <p class="text-sm text-amber-800 dark:text-amber-300">{{ $k->catatan_penting }}</p>
        </div>
        @endif
    </div>
    @endif

    {{-- ═══ VITALS SUMMARY BAR ═══ --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-8 gap-3 mb-4">
        @php
            $vitals = [
                ['label' => 'BB', 'value' => $beratBadan ? $beratBadan.' kg' : '—', 'color' => 'gray'],
                ['label' => 'TB', 'value' => $tinggiBadan ? $tinggiBadan.' cm' : '—', 'color' => 'gray'],
                ['label' => 'BMI', 'value' => $bmi ? $bmi.' ('.$bmiLabel.')' : '—', 'color' => $bmi && $bmi >= 25 ? 'amber' : 'gray'],
                ['label' => 'TD', 'value' => $tekananDarah ?: '—', 'color' => 'gray'],
                ['label' => 'Nadi', 'value' => $nadi ? $nadi.' bpm' : '—', 'color' => 'gray'],
                ['label' => 'Suhu', 'value' => $suhu ? $suhu.'°C' : '—', 'color' => $suhu && $suhu > 37.5 ? 'red' : 'gray'],
                ['label' => 'SpO2', 'value' => $saturasi ? $saturasi.'%' : '—', 'color' => $saturasi && $saturasi < 95 ? 'red' : 'gray'],
                ['label' => 'GDS', 'value' => $gds ? $gds.' mg/dL' : '—', 'color' => 'gray'],
            ];
        @endphp
        @foreach($vitals as $v)
        <div class="card p-3 text-center">
            <p class="text-[10px] uppercase tracking-wider text-gray-400 dark:text-gray-500 mb-0.5">{{ $v['label'] }}</p>
            <p @class(['text-sm font-bold',
                'text-red-600'    => $v['color'] === 'red',
                'text-amber-600'  => $v['color'] === 'amber',
                'text-gray-800 dark:text-gray-200' => $v['color'] === 'gray',
            ])>{{ $v['value'] }}</p>
        </div>
        @endforeach
    </div>

    {{-- ═══ LAYOUT: SIDEBAR NAV + KONTEN ═══ --}}
    <div class="flex gap-4">
        {{-- Sidebar navigasi sub-modul --}}
        <div class="w-48 flex-shrink-0">
            <nav class="card p-2 space-y-0.5">
                @foreach([
                    'identitas' => ['label' => 'Data Identitas',      'icon' => 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z'],
                    'vitals'    => ['label' => 'Asesmen & Vital',      'icon' => 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z'],
                    'riwayat'   => ['label' => 'Riwayat Kunjungan',   'icon' => 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z'],
                    'notes'     => ['label' => 'Medical Notes',        'icon' => 'M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z'],
                    'penunjang' => ['label' => 'Penunjang Medis',      'icon' => 'M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z'],
                    'tindakan'  => ['label' => 'Procedure & Equipment','icon' => 'M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z'],
                    'obat'      => ['label' => 'Medication',            'icon' => 'M9 3H5a2 2 0 00-2 2v4m6-6h10a2 2 0 012 2v4M9 3v18m0 0h10a2 2 0 002-2V9M9 21H5a2 2 0 01-2-2V9m0 0h18'],
                ] as $key => $item)
                <button type="button" wire:click="$set('activeSection', '{{ $key }}')"
                        @class([
                            'w-full flex items-center gap-2 px-3 py-2 rounded-lg text-left text-sm transition-colors',
                            'bg-[#0a3d62] text-white font-medium' => $activeSection === $key,
                            'text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700' => $activeSection !== $key,
                        ])>
                    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $item['icon'] }}"/>
                    </svg>
                    <span class="truncate">{{ $item['label'] }}</span>
                </button>
                @endforeach
            </nav>
        </div>

        {{-- Konten utama --}}
        <div class="flex-1 min-w-0">

            {{-- ── Data Identitas ── --}}
            @if($activeSection === 'identitas')
            <div class="card">
                <div class="card-header">
                    <h3 class="text-sm font-semibold dark:text-white">Data Identitas Pasien</h3>
                </div>
                <div class="card-body">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-8 gap-y-3 text-sm">
                        @php
                            $fields = [
                                'Nomor RM'        => $pasien?->nomor_rm,
                                'NIK'             => $pasien?->nik ?? '—',
                                'Nama Lengkap'    => $pasien?->nama,
                                'Jenis Kelamin'   => $pasien?->jenis_kelamin === 'L' ? 'Laki-laki' : 'Perempuan',
                                'Tanggal Lahir'   => $pasien?->tanggal_lahir?->format('d/m/Y'),
                                'Umur'            => ($pasien?->umur ?? '?') . ' tahun',
                                'Golongan Darah'  => $pasien?->golongan_darah ?? '—',
                                'Tipe Pasien'     => strtoupper($pasien?->tipe_pasien ?? '—'),
                                'No. BPJS'        => $pasien?->no_bpjs ?? '—',
                                'No. Asuransi'    => $pasien?->no_asuransi ?? '—',
                                'Telepon'         => $pasien?->telepon ?? '—',
                                'Email'           => $pasien?->email ?? '—',
                                'Alamat'          => $pasien?->alamat ?? '—',
                            ];
                        @endphp
                        @foreach($fields as $label => $val)
                        <div class="flex gap-2 py-1 border-b border-gray-100 dark:border-gray-700">
                            <span class="w-36 text-gray-500 dark:text-gray-400 flex-shrink-0">{{ $label }}</span>
                            <span class="font-medium text-gray-900 dark:text-gray-100 break-words">{{ $val }}</span>
                        </div>
                        @endforeach

                        @if($adaAlergi)
                        <div class="sm:col-span-2 flex gap-2 py-1 border-b border-gray-100 dark:border-gray-700">
                            <span class="w-36 text-red-500 flex-shrink-0 font-semibold">⚠ Alergi</span>
                            <span class="font-semibold text-red-700 dark:text-red-400">{{ $pasien->alergi }}</span>
                        </div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- ── Asesmen & Vital ── --}}
            @elseif($activeSection === 'vitals')
            <div class="card">
                <div class="card-header">
                    <h3 class="text-sm font-semibold dark:text-white">Asesmen Perawat & Tanda Vital</h3>
                </div>
                <div class="card-body space-y-4">
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                        <div class="form-group">
                            <label class="form-label dark:text-gray-300">Berat Badan (kg)</label>
                            <input wire:model="beratBadan" type="number" step="0.1" placeholder="65.5"
                                   class="form-input dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200"/>
                            @error('beratBadan') <p class="form-error">{{ $message }}</p> @enderror
                        </div>
                        <div class="form-group">
                            <label class="form-label dark:text-gray-300">Tinggi Badan (cm)</label>
                            <input wire:model="tinggiBadan" type="number" step="0.1" placeholder="165"
                                   class="form-input dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200"/>
                            @error('tinggiBadan') <p class="form-error">{{ $message }}</p> @enderror
                        </div>
                        <div class="form-group">
                            <label class="form-label dark:text-gray-300">BMI (otomatis)</label>
                            <div class="form-input bg-gray-50 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-300">
                                {{ $bmi ? $bmi.' — '.$bmiLabel : '—' }}
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label dark:text-gray-300">Tekanan Darah</label>
                            <input wire:model="tekananDarah" type="text" placeholder="120/80"
                                   class="form-input dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200"/>
                        </div>
                        <div class="form-group">
                            <label class="form-label dark:text-gray-300">Nadi (bpm)</label>
                            <input wire:model="nadi" type="number" placeholder="80"
                                   class="form-input dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200"/>
                            @error('nadi') <p class="form-error">{{ $message }}</p> @enderror
                        </div>
                        <div class="form-group">
                            <label class="form-label dark:text-gray-300">Suhu (°C)</label>
                            <input wire:model="suhu" type="number" step="0.1" placeholder="36.5"
                                   class="form-input dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200"/>
                            @error('suhu') <p class="form-error">{{ $message }}</p> @enderror
                        </div>
                        <div class="form-group">
                            <label class="form-label dark:text-gray-300">SpO2 (%)</label>
                            <input wire:model="saturasi" type="number" placeholder="98"
                                   class="form-input dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200"/>
                            @error('saturasi') <p class="form-error">{{ $message }}</p> @enderror
                        </div>
                        <div class="form-group">
                            <label class="form-label dark:text-gray-300">GDS (mg/dL)</label>
                            <input wire:model="gds" type="number" step="0.1" placeholder="100"
                                   class="form-input dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200"/>
                            @error('gds') <p class="form-error">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div class="form-group">
                            <label class="form-label dark:text-gray-300">Asal Kedatangan</label>
                            <select wire:model="asalKedatangan"
                                    class="form-select dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200">
                                <option value="">— Pilih —</option>
                                <option value="keinginan_sendiri">Keinginan Sendiri</option>
                                <option value="rujukan_puskesmas">Rujukan Puskesmas</option>
                                <option value="rujukan_dokter">Rujukan Dokter Lain</option>
                                <option value="rujukan_rs">Rujukan RS</option>
                                <option value="kecelakaan">Kecelakaan / Emergency</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label dark:text-gray-300">Catatan Penting / Peringatan</label>
                            <input wire:model="catatanPenting" type="text"
                                   placeholder="Contoh: riwayat alergi penisilin, DM tidak terkontrol..."
                                   class="form-input dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200"/>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label dark:text-gray-300">Anamnesis / Keluhan Utama</label>
                        <textarea wire:model="anamnesisAwal" rows="3"
                                  placeholder="Pasien mengeluhkan..."
                                  class="form-input dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200"></textarea>
                    </div>

                    @if(in_array($k->status, ['menunggu', 'dalam_pemeriksaan']))
                    <div class="flex justify-end">
                        <button wire:click="simpanVitals" class="btn-primary" wire:loading.attr="disabled">
                            <span wire:loading.remove wire:target="simpanVitals">Simpan Data Vital & Asesmen</span>
                            <span wire:loading wire:target="simpanVitals" class="flex items-center gap-2">
                                <div class="spinner h-4 w-4 border-white border-t-transparent"></div> Menyimpan...
                            </span>
                        </button>
                    </div>
                    @endif
                </div>
            </div>

            {{-- ── Riwayat Kunjungan ── --}}
            @elseif($activeSection === 'riwayat')
            <div class="table-wrapper">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Tanggal</th>
                            <th>No. Antrean</th>
                            <th>Dokter / Poli</th>
                            <th>BB/TD/Suhu</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($this->riwayatKunjungan as $r)
                        <tr wire:key="rw-{{ $r->id }}">
                            <td class="text-sm text-gray-700 dark:text-gray-300">
                                {{ $r->tanggal->format('d/m/Y H:i') }}
                            </td>
                            <td class="font-mono font-semibold text-gray-600 dark:text-gray-400">{{ $r->nomor_antrean }}</td>
                            <td>
                                <p class="text-sm text-gray-700 dark:text-gray-300">{{ $r->dokter?->user?->nama ?? '—' }}</p>
                                <p class="text-xs text-gray-400">{{ $r->poli?->nama ?? '—' }}</p>
                            </td>
                            <td class="text-xs text-gray-500 dark:text-gray-400">
                                @if($r->asesmenPerawat)
                                    BB {{ $r->asesmenPerawat->berat_badan ?? '—' }} kg ·
                                    TD {{ $r->asesmenPerawat->tekanan_darah ?? '—' }} ·
                                    S {{ $r->asesmenPerawat->suhu ?? '—' }}°C
                                @else
                                    —
                                @endif
                            </td>
                            <td><x-badge-status :status="$r->status" /></td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="5">
                                <div class="empty-state py-8">
                                    <p class="empty-state-text">Belum ada riwayat kunjungan sebelumnya</p>
                                </div>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- ── Medical Notes (SOAP) ── --}}
            @elseif($activeSection === 'notes')
            <livewire:pemeriksaan.soap-note :kunjunganId="$kunjunganId" wire:key="soap-{{ $kunjunganId }}" />

            {{-- ── Penunjang Medis ── --}}
            @elseif($activeSection === 'penunjang')
            <livewire:pemeriksaan.penunjang :kunjunganId="$kunjunganId" wire:key="penunjang-{{ $kunjunganId }}" />

            {{-- ── Placeholder: Tindakan, Obat ── --}}
            @else
            <div class="card">
                <div class="card-body py-16">
                    <div class="empty-state">
                        <svg class="empty-state-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                  d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"/>
                        </svg>
                        <p class="empty-state-text">Modul dalam pengembangan</p>
                        <p class="text-sm text-gray-400 mt-1">Fitur ini akan tersedia di PRD berikutnya</p>
                    </div>
                </div>
            </div>
            @endif

        </div>
    </div>
    @endif
</div>
