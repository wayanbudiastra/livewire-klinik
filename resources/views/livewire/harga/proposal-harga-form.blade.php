<div class="space-y-5" x-data="{ cakupan: @entangle('cakupan') }">

    {{-- Header Proposal --}}
    <div class="card">
        <div class="card-header">
            <h3 class="text-sm font-semibold dark:text-white">Informasi Proposal</h3>
        </div>
        <div class="card-body grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="md:col-span-2 form-group">
                <label class="form-label dark:text-gray-300">Judul Proposal <span class="text-red-500">*</span></label>
                <input wire:model="judul" type="text" class="form-input dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200"
                       placeholder="Contoh: Kenaikan Harga Tahun 2027" />
                @error('judul') <p class="form-error">{{ $message }}</p> @enderror
            </div>

            <div class="form-group">
                <label class="form-label dark:text-gray-300">Tahun <span class="text-red-500">*</span></label>
                <input wire:model="tahun" type="number" class="form-input dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200"
                       min="2024" max="2099" />
                @error('tahun') <p class="form-error">{{ $message }}</p> @enderror
            </div>

            <div class="form-group">
                <label class="form-label dark:text-gray-300">Tanggal Efektif <span class="text-red-500">*</span></label>
                <input wire:model="tanggalEfektif" type="date" class="form-input dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200"
                       min="{{ now()->addDay()->format('Y-m-d') }}" />
                <p class="text-xs text-gray-400 mt-1">Harga baru hanya berlaku mulai tanggal ini.</p>
                @error('tanggalEfektif') <p class="form-error">{{ $message }}</p> @enderror
            </div>

            <div class="form-group">
                <label class="form-label dark:text-gray-300">Cakupan <span class="text-red-500">*</span></label>
                <select wire:model.live="cakupan" x-model="cakupan"
                        class="form-select dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200">
                    <option value="semua">Semua (Tindakan + Barang)</option>
                    <option value="tindakan">Tindakan / Jasa Pelayanan Saja</option>
                    <option value="barang">Barang (Obat / Alkes / BHP) Saja</option>
                </select>
                @error('cakupan') <p class="form-error">{{ $message }}</p> @enderror
            </div>

            <div class="form-group">
                <label class="form-label dark:text-gray-300">Catatan / Referensi SK</label>
                <input wire:model="catatan" type="text" class="form-input dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200"
                       placeholder="Opsional — alasan kenaikan, nomor SK, dsb" />
            </div>

            <div class="md:col-span-2">
                <label class="flex items-center gap-3 cursor-pointer">
                    <input wire:model="ikutBpjs" type="checkbox"
                           class="h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500" />
                    <span class="text-sm text-gray-700 dark:text-gray-300">
                        Ikutkan penyesuaian tarif BPJS
                        <span class="text-xs text-amber-500 ml-1">(default: tidak)</span>
                    </span>
                </label>
                <p class="text-xs text-gray-400 mt-1 ml-7">Aktifkan hanya jika klinik mengelola tarif BPJS sendiri (non-Permenkes).</p>
            </div>
        </div>
    </div>

    {{-- Konfigurasi Kenaikan Barang --}}
    <div class="card" x-show="cakupan === 'semua' || cakupan === 'barang'" x-transition>
        <div class="card-header">
            <h3 class="text-sm font-semibold dark:text-white">% Kenaikan Harga Barang</h3>
            <p class="text-xs text-gray-400 dark:text-gray-500">Berlaku untuk obat, alkes, dan bahan habis pakai. Dibulatkan ke Rp 100 terdekat.</p>
        </div>
        <div class="card-body">
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                @foreach(['obat' => 'Obat', 'alkes' => 'Alkes', 'bahan_habis_pakai' => 'Bahan Habis Pakai (BHP)'] as $jenis => $label)
                <div class="form-group">
                    <label class="form-label dark:text-gray-300">{{ $label }}</label>
                    <div class="relative">
                        <input wire:model="konfigBarang.{{ $jenis }}" type="number"
                               class="form-input pr-8 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200"
                               min="0" max="100" step="0.5" placeholder="0" />
                        <span class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm">%</span>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- Konfigurasi Kenaikan Tindakan --}}
    <div class="card" x-show="cakupan === 'semua' || cakupan === 'tindakan'" x-transition>
        <div class="card-header">
            <h3 class="text-sm font-semibold dark:text-white">% Kenaikan Tarif Tindakan / Jasa</h3>
            <p class="text-xs text-gray-400 dark:text-gray-500">Per kategori tindakan. Dibulatkan ke Rp 100 terdekat.</p>
        </div>
        <div class="card-body">
            @if(empty($konfigTindakan))
            <p class="text-sm text-gray-400">Tidak ada kategori tindakan aktif ditemukan.</p>
            @else
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                @foreach($konfigTindakan as $kategori => $persen)
                <div class="form-group">
                    <label class="form-label dark:text-gray-300">{{ $kategori ?: 'Tanpa Kategori' }}</label>
                    <div class="relative">
                        <input wire:model="konfigTindakan.{{ $kategori }}" type="number"
                               class="form-input pr-8 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200"
                               min="0" max="100" step="0.5" placeholder="0" />
                        <span class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm">%</span>
                    </div>
                </div>
                @endforeach
            </div>
            @endif
        </div>
    </div>

    {{-- Submit --}}
    <div class="flex justify-end gap-3">
        <a href="{{ route('harga.proposal.index') }}" class="btn-secondary">Batal</a>
        <x-confirm-button action="simpan"
            title="Buat Proposal Harga?"
            text="Sistem akan mengkalkulasi harga baru untuk semua item aktif sesuai cakupan dan persentase yang diinput."
            icon="info" type="primary" confirm="Ya, Buat Proposal"
            wire:loading.attr="disabled" class="btn-primary">
            <span wire:loading.remove wire:target="simpan">Buat Proposal & Generate Item</span>
            <span wire:loading wire:target="simpan">Memproses...</span>
        </x-confirm-button>
    </div>
</div>
