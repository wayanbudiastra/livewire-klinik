<div class="space-y-5">
    <div class="page-header">
        <div>
            <h1 class="page-title">Buat Stok Opname</h1>
            <p class="page-subtitle">Sistem akan men-snapshot stok semua barang aktif saat ini</p>
        </div>
        <a href="{{ route('inventory.opname.index') }}" class="btn-secondary">Kembali</a>
    </div>

    <div class="card">
        <div class="card-body space-y-4 max-w-lg">
            <div class="form-group">
                <label class="form-label">Tanggal Opname <span class="text-red-500">*</span></label>
                <input type="date" wire:model="tanggalOpname" class="form-input dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200" />
                @error('tanggalOpname') <p class="form-error">{{ $message }}</p> @enderror
            </div>

            <div class="form-group">
                <label class="form-label">Keterangan Periode</label>
                <input type="text" wire:model="keteranganPeriode" class="form-input dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200"
                    placeholder="cth: Opname Bulanan Mei 2026" />
            </div>

            <div class="form-group">
                <label class="form-label">Filter Jenis Barang</label>
                <select wire:model="filterJenis" class="form-input dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200">
                    <option value="">Semua Jenis (default)</option>
                    <option value="obat">Obat saja</option>
                    <option value="bahan_habis_pakai">Bahan Habis Pakai saja</option>
                    <option value="alat_kesehatan">Alat Kesehatan saja</option>
                </select>
                <p class="text-xs text-gray-400 mt-1">Biarkan kosong untuk opname seluruh barang aktif.</p>
            </div>

            <div class="form-group">
                <label class="form-label">Catatan</label>
                <textarea wire:model="catatan" rows="2" class="form-input dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200"
                    placeholder="Opsional"></textarea>
            </div>

            <div class="p-3 bg-amber-50 dark:bg-amber-900/20 rounded-lg text-sm text-amber-700 dark:text-amber-300">
                <p class="font-medium">⚠ Perhatian</p>
                <p class="mt-0.5">Stok sistem akan di-snapshot pada saat opname dibuat. Input stok fisik dilakukan setelah ini di halaman detail opname.</p>
            </div>

            <div class="flex justify-end gap-3 pt-2">
                <a href="{{ route('inventory.opname.index') }}" class="btn-secondary">Batal</a>
                <button type="button" wire:click="buat" wire:loading.attr="disabled" class="btn-primary">
                    <span wire:loading.remove wire:target="buat">Buat Opname</span>
                    <span wire:loading wire:target="buat">Membuat snapshot...</span>
                </button>
            </div>
        </div>
    </div>
</div>
