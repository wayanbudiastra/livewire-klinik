<div>
    <span x-on:open-barang-create.window="$wire.openCreate()" x-on:open-barang-edit.window="$wire.openEdit($event.detail.id)"></span>

    @if ($showModal)
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/50" wire:click="$set('showModal', false)"></div>
        <div class="relative z-10 w-full max-w-3xl rounded-2xl bg-white shadow-2xl dark:bg-gray-800 dark:border dark:border-gray-700 animate-fade-in max-h-[90vh] overflow-y-auto">
            <div class="modal-header sticky top-0 bg-white dark:bg-gray-800 z-10">
                <h3 class="modal-title dark:text-white">{{ $isEdit ? 'Edit Barang' : 'Tambah Barang Baru' }}</h3>
                <button wire:click="$set('showModal', false)" class="text-gray-400 hover:text-gray-600">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <div class="modal-body">
                <form wire:submit="save" class="space-y-4">
                    <div class="grid grid-cols-3 gap-3">
                        <div class="form-group">
                            <label class="form-label dark:text-gray-300">Kode <span class="text-red-500">*</span></label>
                            <input wire:model="kode" type="text" class="form-input uppercase dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200"/>
                            @error('kode') <p class="form-error">{{ $message }}</p> @enderror
                        </div>
                        <div class="form-group">
                            <label class="form-label dark:text-gray-300">Jenis <span class="text-red-500">*</span></label>
                            <select wire:model="jenis" class="form-select dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200">
                                <option value="obat">Obat</option>
                                <option value="alkes">Alkes</option>
                                <option value="bahan_habis_pakai">Bahan Habis Pakai</option>
                                <option value="lainnya">Lainnya</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label dark:text-gray-300">Kategori</label>
                            <input wire:model="kategori" type="text" placeholder="Antibiotik, dll." class="form-input dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200"/>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div class="form-group">
                            <label class="form-label dark:text-gray-300">Nama Barang <span class="text-red-500">*</span></label>
                            <input wire:model="nama" type="text" class="form-input dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200"/>
                            @error('nama') <p class="form-error">{{ $message }}</p> @enderror
                        </div>
                        <div class="form-group">
                            <label class="form-label dark:text-gray-300">Nama Generik</label>
                            <input wire:model="nama_generik" type="text" class="form-input dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200"/>
                        </div>
                    </div>
                    <div class="grid grid-cols-4 gap-3">
                        <div class="form-group">
                            <label class="form-label dark:text-gray-300">Satuan <span class="text-red-500">*</span></label>
                            <input wire:model="satuan" type="text" placeholder="Tablet, Pcs..." class="form-input dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200"/>
                            @error('satuan') <p class="form-error">{{ $message }}</p> @enderror
                        </div>
                        <div class="form-group">
                            <label class="form-label dark:text-gray-300">Satuan Besar</label>
                            <input wire:model="satuan_besar" type="text" placeholder="Box, Karton..." class="form-input dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200"/>
                        </div>
                        <div class="form-group">
                            <label class="form-label dark:text-gray-300">Isi/Satuan Besar</label>
                            <input wire:model="isi_satuan_besar" type="number" min="1" class="form-input dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200"/>
                        </div>
                        <div class="form-group">
                            <label class="form-label dark:text-gray-300">Kemasan</label>
                            <input wire:model="kemasan" type="text" placeholder="Strip, Botol..." class="form-input dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200"/>
                        </div>
                    </div>
                    <div class="grid grid-cols-3 gap-3">
                        <div class="form-group">
                            <label class="form-label dark:text-gray-300">Stok Min <span class="text-red-500">*</span></label>
                            <input wire:model="stok_minimum" type="number" min="0" class="form-input dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200"/>
                        </div>
                        <div class="form-group">
                            <label class="form-label dark:text-gray-300">Stok Maks</label>
                            <input wire:model="stok_maksimum" type="number" min="0" class="form-input dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200"/>
                        </div>
                        <div class="form-group">
                            <label class="form-label dark:text-gray-300">HPR Awal (Rp)</label>
                            <input wire:model="harga_pokok" type="number" min="0" step="0.01" class="form-input dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200"/>
                        </div>
                    </div>
                    <div class="grid grid-cols-3 gap-3">
                        <div class="form-group">
                            <label class="form-label dark:text-gray-300">Harga Jual (Rp) <span class="text-red-500">*</span></label>
                            <input wire:model="harga_jual" type="number" min="0" step="0.01" class="form-input dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200"/>
                            @error('harga_jual') <p class="form-error">{{ $message }}</p> @enderror
                        </div>
                        <div class="form-group">
                            <label class="form-label dark:text-gray-300">Golongan</label>
                            <select wire:model="golongan" class="form-select dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200">
                                <option value="">— Pilih —</option>
                                @foreach (['bebas'=>'Bebas','bebas_terbatas'=>'Bebas Terbatas','keras'=>'Keras','narkotika'=>'Narkotika','psikotropika'=>'Psikotropika'] as $v => $l)
                                    <option value="{{ $v }}">{{ $l }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label dark:text-gray-300">Supplier Utama</label>
                            <select wire:model="supplier_utama_id" class="form-select dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200">
                                <option value="">— Pilih —</option>
                                @foreach ($this->supplierList as $sp)
                                    <option value="{{ $sp->id }}">{{ $sp->kode }} — {{ $sp->nama }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="flex items-center gap-6">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" wire:model="butuh_resep" class="form-checkbox"/>
                            <span class="text-sm text-gray-700 dark:text-gray-300">Butuh Resep Dokter</span>
                        </label>
                        <div class="flex items-center gap-3">
                            <button type="button" wire:click="$set('is_active', !{{ $is_active ? 'true' : 'false' }})"
                                    @class(['relative inline-flex h-6 w-11 items-center rounded-full transition-colors', 'bg-primary-600' => $is_active, 'bg-gray-300 dark:bg-gray-600' => !$is_active])>
                                <span @class(['inline-block h-4 w-4 rounded-full bg-white shadow transition-transform', 'translate-x-6' => $is_active, 'translate-x-1' => !$is_active])></span>
                            </button>
                            <span class="text-sm text-gray-700 dark:text-gray-300">{{ $is_active ? 'Aktif' : 'Non-Aktif' }}</span>
                        </div>
                    </div>
                    <div class="modal-footer -mx-5 -mb-5 mt-2">
                        <button type="button" wire:click="$set('showModal', false)" class="btn-secondary">Batal</button>
                        <button type="submit" class="btn-primary" wire:loading.attr="disabled">
                            <span wire:loading.remove wire:target="save">{{ $isEdit ? 'Simpan' : 'Tambah' }}</span>
                            <span wire:loading wire:target="save" class="flex items-center gap-2"><div class="spinner h-4 w-4 border-white border-t-transparent"></div> Menyimpan...</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @endif
</div>
