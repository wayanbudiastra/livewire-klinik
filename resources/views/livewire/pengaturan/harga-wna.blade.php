<div class="max-w-3xl space-y-6">

    {{-- Markup Otomatis: Obat & BHP + Lab (berbasis harga modal) --}}
    <div class="card">
        <div class="card-header">
            <h3 class="text-sm font-semibold dark:text-white">Markup Otomatis — Obat, BHP & Lab</h3>
            <p class="text-xs text-gray-400 mt-0.5">
                Harga jual (KTP) & WNA dihitung <strong>otomatis</strong> dari harga modal setiap kali
                modal berubah (mis. pembelian baru mengubah HPR, atau harga modal Lab diisi/diubah).
                Angka di sini masih bisa diedit manual per item sesudahnya di form masing-masing.
            </p>
        </div>
        <div class="card-body space-y-5">
            <form wire:submit="simpanMarkupOtomatis" class="space-y-4">
                <div class="rounded-lg border border-gray-200 dark:border-gray-600 p-3 space-y-3">
                    <p class="text-sm font-semibold text-gray-700 dark:text-gray-200">Obat & BHP</p>
                    <div class="grid grid-cols-2 gap-3">
                        <div class="form-group">
                            <label class="form-label dark:text-gray-300">Multiplier KTP (x modal)</label>
                            <input wire:model="markupObatBhpKtp" type="number" min="1" step="0.1"
                                   class="form-input dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200"/>
                            @error('markupObatBhpKtp') <p class="form-error">{{ $message }}</p> @enderror
                        </div>
                        <div class="form-group">
                            <label class="form-label dark:text-gray-300">Multiplier WNA (x modal)</label>
                            <input wire:model="markupObatBhpWna" type="number" min="1" step="0.1"
                                   class="form-input dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200"/>
                            @error('markupObatBhpWna') <p class="form-error">{{ $message }}</p> @enderror
                        </div>
                    </div>
                    <p class="text-xs text-gray-400">
                        {{ $this->ringkasanOtomatis['obat_bhp']['punya_modal'] }} / {{ $this->ringkasanOtomatis['obat_bhp']['total'] }}
                        item Obat & BHP sudah punya harga modal (HPR).
                    </p>
                </div>

                <div class="rounded-lg border border-gray-200 dark:border-gray-600 p-3 space-y-3">
                    <p class="text-sm font-semibold text-gray-700 dark:text-gray-200">Laboratorium</p>
                    <div class="grid grid-cols-2 gap-3">
                        <div class="form-group">
                            <label class="form-label dark:text-gray-300">Multiplier KTP (x modal)</label>
                            <input wire:model="markupLabKtp" type="number" min="1" step="0.1"
                                   class="form-input dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200"/>
                            @error('markupLabKtp') <p class="form-error">{{ $message }}</p> @enderror
                        </div>
                        <div class="form-group">
                            <label class="form-label dark:text-gray-300">Multiplier WNA (x modal)</label>
                            <input wire:model="markupLabWna" type="number" min="1" step="0.1"
                                   class="form-input dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200"/>
                            @error('markupLabWna') <p class="form-error">{{ $message }}</p> @enderror
                        </div>
                    </div>
                    <p class="text-xs text-gray-400">
                        {{ $this->ringkasanOtomatis['lab']['punya_modal'] }} / {{ $this->ringkasanOtomatis['lab']['total'] }}
                        item Lab sudah punya harga modal. Radiologi tidak ikut sistem ini (tetap manual).
                    </p>
                </div>

                <button type="submit" class="btn-primary" wire:loading.attr="disabled" wire:target="simpanMarkupOtomatis">
                    <span wire:loading.remove wire:target="simpanMarkupOtomatis">Simpan Multiplier</span>
                    <span wire:loading wire:target="simpanMarkupOtomatis">...</span>
                </button>
            </form>

            <div class="border-t border-gray-100 dark:border-gray-700 pt-4">
                <p class="text-xs text-gray-400 mb-2">
                    Hitung ulang harga jual & WNA <strong>semua</strong> item Obat/BHP (yang sudah punya HPR)
                    dan Lab (yang sudah punya harga modal) memakai multiplier di atas — MENIMPA harga yang
                    sudah ada, bukan cuma yang kosong.
                </p>
                <button type="button" wire:click="hitungUlangSemua"
                        wire:confirm="Hitung ulang harga jual & WNA SEMUA item Obat/BHP dan Lab yang sudah punya harga modal, memakai multiplier saat ini? Ini akan MENIMPA harga yang sudah ada."
                        class="btn-secondary" wire:loading.attr="disabled" wire:target="hitungUlangSemua">
                    <span wire:loading.remove wire:target="hitungUlangSemua">🔄 Hitung Ulang Semua Data</span>
                    <span wire:loading wire:target="hitungUlangSemua">Memproses...</span>
                </button>
            </div>
        </div>
    </div>

    {{-- Setting Markup Manual (Tindakan, Radiologi, Alkes, lainnya) --}}
    <div class="card">
        <div class="card-header">
            <h3 class="text-sm font-semibold dark:text-white">Markup Harga WNA — Tindakan, Radiologi & Alkes</h3>
            <p class="text-xs text-gray-400 mt-0.5">
                Persentase kenaikan default dari harga umum. Dipakai sebagai nilai awal (generator manual,
                lewat tombol "Generate" di form) saat admin menambah/edit tindakan, radiologi, atau alkes —
                masih bisa diedit manual per item sebelum disimpan. Obat, BHP & Lab TIDAK ikut di sini
                (lihat "Markup Otomatis" di atas).
            </p>
        </div>
        <div class="card-body">
            <form wire:submit="simpanMarkup" class="flex items-end gap-3">
                <div class="form-group flex-1 max-w-xs">
                    <label class="form-label dark:text-gray-300">Markup (%)</label>
                    <input wire:model="markupPersen" type="number" min="0" max="1000" step="0.5"
                           class="form-input dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200"/>
                    @error('markupPersen') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                <button type="submit" class="btn-primary" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="simpanMarkup">Simpan</span>
                    <span wire:loading wire:target="simpanMarkup">...</span>
                </button>
            </form>
        </div>
    </div>

    {{-- Bulk Apply --}}
    <div class="card">
        <div class="card-header">
            <h3 class="text-sm font-semibold dark:text-white">Terapkan ke Semua Item (Manual)</h3>
            <p class="text-xs text-gray-400 mt-0.5">
                Isi otomatis harga WNA untuk item yang <strong>belum pernah diisi</strong> memakai
                markup di atas. Item yang sudah diisi manual (sengaja beda dari markup default)
                tidak akan ditimpa.
            </p>
        </div>
        <div class="card-body space-y-4">

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                @foreach ([
                    'tindakan'    => 'Tindakan',
                    'radiologi'   => 'Radiologi',
                    'obat_manual' => 'Alkes / Lainnya',
                ] as $key => $label)
                @php $r = $this->ringkasan[$key]; @endphp
                <div class="rounded-lg border border-gray-200 dark:border-gray-600 p-3">
                    <p class="text-xs text-gray-400 mb-1">{{ $label }}</p>
                    <p class="text-lg font-bold text-gray-800 dark:text-gray-100">
                        {{ $r['terisi'] }} <span class="text-sm font-normal text-gray-400">/ {{ $r['total'] }} terisi</span>
                    </p>
                </div>
                @endforeach
            </div>

            <button type="button" wire:click="terapkanKeSemua"
                    wire:confirm="Isi otomatis harga WNA untuk semua item yang masih kosong memakai markup {{ $markupPersen }}%? Item yang sudah pernah diisi manual tidak akan diubah."
                    class="btn-primary" wire:loading.attr="disabled" wire:target="terapkanKeSemua">
                <span wire:loading.remove wire:target="terapkanKeSemua">⚡ Terapkan ke Item yang Belum Terisi</span>
                <span wire:loading wire:target="terapkanKeSemua">Memproses...</span>
            </button>
        </div>
    </div>

</div>
