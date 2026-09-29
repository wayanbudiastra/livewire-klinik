<div class="card">
    <div class="card-header">
        <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-100">Sharing Fee Perawat</h3>
        <p class="text-xs text-gray-400">Persentase global (sama utk semua perawat), dihitung dari tindakan yang perawat kerjakan sendiri sbg pelaksana</p>
    </div>
    <div class="card-body">
        <form wire:submit="save" class="space-y-4">
            <div class="rounded-lg border border-gray-200 dark:border-gray-600 p-4 space-y-2 max-w-xs">
                <div class="flex items-center justify-between">
                    <p class="text-sm font-medium text-gray-800 dark:text-gray-200">Tindakan Medis</p>
                    <div class="flex items-center gap-2">
                        <input wire:model.live="fee_tindakan" type="number"
                               min="0" max="100" step="0.5"
                               class="w-20 text-right form-input py-1 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200"/>
                        <span class="text-sm font-medium text-gray-500">%</span>
                    </div>
                </div>
                <div class="h-2 rounded-full bg-gray-100 dark:bg-gray-700 overflow-hidden">
                    <div class="h-full rounded-full bg-teal-500 transition-all duration-300"
                         style="width: {{ min((float) $fee_tindakan, 100) }}%"></div>
                </div>
                @error('fee_tindakan') <p class="form-error">{{ $message }}</p> @enderror
            </div>

            @can('masterdata.edit')
            <div class="flex justify-end pt-2">
                <button type="submit" class="btn-primary" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="save">Simpan</span>
                    <span wire:loading wire:target="save" class="flex items-center gap-2">
                        <div class="spinner h-4 w-4 border-white border-t-transparent"></div> Menyimpan...
                    </span>
                </button>
            </div>
            @endcan
        </form>
    </div>
</div>
