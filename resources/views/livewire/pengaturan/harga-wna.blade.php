<div class="max-w-3xl space-y-6">

    {{-- Setting Markup --}}
    <div class="card">
        <div class="card-header">
            <h3 class="text-sm font-semibold dark:text-white">Markup Harga WNA</h3>
            <p class="text-xs text-gray-400 mt-0.5">
                Persentase kenaikan default dari harga umum. Dipakai sebagai nilai awal
                saat admin menambah/edit tindakan, lab, radiologi, atau obat — masih bisa
                diedit manual per item sebelum disimpan.
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
            <h3 class="text-sm font-semibold dark:text-white">Terapkan ke Semua Item</h3>
            <p class="text-xs text-gray-400 mt-0.5">
                Isi otomatis harga WNA untuk item yang <strong>belum pernah diisi</strong> memakai
                markup di atas. Item yang sudah diisi manual (sengaja beda dari markup default)
                tidak akan ditimpa.
            </p>
        </div>
        <div class="card-body space-y-4">

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                @foreach ([
                    'tindakan'  => 'Tindakan',
                    'penunjang' => 'Lab / Radiologi',
                    'obat'      => 'Obat / Alkes',
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
