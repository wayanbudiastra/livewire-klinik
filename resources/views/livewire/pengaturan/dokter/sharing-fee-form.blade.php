<div class="card">
    <div class="card-header">
        <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-100">Sharing Fee</h3>
        <p class="text-xs text-gray-400">Persentase fee dari total tarif item per kategori</p>
    </div>
    <div class="card-body">
        <form wire:submit="save" class="space-y-4">

            @php
            $categories = [
                'tindakan'  => ['label' => 'Tindakan Medis',  'color' => 'blue',   'field' => 'fee_tindakan'],
                'lab'       => ['label' => 'Laboratorium',    'color' => 'emerald','field' => 'fee_lab'],
                'radiologi' => ['label' => 'Radiologi',       'color' => 'violet', 'field' => 'fee_radiologi'],
                'peralatan' => ['label' => 'Peralatan Medis', 'color' => 'amber',  'field' => 'fee_peralatan'],
            ];
            @endphp

            @foreach ($categories as $key => $cat)
            <div class="rounded-lg border border-gray-200 dark:border-gray-600 p-4 space-y-2">
                <div class="flex items-center justify-between">
                    <p class="text-sm font-medium text-gray-800 dark:text-gray-200">{{ $cat['label'] }}</p>
                    <div class="flex items-center gap-2">
                        <input wire:model.live="fee_{{ $key }}" type="number"
                               min="0" max="100" step="0.5"
                               class="w-20 text-right form-input py-1 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200"/>
                        <span class="text-sm font-medium text-gray-500">%</span>
                    </div>
                </div>
                {{-- Progress bar --}}
                @php $val = (float) $this->{'fee_'.$key}; @endphp
                <div class="h-2 rounded-full bg-gray-100 dark:bg-gray-700 overflow-hidden">
                    <div class="h-full rounded-full bg-{{ $cat['color'] }}-500 transition-all duration-300"
                         style="width: {{ min($val, 100) }}%"></div>
                </div>
                @error('fee_'.$key) <p class="form-error">{{ $message }}</p> @enderror
            </div>
            @endforeach

            @can('masterdata.edit')
            <div class="flex justify-end pt-2">
                <button type="submit" class="btn-primary" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="save">Simpan Sharing Fee</span>
                    <span wire:loading wire:target="save" class="flex items-center gap-2">
                        <div class="spinner h-4 w-4 border-white border-t-transparent"></div> Menyimpan...
                    </span>
                </button>
            </div>
            @endcan
        </form>
    </div>
</div>
