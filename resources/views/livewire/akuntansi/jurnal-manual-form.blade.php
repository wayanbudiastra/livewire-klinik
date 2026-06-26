<div class="card max-w-2xl">
    <div class="card-header">
        <h3 class="text-sm font-semibold dark:text-white">Input Jurnal Manual</h3>
        <p class="text-xs text-gray-400">Biaya operasional non-sistem (listrik, sewa, gaji non-dokter, dll) atau mutasi modal pemilik</p>
    </div>
    <div class="card-body space-y-4">

        <div class="grid grid-cols-2 gap-4">
            <div class="form-group">
                <label class="form-label">Tanggal <span class="text-red-500">*</span></label>
                <input type="date" wire:model="tanggal" max="{{ now()->format('Y-m-d') }}"
                    class="form-input dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200" />
                @error('tanggal') <p class="form-error">{{ $message }}</p> @enderror
            </div>
            <div class="form-group">
                <label class="form-label">Kategori</label>
                <select wire:model.live="kategori" class="form-input dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200">
                    <option value="">— Pilih (opsional) —</option>
                    @foreach($this->daftarKategori as $key => $label)
                    <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
                <p class="text-xs text-gray-400 mt-1">Memilih kategori otomatis menyarankan akun, tetap bisa diubah manual.</p>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div class="form-group">
                <label class="form-label">Akun Debit <span class="text-red-500">*</span></label>
                <select wire:model="kodeAkunDebit" class="form-input dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200">
                    <option value="">— Pilih Akun —</option>
                    @foreach($this->daftarAkun as $akun)
                    <option value="{{ $akun->kode }}">{{ $akun->kode }} — {{ $akun->nama }}</option>
                    @endforeach
                </select>
                @error('kodeAkunDebit') <p class="form-error">{{ $message }}</p> @enderror
            </div>
            <div class="form-group">
                <label class="form-label">Akun Kredit <span class="text-red-500">*</span></label>
                <select wire:model="kodeAkunKredit" class="form-input dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200">
                    <option value="">— Pilih Akun —</option>
                    @foreach($this->daftarAkun as $akun)
                    <option value="{{ $akun->kode }}">{{ $akun->kode }} — {{ $akun->nama }}</option>
                    @endforeach
                </select>
                @error('kodeAkunKredit') <p class="form-error">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="form-group">
            <label class="form-label">Nominal (Rp) <span class="text-red-500">*</span></label>
            <input type="number" wire:model="nominal" min="0.01" step="100"
                class="form-input dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200" />
            @error('nominal') <p class="form-error">{{ $message }}</p> @enderror
        </div>

        <div class="form-group">
            <label class="form-label">Keterangan <span class="text-red-500">*</span></label>
            <input type="text" wire:model="keterangan" placeholder="Contoh: Bayar listrik PLN bulan Juni 2026"
                class="form-input dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200" />
            @error('keterangan') <p class="form-error">{{ $message }}</p> @enderror
        </div>

        <div class="form-group">
            <label class="form-label">Dokumen Pendukung (opsional)</label>
            <input type="file" wire:model="dokumenPendukung" accept=".pdf,.jpg,.jpeg,.png"
                class="form-input dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200" />
            <p class="text-xs text-gray-400 mt-1">PDF/JPG/PNG, maks 5MB — bukti transfer, invoice, atau kuitansi.</p>
            @error('dokumenPendukung') <p class="form-error">{{ $message }}</p> @enderror
            <div wire:loading wire:target="dokumenPendukung" class="text-xs text-gray-400 mt-1">Mengunggah...</div>
        </div>

        <div class="flex justify-end gap-3 pt-2">
            <a href="{{ route('akuntansi.jurnal-manual') }}" class="btn-secondary">Batal</a>
            <button type="button" wire:click="simpan" wire:loading.attr="disabled" class="btn-primary">
                <span wire:loading.remove wire:target="simpan">Simpan</span>
                <span wire:loading wire:target="simpan">Menyimpan...</span>
            </button>
        </div>
    </div>
</div>
