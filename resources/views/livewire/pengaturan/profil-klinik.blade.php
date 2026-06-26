<div class="space-y-6">

    {{-- Seksi: Informasi Umum --}}
    <div class="card">
        <div class="card-header">
            <div>
                <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Informasi Umum</h3>
                <p class="text-xs text-gray-400 mt-0.5">Identitas resmi fasilitas kesehatan</p>
            </div>
        </div>
        <div class="card-body grid grid-cols-1 md:grid-cols-2 gap-5">
            <div class="form-group md:col-span-2">
                <label class="form-label">Nama Klinik / RS <span class="text-red-500">*</span></label>
                <input type="text" wire:model="nama" placeholder="Misal: Klinik Sehat Bersama"
                    class="form-input dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200" />
                @error('nama') <p class="form-error">{{ $message }}</p> @enderror
            </div>
            <div class="form-group">
                <label class="form-label">Jenis Fasilitas</label>
                <select wire:model="jenis" class="form-input dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200">
                    <option value="">-- Pilih Jenis --</option>
                    <option value="Klinik Pratama">Klinik Pratama</option>
                    <option value="Klinik Utama">Klinik Utama</option>
                    <option value="Rumah Sakit Umum">Rumah Sakit Umum</option>
                    <option value="Rumah Sakit Khusus">Rumah Sakit Khusus</option>
                    <option value="Puskesmas">Puskesmas</option>
                    <option value="Apotek">Apotek</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Nomor Izin Operasional</label>
                <input type="text" wire:model="nomor_izin" placeholder="Misal: 503/DINKES/2024/001"
                    class="form-input dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200" />
            </div>
            <div class="form-group">
                <label class="form-label">NPWP</label>
                <input type="text" wire:model="npwp" placeholder="00.000.000.0-000.000"
                    class="form-input dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200" />
                @error('npwp') <p class="form-error">{{ $message }}</p> @enderror
            </div>
            <div class="form-group">
                <label class="form-label">Nama Pimpinan</label>
                <input type="text" wire:model="nama_pimpinan" placeholder="dr. Budi Santoso, Sp.PD"
                    class="form-input dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200" />
            </div>
            <div class="form-group">
                <label class="form-label">Jabatan Pimpinan</label>
                <input type="text" wire:model="jabatan_pimpinan" placeholder="Misal: Direktur / Kepala Klinik"
                    class="form-input dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200" />
            </div>
        </div>
    </div>

    {{-- Seksi: Lokasi --}}
    <div class="card">
        <div class="card-header">
            <div>
                <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Lokasi</h3>
                <p class="text-xs text-gray-400 mt-0.5">Alamat lengkap fasilitas</p>
            </div>
        </div>
        <div class="card-body grid grid-cols-1 md:grid-cols-3 gap-5">
            <div class="form-group md:col-span-3">
                <label class="form-label">Alamat Lengkap</label>
                <textarea wire:model="alamat" rows="3" placeholder="Jl. Contoh No. 1, RT 01/RW 01..."
                    class="form-input dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200"></textarea>
            </div>
            <div class="form-group">
                <label class="form-label">Kota / Kabupaten</label>
                <input type="text" wire:model="kota" placeholder="Denpasar"
                    class="form-input dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200" />
            </div>
            <div class="form-group">
                <label class="form-label">Provinsi</label>
                <input type="text" wire:model="provinsi" placeholder="Bali"
                    class="form-input dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200" />
            </div>
            <div class="form-group">
                <label class="form-label">Kode Pos</label>
                <input type="text" wire:model="kode_pos" placeholder="80234"
                    class="form-input dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200" maxlength="6" />
                @error('kode_pos') <p class="form-error">{{ $message }}</p> @enderror
            </div>
        </div>
    </div>

    {{-- Seksi: Kontak --}}
    <div class="card">
        <div class="card-header">
            <div>
                <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Kontak</h3>
                <p class="text-xs text-gray-400 mt-0.5">Nomor telepon, email, dan website</p>
            </div>
        </div>
        <div class="card-body grid grid-cols-1 md:grid-cols-2 gap-5">
            <div class="form-group">
                <label class="form-label">Telepon</label>
                <input type="text" wire:model="telepon" placeholder="(0361) 123456"
                    class="form-input dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200" />
            </div>
            <div class="form-group">
                <label class="form-label">Fax</label>
                <input type="text" wire:model="fax" placeholder="(0361) 123457"
                    class="form-input dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200" />
            </div>
            <div class="form-group">
                <label class="form-label">Email</label>
                <input type="email" wire:model="email" placeholder="info@klinik.com"
                    class="form-input dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200" />
                @error('email') <p class="form-error">{{ $message }}</p> @enderror
            </div>
            <div class="form-group">
                <label class="form-label">Website</label>
                <input type="url" wire:model="website" placeholder="https://klinik.com"
                    class="form-input dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200" />
                @error('website') <p class="form-error">{{ $message }}</p> @enderror
            </div>
        </div>
    </div>

    {{-- Seksi: Header & Footer Struk --}}
    <div class="card">
        <div class="card-header">
            <div>
                <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Teks Struk / Invoice</h3>
                <p class="text-xs text-gray-400 mt-0.5">Teks tambahan yang muncul di bagian atas dan bawah setiap struk</p>
            </div>
        </div>
        <div class="card-body grid grid-cols-1 md:grid-cols-2 gap-5">
            <div class="form-group">
                <label class="form-label">Header Struk</label>
                <textarea wire:model="header_struk" rows="3"
                    placeholder="Misal: Melayani dengan sepenuh hati&#10;Buka 24 jam setiap hari"
                    class="form-input dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200"></textarea>
                <p class="text-xs text-gray-400 mt-1">Ditampilkan di bawah nama klinik pada header struk.</p>
            </div>
            <div class="form-group">
                <label class="form-label">Footer Struk</label>
                <textarea wire:model="footer_struk" rows="3"
                    placeholder="Misal: Terima kasih atas kepercayaan Anda&#10;Semoga lekas sembuh"
                    class="form-input dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200"></textarea>
                <p class="text-xs text-gray-400 mt-1">Ditampilkan di bagian bawah struk sebagai penutup.</p>
            </div>
        </div>
    </div>

    {{-- Seksi: Logo --}}
    <div class="card">
        <div class="card-header">
            <div>
                <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Logo Klinik</h3>
                <p class="text-xs text-gray-400 mt-0.5">JPG, PNG, atau WEBP — maks. 2 MB</p>
            </div>
        </div>
        <div class="card-body">
            <div class="flex flex-col sm:flex-row gap-6 items-start">
                {{-- Preview --}}
                <div class="flex-shrink-0">
                    @if($logoFile)
                        <img src="{{ $logoFile->temporaryUrl() }}"
                             alt="Preview Logo" class="h-28 w-28 rounded-xl object-contain border border-gray-200 dark:border-gray-600 bg-white p-2" />
                    @elseif($logoPath)
                        <img src="{{ asset('storage/' . $logoPath) }}"
                             alt="Logo Klinik" class="h-28 w-28 rounded-xl object-contain border border-gray-200 dark:border-gray-600 bg-white p-2" />
                    @else
                        <div class="h-28 w-28 rounded-xl border-2 border-dashed border-gray-300 dark:border-gray-600 flex items-center justify-center bg-gray-50 dark:bg-gray-800">
                            <svg class="w-10 h-10 text-gray-300 dark:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                      d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                        </div>
                    @endif
                </div>

                {{-- Upload & Hapus --}}
                <div class="flex-1 space-y-3">
                    <div>
                        <label class="form-label">Ganti Logo</label>
                        <input type="file" wire:model="logoFile" accept="image/*"
                            class="block w-full text-sm text-gray-500 dark:text-gray-400
                                   file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0
                                   file:text-sm file:font-medium file:bg-blue-50 file:text-blue-700
                                   hover:file:bg-blue-100 dark:file:bg-blue-900/30 dark:file:text-blue-400" />
                        @error('logoFile') <p class="form-error mt-1">{{ $message }}</p> @enderror
                    </div>

                    @if($logoPath && !$logoFile)
                    <x-confirm-button action="hapusLogo" title="Hapus Logo Klinik?"
                        icon="warning" type="danger" confirm="Ya, Hapus"
                        wire:loading.attr="disabled"
                        class="text-sm text-red-600 hover:text-red-700 dark:text-red-400 flex items-center gap-1">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                        </svg>
                        Hapus Logo
                    </x-confirm-button>
                    @endif

                    <div wire:loading wire:target="logoFile" class="text-xs text-blue-500 flex items-center gap-1">
                        <svg class="animate-spin w-3 h-3" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
                        </svg>
                        Mengunggah...
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Tombol Simpan --}}
    <div class="flex items-center justify-end gap-3 pb-4">
        <button wire:click="simpan" wire:loading.attr="disabled" wire:target="simpan"
            class="btn-primary px-8">
            <span wire:loading.remove wire:target="simpan">Simpan Perubahan</span>
            <span wire:loading wire:target="simpan" class="flex items-center gap-2">
                <svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
                </svg>
                Menyimpan...
            </span>
        </button>
    </div>


</div>
