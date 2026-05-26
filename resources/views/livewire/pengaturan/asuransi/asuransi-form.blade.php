<div>
    <div class="page-header">
        <div>
            <h1 class="page-title">{{ $isEdit ? 'Edit Asuransi' : 'Tambah Asuransi' }}</h1>
            <p class="page-subtitle">{{ $isEdit ? 'Perbarui data penjamin' : 'Daftarkan asuransi baru' }}</p>
        </div>
        <a href="{{ route('pengaturan.asuransi.index') }}" class="btn-secondary">Kembali</a>
    </div>

    <div class="space-y-6">
        {{-- Info Dasar --}}
        <div class="card">
            <div class="card-header">
                <h3 class="text-sm font-semibold text-gray-700">Informasi Dasar</h3>
            </div>
            <div class="card-body grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="form-group">
                    <label class="form-label">Kode <span class="text-red-500">*</span></label>
                    <input type="text" wire:model="kode" class="form-input" @if($isEdit) readonly @endif />
                    @error('kode') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                <div class="form-group">
                    <label class="form-label">Nama Asuransi <span class="text-red-500">*</span></label>
                    <input type="text" wire:model="nama" class="form-input" placeholder="Prudential, AXA, BPJS, dll." />
                    @error('nama') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                <div class="form-group">
                    <label class="form-label">Tipe</label>
                    <select wire:model="tipe" class="form-input">
                        <option value="swasta">Swasta</option>
                        <option value="bpjs">BPJS</option>
                        <option value="pemerintah">Pemerintah</option>
                        <option value="corporate">Corporate</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">TOP (Term of Payment)</label>
                    <div class="relative">
                        <input type="number" wire:model="termPembayaranHari" class="form-input pr-12"
                            min="0" max="365" placeholder="30" />
                        <span class="absolute inset-y-0 right-3 flex items-center text-gray-400 text-sm">hari</span>
                    </div>
                    @error('termPembayaranHari') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                <div class="form-group">
                    <label class="form-label">Periode Mulai</label>
                    <input type="date" wire:model="periodeMulai" class="form-input" />
                </div>
                <div class="form-group">
                    <label class="form-label">Periode Berakhir</label>
                    <input type="date" wire:model="periodeBerakhir" class="form-input" />
                    @error('periodeBerakhir') <p class="form-error">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        {{-- Cover per Kategori --}}
        <div class="card">
            <div class="card-header">
                <h3 class="text-sm font-semibold text-gray-700">Cover / Diskon per Kategori (%)</h3>
                <p class="text-xs text-gray-400 mt-0.5">0 = tidak di-cover, 100 = di-cover penuh</p>
            </div>
            <div class="card-body grid grid-cols-1 md:grid-cols-2 gap-4">
                @foreach([
                    'coverProsedur'     => 'Prosedur (Tindakan Medis)',
                    'coverLaboratorium' => 'Laboratorium',
                    'coverRadiologi'    => 'Radiologi',
                    'coverPeralatan'    => 'Peralatan (Obat / Alkes)',
                ] as $field => $label)
                <div class="form-group">
                    <label class="form-label">{{ $label }}</label>
                    <div class="relative">
                        <input type="number" wire:model="{{ $field }}"
                            class="form-input pr-8" min="0" max="100" step="0.01" placeholder="0" />
                        <span class="absolute inset-y-0 right-3 flex items-center text-gray-400 text-sm">%</span>
                    </div>
                    @error($field) <p class="form-error">{{ $message }}</p> @enderror
                </div>
                @endforeach
            </div>
        </div>

        {{-- Plafon --}}
        <div class="card">
            <div class="card-header">
                <h3 class="text-sm font-semibold text-gray-700">Plafon (Opsional)</h3>
            </div>
            <div class="card-body grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="form-group">
                    <label class="form-label">Plafon per Kunjungan</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-3 flex items-center text-gray-400 text-sm">Rp</span>
                        <input type="number" wire:model="plafonPerKunjungan" class="form-input pl-9" min="0" placeholder="Kosong = tanpa plafon" />
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Plafon per Tahun</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-3 flex items-center text-gray-400 text-sm">Rp</span>
                        <input type="number" wire:model="plafonPerTahun" class="form-input pl-9" min="0" placeholder="Kosong = tanpa plafon" />
                    </div>
                </div>
            </div>
        </div>

        {{-- Kontak --}}
        <div class="card">
            <div class="card-header">
                <h3 class="text-sm font-semibold text-gray-700">Kontak Penagihan</h3>
            </div>
            <div class="card-body grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="form-group">
                    <label class="form-label">PIC</label>
                    <input type="text" wire:model="pic" class="form-input" placeholder="Nama penanggung jawab" />
                </div>
                <div class="form-group">
                    <label class="form-label">Telepon</label>
                    <input type="text" wire:model="telepon" class="form-input" />
                </div>
                <div class="form-group">
                    <label class="form-label">Email</label>
                    <input type="email" wire:model="email" class="form-input" />
                    @error('email') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                <div class="form-group md:col-span-2">
                    <label class="form-label">Alamat</label>
                    <textarea wire:model="alamat" class="form-input" rows="2"></textarea>
                </div>
            </div>
        </div>

        <div class="flex justify-end gap-3">
            <a href="{{ route('pengaturan.asuransi.index') }}" class="btn-secondary">Batal</a>
            <button wire:click="simpan" wire:loading.attr="disabled" class="btn-primary">
                <span wire:loading wire:target="simpan">Menyimpan...</span>
                <span wire:loading.remove wire:target="simpan">{{ $isEdit ? 'Perbarui' : 'Simpan' }}</span>
            </button>
        </div>
    </div>
</div>
