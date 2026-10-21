<div class="space-y-5">

    {{-- Header No. RM (saat edit) --}}
    @if ($isEdit)
    <div class="flex items-center gap-3 p-3 rounded-lg bg-blue-50 border border-blue-200 dark:bg-blue-900/20 dark:border-blue-700">
        <svg class="h-5 w-5 text-blue-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2"/>
        </svg>
        <div>
            <p class="text-xs text-blue-600 dark:text-blue-400">Nomor Rekam Medis</p>
            <p class="font-mono font-bold text-blue-800 dark:text-blue-300">{{ $nomorRM }}</p>
        </div>
    </div>
    @endif

    {{-- ── Seksi 1: Identitas Utama ──────────────────────── --}}
    <div class="card">
        <div class="card-header">
            <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-100">Identitas Utama</h3>
        </div>
        <div class="card-body space-y-4">

            {{-- Tipe Pasien (bisa diubah juga saat edit: WNI <-> WNA) --}}
            <div class="form-group">
                <label class="form-label dark:text-gray-300">Tipe Pasien <span class="text-red-500">*</span></label>
                <div class="flex gap-3">
                    @foreach (['WNI' => '🇮🇩 WNI', 'WNA' => '🌐 WNA'] as $val => $lbl)
                    <button type="button" wire:click="$set('tipe_pasien', '{{ $val }}')"
                            @class([
                                'flex-1 py-2.5 px-4 rounded-lg border text-sm font-medium transition-colors',
                                'border-blue-500 bg-blue-50 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300 dark:border-blue-500' => $tipe_pasien === $val,
                                'border-gray-200 hover:bg-gray-50 text-gray-600 dark:border-gray-600 dark:text-gray-400 dark:hover:bg-gray-700' => $tipe_pasien !== $val,
                            ])>
                        {{ $lbl }}
                    </button>
                    @endforeach
                </div>
                @if ($isEdit)
                <p class="text-xs text-amber-600 dark:text-amber-400 mt-1">
                    Mengubah tipe pasien akan mengosongkan data identitas tipe sebelumnya
                    (NIK/BPJS untuk WNI, No. Paspor/Negara Asal untuk WNA) saat disimpan.
                </p>
                @endif
                @error('tipe_pasien') <p class="form-error">{{ $message }}</p> @enderror
            </div>

            <div class="form-group">
                <label class="form-label dark:text-gray-300">Nama Lengkap <span class="text-red-500">*</span></label>
                <input wire:model="nama" type="text" placeholder="Sesuai KTP / Paspor"
                       class="form-input dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200"/>
                @error('nama') <p class="form-error">{{ $message }}</p> @enderror
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div class="form-group">
                    <label class="form-label dark:text-gray-300">Tempat Lahir <span class="text-red-500">*</span></label>
                    <input wire:model="tempat_lahir" type="text" placeholder="Kota / Kabupaten"
                           class="form-input dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200"/>
                    @error('tempat_lahir') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                <div class="form-group">
                    <label class="form-label dark:text-gray-300">Tanggal Lahir <span class="text-red-500">*</span></label>
                    <input wire:model="tanggal_lahir" type="date"
                           class="form-input dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200"/>
                    @error('tanggal_lahir') <p class="form-error">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div class="form-group">
                    <label class="form-label dark:text-gray-300">Jenis Kelamin <span class="text-red-500">*</span></label>
                    <div class="flex gap-3 mt-1">
                        @foreach (['L' => 'Laki-laki', 'P' => 'Perempuan'] as $val => $lbl)
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="radio" wire:model="jenis_kelamin" value="{{ $val }}"
                                   class="form-radio"/>
                            <span class="text-sm text-gray-700 dark:text-gray-300">{{ $lbl }}</span>
                        </label>
                        @endforeach
                    </div>
                    @error('jenis_kelamin') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                <div class="form-group">
                    <label class="form-label dark:text-gray-300">Golongan Darah</label>
                    <select wire:model="golongan_darah"
                            class="form-select dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200">
                        <option value="">— Pilih —</option>
                        @foreach (['A','B','AB','O'] as $g)
                            <option value="{{ $g }}">{{ $g }}</option>
                        @endforeach
                        <option value="tidak_diketahui">Tidak diketahui</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    {{-- ── Seksi 2: Identifikasi & Kontak ──────────────────── --}}
    <div class="card">
        <div class="card-header">
            <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-100">Identifikasi Legal & Kontak</h3>
        </div>
        <div class="card-body space-y-4">

            {{-- WNI --}}
            @if ($tipe_pasien === 'WNI')
            <div class="grid grid-cols-2 gap-4">
                <div class="form-group">
                    <label class="form-label dark:text-gray-300">
                        NIK <span class="text-red-500">*</span>
                        <span class="text-xs text-blue-500 ml-1">(Wajib WNI)</span>
                    </label>
                    <input wire:model="nik" type="text" maxlength="16" placeholder="16 digit angka"
                           class="form-input font-mono dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200"/>
                    @error('nik') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                <div class="form-group">
                    <label class="form-label dark:text-gray-300">Nomor BPJS</label>
                    <input wire:model="no_bpjs" type="text" maxlength="13" placeholder="13 digit"
                           class="form-input font-mono dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200"/>
                    @error('no_bpjs') <p class="form-error">{{ $message }}</p> @enderror
                </div>
            </div>
            @endif

            {{-- WNA --}}
            @if ($tipe_pasien === 'WNA')
            <div class="grid grid-cols-2 gap-4">
                <div class="form-group">
                    <label class="form-label dark:text-gray-300">
                        No. Paspor <span class="text-red-500">*</span>
                        <span class="text-xs text-violet-500 ml-1">(Wajib WNA)</span>
                    </label>
                    <input wire:model="no_paspor" type="text" placeholder="Contoh: B1234567"
                           class="form-input uppercase font-mono dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200"/>
                    @error('no_paspor') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                <div class="form-group">
                    <label class="form-label dark:text-gray-300">Negara Asal <span class="text-red-500">*</span></label>
                    <input wire:model="negara_asal" type="text" placeholder="Amerika Serikat"
                           class="form-input dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200"/>
                    @error('negara_asal') <p class="form-error">{{ $message }}</p> @enderror
                </div>
            </div>
            <div class="form-group">
                <label class="form-label dark:text-gray-300">No. Asuransi</label>
                <input wire:model="no_asuransi" type="text" placeholder="Nomor asuransi swasta"
                       class="form-input dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200"/>
            </div>
            @endif

            <div class="form-group">
                <label class="form-label dark:text-gray-300">Alamat Domisili <span class="text-red-500">*</span></label>
                <textarea wire:model="alamat" rows="2"
                          placeholder="Jl. Nama Jalan No. xx, Kelurahan, Kecamatan, Kota"
                          class="form-textarea dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200"></textarea>
                @error('alamat') <p class="form-error">{{ $message }}</p> @enderror
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div class="form-group">
                    <label class="form-label dark:text-gray-300">No. HP <span class="text-red-500">*</span></label>
                    <input wire:model="telepon" type="tel" placeholder="08xxxxxxxxxx atau +xxxxxxxxxx"
                           class="form-input dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200"/>
                    @error('telepon') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                <div class="form-group">
                    <label class="form-label dark:text-gray-300">Email</label>
                    <input wire:model="email" type="email" placeholder="contoh@email.com"
                           class="form-input dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200"/>
                    @error('email') <p class="form-error">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="form-group">
                <label class="form-label dark:text-gray-300">Riwayat Alergi</label>
                <input wire:model="alergi" type="text"
                       placeholder="Penisilin, Seafood — kosongkan jika tidak ada"
                       class="form-input dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200"/>
            </div>
        </div>
    </div>

    {{-- ── Seksi 3: Kontak Darurat ──────────────────────────── --}}
    <div class="card">
        <div class="card-header">
            <div class="flex items-center justify-between w-full">
                <div class="flex items-center gap-2">
                    <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-100">
                        Kontak Darurat <span class="text-red-500">*</span>
                        <span class="text-xs font-normal text-gray-400 ml-1">(Wajib minimal 1)</span>
                    </h3>
                    <span class="inline-flex items-center justify-center w-5 h-5 text-xs font-bold text-white bg-blue-600 rounded-full dark:bg-blue-500">
                        {{ count($kontak) }}
                    </span>
                </div>
                <button type="button" wire:click="addKontak" class="btn-secondary btn-sm">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    Tambah Kontak
                </button>
            </div>
        </div>
        <div class="card-body space-y-3">
            @error('kontak') 
            <p class="form-error bg-red-50 dark:bg-red-900/20 p-2 rounded border border-red-200 dark:border-red-700">{{ $message }}</p> 
            @enderror
            
            @if (empty($kontak))
            <p class="text-sm text-center text-gray-400 py-3">
                Belum ada kontak darurat. Klik "Tambah Kontak" untuk menambahkan.
            </p>
            @endif

            @foreach ($kontak as $i => $k)
            <div @class([
                'rounded-lg border p-3 space-y-3',
                'border-blue-300 bg-blue-50/40 dark:border-blue-600 dark:bg-blue-900/10' => $k['is_primary'],
                'border-gray-200 dark:border-gray-600' => !$k['is_primary'],
            ])>
                <div class="flex items-center justify-between">
                    <span @class([
                        'text-xs font-semibold',
                        'text-blue-700 dark:text-blue-400' => $k['is_primary'],
                        'text-gray-500 dark:text-gray-400' => !$k['is_primary'],
                    ])>
                        {{ $k['is_primary'] ? '★ Kontak Utama' : "Kontak " . ($i + 1) }}
                    </span>
                    <div class="flex gap-2">
                        @if (!$k['is_primary'])
                        <button type="button" wire:click="setPrimaryKontak({{ $i }})"
                                class="text-xs text-blue-600 hover:text-blue-800 dark:text-blue-400">
                            Jadikan Utama
                        </button>
                        @endif
                        <button type="button" wire:click="confirmDeleteKontak({{ $i }})"
                                @if(count($kontak) <= 1) disabled @endif
                                @class([
                                    'text-xs text-red-500 hover:text-red-700',
                                    'opacity-50 cursor-not-allowed' => count($kontak) <= 1,
                                ])
                                title="{{ count($kontak) <= 1 ? 'Tambahkan kontak baru sebelum menghapus kontak terakhir' : '' }}">
                            Hapus
                        </button>
                    </div>
                </div>

                <div class="grid grid-cols-3 gap-3">
                    <div class="form-group">
                        <label class="form-label text-xs dark:text-gray-400">Nama <span class="text-red-500">*</span></label>
                        <input wire:model="kontak.{{ $i }}.nama" type="text" placeholder="Nama kontak"
                               class="form-input dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200"/>
                        @error("kontak.{$i}.nama") <p class="form-error">{{ $message }}</p> @enderror
                    </div>
                    <div class="form-group">
                        <label class="form-label text-xs dark:text-gray-400">Hubungan <span class="text-red-500">*</span></label>
                        <select wire:model="kontak.{{ $i }}.hubungan"
                                class="form-select dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200">
                            @foreach ($this->hubunganOptions as $val => $lbl)
                                <option value="{{ $val }}">{{ $lbl }}</option>
                            @endforeach
                        </select>
                        @error("kontak.{$i}.hubungan") <p class="form-error">{{ $message }}</p> @enderror
                    </div>
                    <div class="form-group">
                        <label class="form-label text-xs dark:text-gray-400">No. HP <span class="text-red-500">*</span></label>
                        <div class="flex items-center gap-2">
                            <input wire:model="kontak.{{ $i }}.nomor_hp" type="tel" placeholder="08xxxxxxxxxx"
                                   class="form-input flex-1 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200"/>
                            @if ($kontak[$i]['nomor_hp'])
                            <button type="button" 
                                    @click="navigator.clipboard.writeText('{{ $kontak[$i]['nomor_hp'] }}'); $wire.copyPhoneNumber({{ $i }});"
                                    @class([
                                        'px-2 py-1 text-xs rounded transition-colors',
                                        'bg-green-500 text-white' => $copiedPhoneIndex === $i,
                                        'bg-gray-200 text-gray-600 hover:bg-gray-300 dark:bg-gray-700 dark:text-gray-300' => $copiedPhoneIndex !== $i,
                                    ])
                                    title="Copy nomor HP">
                                {{ $copiedPhoneIndex === $i ? '✓ Copied' : 'Copy' }}
                            </button>
                            @endif
                        </div>
                        @error("kontak.{$i}.nomor_hp") <p class="form-error">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>
            @endforeach
        </div>

        {{-- Delete Confirmation Dialog --}}
        @if ($showDeleteConfirm && $deleteKontakIndex !== null && isset($kontak[$deleteKontakIndex]))
        <div class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 dark:bg-black/60">
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl p-6 max-w-sm mx-4">
                <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-2">
                    Konfirmasi Hapus Kontak
                </h3>
                <p class="text-gray-600 dark:text-gray-300 mb-4">
                    Anda yakin ingin menghapus kontak <span class="font-semibold">{{ $kontak[$deleteKontakIndex]['nama'] }}</span>?
                </p>
                <div class="flex gap-3 justify-end">
                    <button type="button" wire:click="cancelDeleteKontak()"
                            class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600 rounded-lg">
                        Batal
                    </button>
                    <button type="button" wire:click="removeKontak({{ $deleteKontakIndex }})"
                            class="px-4 py-2 text-sm font-medium text-white bg-red-600 hover:bg-red-700 dark:bg-red-600 dark:hover:bg-red-700 rounded-lg">
                        Hapus
                    </button>
                </div>
            </div>
        </div>
        @endif
    </div>

    {{-- ── Section: Sumber Informasi ───────────────────────── --}}
    <div class="card border-l-4 border-blue-400">
        <div class="card-header">
            <h3 class="text-sm font-semibold text-gray-700">
                Sumber Informasi
                @if(!$isEdit)<span class="text-red-500 ml-1">*</span>@endif
            </h3>
        </div>
        <div class="card-body space-y-4">
            <p class="text-sm text-gray-500">Dari mana pasien mengetahui klinik kami?</p>

            <div class="grid grid-cols-2 md:grid-cols-4 gap-2">
                @foreach($daftarSumber as $sumber)
                <button type="button"
                    wire:click="$set('sumberInformasiId', {{ $sumber->id }})"
                    @class([
                        'py-3 px-3 rounded-lg border text-sm font-medium transition-all text-center',
                        'bg-blue-50 border-blue-500 text-blue-700 ring-1 ring-blue-500' => $sumberInformasiId == $sumber->id,
                        'border-gray-200 hover:bg-gray-50 text-gray-700' => $sumberInformasiId != $sumber->id,
                    ])>
                    <div class="text-xl mb-1">{{ $sumber->icon }}</div>
                    <div class="leading-tight">{{ $sumber->nama }}</div>
                </button>
                @endforeach
            </div>
            @error('sumberInformasiId') <p class="form-error">{{ $message }}</p> @enderror

            @if($sumberButuhKeterangan)
            <div class="form-group">
                <label class="form-label">
                    Sebutkan sumber lainnya
                    @if(!$isEdit)<span class="text-red-500">*</span>@endif
                </label>
                <input type="text" wire:model.blur="sumberKeterangan"
                    class="form-input @error('sumberKeterangan') border-red-400 @enderror"
                    placeholder="Contoh: YouTube, papan reklame, event kesehatan, dll" />
                @error('sumberKeterangan') <p class="form-error">{{ $message }}</p> @enderror
            </div>
            @endif
        </div>
    </div>

    {{-- ── Tombol Simpan ────────────────────────────────────── --}}
    <div class="flex justify-end gap-3">
        <a href="{{ $isEdit ? route('pasien.show', $pasienId) : route('pasien.index') }}"
           class="btn-secondary">Batal</a>
        <button type="button" wire:click="save" class="btn-primary" wire:loading.attr="disabled">
            <span wire:loading.remove wire:target="save">
                {{ $isEdit ? 'Simpan Perubahan' : 'Daftarkan Pasien' }}
            </span>
            <span wire:loading wire:target="save" class="flex items-center gap-2">
                <div class="spinner h-4 w-4 border-white border-t-transparent"></div> Menyimpan...
            </span>
        </button>
    </div>
</div>
