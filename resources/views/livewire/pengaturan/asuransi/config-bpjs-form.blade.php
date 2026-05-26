<div class="max-w-2xl mx-auto">
    @if(session('success'))
    <div class="alert alert-success mb-4">{{ session('success') }}</div>
    @endif

    <div class="card">
        <div class="card-header">
            <h3 class="text-sm font-semibold text-gray-700">Konfigurasi BPJS Kesehatan</h3>
        </div>
        <div class="card-body space-y-5">

            {{-- Toggle Kerjasama --}}
            <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg border border-gray-200">
                <div>
                    <p class="font-medium text-gray-900">Kerjasama dengan BPJS</p>
                    <p class="text-xs text-gray-500 mt-0.5">Apakah klinik/RS bekerjasama dengan BPJS Kesehatan?</p>
                </div>
                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" wire:model.live="kerjasama" class="sr-only peer" />
                    <div class="w-11 h-6 bg-gray-300 rounded-full peer peer-checked:bg-primary-600
                                peer-checked:after:translate-x-full after:content-[''] after:absolute
                                after:top-0.5 after:left-0.5 after:bg-white after:rounded-full
                                after:h-5 after:w-5 after:transition-all"></div>
                </label>
            </div>

            {{-- Toggle Aktif --}}
            <div class="flex items-center justify-between p-4 rounded-lg border
                        {{ $kerjasama ? 'bg-emerald-50 border-emerald-200' : 'bg-gray-100 border-gray-200 opacity-60' }}">
                <div>
                    <p class="font-medium text-gray-900">Aktifkan BPJS sebagai Penjamin</p>
                    <p class="text-xs text-gray-500 mt-0.5">
                        @if($kerjasama)
                            BPJS akan muncul sebagai opsi penjamin saat registrasi kunjungan
                        @else
                            Aktifkan kerjasama terlebih dahulu
                        @endif
                    </p>
                </div>
                <label class="relative inline-flex items-center {{ $kerjasama ? 'cursor-pointer' : 'cursor-not-allowed' }}">
                    <input type="checkbox" wire:model="isActive" class="sr-only peer" @disabled(!$kerjasama) />
                    <div class="w-11 h-6 bg-gray-300 rounded-full peer peer-checked:bg-emerald-600
                                peer-checked:after:translate-x-full after:content-[''] after:absolute
                                after:top-0.5 after:left-0.5 after:bg-white after:rounded-full
                                after:h-5 after:w-5 after:transition-all"></div>
                </label>
            </div>

            {{-- Detail Faskes --}}
            @if($kerjasama)
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="form-group">
                    <label class="form-label">Kode Faskes</label>
                    <input type="text" wire:model="kodeFaskes" class="form-input" placeholder="Kode faskes BPJS" />
                    @error('kodeFaskes') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                <div class="form-group">
                    <label class="form-label">Nama Faskes</label>
                    <input type="text" wire:model="namaFaskes" class="form-input" />
                    @error('namaFaskes') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                <div class="form-group">
                    <label class="form-label">Tanggal Kerjasama</label>
                    <input type="date" wire:model="tanggalKerjasama" class="form-input" />
                    @error('tanggalKerjasama') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                <div class="form-group">
                    <label class="form-label">Berakhir</label>
                    <input type="date" wire:model="tanggalBerakhir" class="form-input" />
                    @error('tanggalBerakhir') <p class="form-error">{{ $message }}</p> @enderror
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Catatan</label>
                <textarea wire:model="catatan" class="form-input" rows="3"></textarea>
            </div>
            @endif

            <div class="flex justify-end">
                <button wire:click="simpan" wire:loading.attr="disabled" class="btn-primary">
                    <span wire:loading wire:target="simpan">Menyimpan...</span>
                    <span wire:loading.remove wire:target="simpan">Simpan Konfigurasi</span>
                </button>
            </div>
        </div>
    </div>
</div>
