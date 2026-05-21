<div class="card">
    <div class="card-header">
        <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-100">Mapping Poliklinik</h3>
    </div>
    <div class="card-body space-y-4">

        {{-- Tambah Poli --}}
        @can('masterdata.edit')
        <div class="flex gap-2">
            <select wire:model="addPoliId"
                    class="form-select flex-1 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200">
                <option value="0">— Pilih Poli untuk ditambahkan —</option>
                @foreach ($this->availablePoli as $p)
                    <option value="{{ $p->id }}">{{ $p->nama }} ({{ $p->kode }})</option>
                @endforeach
            </select>
            <button wire:click="addMapping" class="btn-primary" wire:loading.attr="disabled">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Tambah
            </button>
        </div>
        @endcan

        {{-- Daftar Mapping --}}
        @if ($this->mappedPoli->isEmpty())
        <div class="empty-state py-8">
            <p class="empty-state-text">Belum ada poli yang dimapping</p>
        </div>
        @else
        <div class="table-wrapper">
            <table class="table">
                <thead>
                    <tr>
                        <th>Poliklinik</th>
                        <th>Kode</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($this->mappedPoli as $dp)
                    <tr wire:key="dp-{{ $dp->id }}">
                        <td class="font-medium text-gray-900 dark:text-gray-100">{{ $dp->poli->nama }}</td>
                        <td class="font-mono text-xs text-gray-500">{{ $dp->poli->kode }}</td>
                        <td>
                            @if ($dp->is_aktif)
                                <span class="badge-success">Aktif</span>
                            @else
                                <span class="badge-gray">Nonaktif</span>
                            @endif
                        </td>
                        <td>
                            @can('masterdata.edit')
                            @if ($dp->is_aktif)
                            <button wire:click="removeMapping({{ $dp->poli_id }})"
                                    wire:confirm="Nonaktifkan mapping Poli {{ $dp->poli->nama }}?"
                                    class="btn-danger btn-sm">Hapus</button>
                            @endif
                            @endcan
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </div>
</div>
