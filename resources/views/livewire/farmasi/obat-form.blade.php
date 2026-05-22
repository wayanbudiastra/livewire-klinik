<div>
    <span
        x-on:open-obat-create.window="$wire.openCreate()"
        x-on:open-obat-edit.window="$wire.openEdit($event.detail.id)"
    ></span>

    @if ($showModal)
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/50" wire:click="$set('showModal', false)"></div>
        <div class="relative z-10 w-full max-w-3xl rounded-2xl bg-white shadow-2xl
                    dark:bg-gray-800 dark:border dark:border-gray-700 animate-fade-in
                    max-h-[90vh] overflow-y-auto">

            <div class="modal-header sticky top-0 bg-white dark:bg-gray-800 z-10">
                <h3 class="modal-title dark:text-white">
                    {{ $isEdit ? 'Edit Obat/Alkes' : 'Tambah Obat/Alkes Baru' }}
                </h3>
                <button wire:click="$set('showModal', false)" class="text-gray-400 hover:text-gray-600">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <div class="modal-body">
                <form wire:submit="save" class="space-y-4">

                    {{-- Baris 1: Jenis & Paten --}}
                    <div class="flex gap-3 items-center">
                        <div class="flex gap-2 flex-1">
                            @foreach (['obat' => 'Obat', 'alkes' => 'Alkes'] as $val => $lbl)
                            <button type="button" wire:click="$set('jenis_barang', '{{ $val }}')"
                                    @class([
                                        'flex-1 py-2 rounded-lg border text-sm font-medium transition-colors',
                                        'border-[#0a3d62] bg-[#0a3d62] text-white' => $jenis_barang === $val,
                                        'border-gray-200 text-gray-600 dark:border-gray-600 dark:text-gray-400' => $jenis_barang !== $val,
                                    ])>{{ $lbl }}</button>
                            @endforeach
                        </div>
                        <label class="flex items-center gap-2 cursor-pointer text-sm text-gray-600 dark:text-gray-400">
                            <input type="checkbox" wire:model="is_paten" class="form-checkbox"/>
                            Obat Paten
                        </label>
                        <div class="flex items-center gap-3">
                            <button type="button" wire:click="$set('is_active', !{{ $is_active ? 'true' : 'false' }})"
                                    @class([
                                        'relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none',
                                        'bg-primary-600' => $is_active,
                                        'bg-gray-300 dark:bg-gray-600' => !$is_active,
                                    ])>
                                <span @class([
                                    'inline-block h-4 w-4 rounded-full bg-white shadow transition-transform',
                                    'translate-x-6' => $is_active,
                                    'translate-x-1' => !$is_active,
                                ])></span>
                            </button>
                            <span class="text-sm text-gray-600 dark:text-gray-400">{{ $is_active ? 'Aktif' : 'Non-Aktif' }}</span>
                        </div>
                    </div>

                    {{-- Baris 2: Kode & Barcode --}}
                    <div class="grid grid-cols-2 gap-4">
                        <div class="form-group">
                            <label class="form-label dark:text-gray-300">Kode <span class="text-red-500">*</span></label>
                            <input wire:model="kode" type="text" placeholder="OBT001"
                                   class="form-input uppercase dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200"/>
                            @error('kode') <p class="form-error">{{ $message }}</p> @enderror
                        </div>
                        <div class="form-group">
                            <label class="form-label dark:text-gray-300">Barcode</label>
                            <input wire:model="barcode" type="text" placeholder="Kode scan fisik (opsional)"
                                   class="form-input dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200"/>
                        </div>
                    </div>

                    {{-- Nama & Generik --}}
                    <div class="grid grid-cols-2 gap-4">
                        <div class="form-group">
                            <label class="form-label dark:text-gray-300">Nama <span class="text-red-500">*</span></label>
                            <input wire:model="nama" type="text" placeholder="Nama lengkap obat/alkes"
                                   class="form-input dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200"/>
                            @error('nama') <p class="form-error">{{ $message }}</p> @enderror
                        </div>
                        <div class="form-group">
                            <label class="form-label dark:text-gray-300">Nama Generik</label>
                            <input wire:model="generik" type="text" placeholder="Nama generik (opsional)"
                                   class="form-input dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200"/>
                        </div>
                    </div>

                    {{-- Satuan --}}
                    <div class="grid grid-cols-4 gap-3">
                        <div class="form-group">
                            <label class="form-label dark:text-gray-300">Satuan Dasar <span class="text-red-500">*</span></label>
                            <input wire:model="satuan" type="text" placeholder="Tablet, Botol..."
                                   class="form-input dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200"/>
                            @error('satuan') <p class="form-error">{{ $message }}</p> @enderror
                        </div>
                        <div class="form-group">
                            <label class="form-label dark:text-gray-300">Satuan Besar</label>
                            <select wire:model="satuan_besar_id"
                                    class="form-select dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200">
                                <option value="">— Pilih —</option>
                                @foreach ($this->satuanList as $s)
                                    <option value="{{ $s->id }}">{{ $s->nama }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label dark:text-gray-300">Satuan Kecil</label>
                            <select wire:model="satuan_kecil_id"
                                    class="form-select dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200">
                                <option value="">— Pilih —</option>
                                @foreach ($this->satuanList as $s)
                                    <option value="{{ $s->id }}">{{ $s->nama }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label dark:text-gray-300">Konversi</label>
                            <input wire:model="konversi" type="number" min="1"
                                   class="form-input dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200"/>
                            <p class="form-hint">Satuan besar = N satuan kecil</p>
                        </div>
                    </div>

                    {{-- Harga & Stok --}}
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                        <div class="form-group">
                            <label class="form-label dark:text-gray-300">Stok Awal</label>
                            <input wire:model="stok" type="number" min="0"
                                   class="form-input dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200"/>
                        </div>
                        <div class="form-group">
                            <label class="form-label dark:text-gray-300">Harga Umum (Rp) <span class="text-red-500">*</span></label>
                            <input wire:model="harga" type="number" min="0" step="100"
                                   class="form-input dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200"/>
                            @error('harga') <p class="form-error">{{ $message }}</p> @enderror
                        </div>
                        <div class="form-group">
                            <label class="form-label dark:text-gray-300">Harga Beli (Rp)</label>
                            <input wire:model="harga_beli" type="number" min="0" step="100"
                                   class="form-input dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200"/>
                        </div>
                        <div class="form-group">
                            <label class="form-label dark:text-gray-300">Harga BPJS (Rp)</label>
                            <input wire:model="harga_bpjs" type="number" min="0" step="100"
                                   class="form-input dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200"/>
                        </div>
                    </div>

                    {{-- Kategori & Expired --}}
                    <div class="grid grid-cols-2 gap-4">
                        <div class="form-group">
                            <label class="form-label dark:text-gray-300">Kategori</label>
                            <input wire:model="kategori" type="text" placeholder="Analgesik, Antibiotik..."
                                   class="form-input dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200"/>
                        </div>
                        <div class="form-group">
                            <label class="form-label dark:text-gray-300">Tanggal Expired</label>
                            <input wire:model="expired_date" type="date"
                                   class="form-input dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200"/>
                        </div>
                    </div>

                    <div class="modal-footer -mx-5 -mb-5 mt-2">
                        <button type="button" wire:click="$set('showModal', false)" class="btn-secondary">Batal</button>
                        <button type="submit" class="btn-primary" wire:loading.attr="disabled">
                            <span wire:loading.remove wire:target="save">{{ $isEdit ? 'Simpan' : 'Tambah' }}</span>
                            <span wire:loading wire:target="save" class="flex items-center gap-2">
                                <div class="spinner h-4 w-4 border-white border-t-transparent"></div> Menyimpan...
                            </span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @endif
</div>
