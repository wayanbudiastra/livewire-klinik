<div class="space-y-4">

    {{-- Tombol Tambah --}}
    @can('masterdata.edit')
    <div class="flex justify-end">
        <button wire:click="openCreate()" class="btn-primary">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            Tambah Jadwal
        </button>
    </div>
    @endcan

    {{-- Form Tambah/Edit --}}
    @if ($showForm)
    <div class="card animate-fade-in">
        <div class="card-header">
            <h3 class="text-sm font-semibold dark:text-white">{{ $jadwalEditId ? 'Edit Jadwal' : 'Tambah Jadwal Baru' }}</h3>
            <button wire:click="$set('showForm', false)" class="text-gray-400 hover:text-gray-600">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
        <div class="card-body">
            <form wire:submit="save" class="grid grid-cols-2 sm:grid-cols-3 gap-4">

                <div class="form-group col-span-2 sm:col-span-1">
                    <label class="form-label dark:text-gray-300">Poli <span class="text-red-500">*</span></label>
                    <select wire:model="dokter_poli_id"
                            class="form-select dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200">
                        <option value="0">— Pilih Poli —</option>
                        @foreach ($this->dokterPoliList as $dp)
                            <option value="{{ $dp->id }}">[{{ $dp->poli->kode }}] {{ $dp->poli->nama }}</option>
                        @endforeach
                    </select>
                    @error('dokter_poli_id') <p class="form-error">{{ $message }}</p> @enderror
                </div>

                <div class="form-group">
                    <label class="form-label dark:text-gray-300">Hari <span class="text-red-500">*</span></label>
                    <select wire:model="hari"
                            class="form-select dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200">
                        <option value="">— Pilih Hari —</option>
                        @foreach ($this->hariOptions as $val => $label)
                            <option value="{{ $val }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('hari') <p class="form-error">{{ $message }}</p> @enderror
                </div>

                <div class="form-group">
                    <label class="form-label dark:text-gray-300">Kuota Pasien</label>
                    <input wire:model="kuota_pasien" type="number" min="1" max="200"
                           class="form-input dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200"/>
                </div>

                <div class="form-group">
                    <label class="form-label dark:text-gray-300">Jam Mulai <span class="text-red-500">*</span></label>
                    <input wire:model="jam_mulai" type="time"
                           class="form-input dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200"/>
                    @error('jam_mulai') <p class="form-error">{{ $message }}</p> @enderror
                </div>

                <div class="form-group">
                    <label class="form-label dark:text-gray-300">Jam Selesai <span class="text-red-500">*</span></label>
                    <input wire:model="jam_selesai" type="time"
                           class="form-input dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200"/>
                    @error('jam_selesai') <p class="form-error">{{ $message }}</p> @enderror
                </div>

                <div class="form-group">
                    <label class="form-label dark:text-gray-300">Keterangan</label>
                    <input wire:model="keterangan" type="text" placeholder="Khusus BPJS, dll."
                           class="form-input dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200"/>
                </div>

                <div class="col-span-2 sm:col-span-3 flex justify-end gap-2 pt-2">
                    <button type="button" wire:click="$set('showForm', false)" class="btn-secondary">Batal</button>
                    <button type="submit" class="btn-primary" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="save">{{ $jadwalEditId ? 'Update' : 'Tambah' }} Jadwal</span>
                        <span wire:loading wire:target="save" class="flex items-center gap-2">
                            <div class="spinner h-4 w-4 border-white border-t-transparent"></div> Menyimpan...
                        </span>
                    </button>
                </div>
            </form>
        </div>
    </div>
    @endif

    {{-- Jadwal per Poli --}}
    @forelse ($this->jadwalPerPoli as $dpId => $item)
    <div class="card">
        <div class="card-header">
            <div class="flex items-center gap-2">
                <span class="badge-primary">{{ $item['poli']->kode }}</span>
                <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-100">{{ $item['poli']->nama }}</h3>
            </div>
            @can('masterdata.edit')
            <button wire:click="openCreate({{ $dpId }})" class="btn-secondary btn-sm">+ Jadwal</button>
            @endcan
        </div>
        <div class="card-body p-0">
            @if ($item['jadwal']->isEmpty())
            <p class="text-center py-6 text-sm text-gray-400">Belum ada jadwal untuk poli ini</p>
            @else
            <table class="table">
                <thead>
                    <tr>
                        <th>Hari</th>
                        <th>Jam</th>
                        <th>Kuota</th>
                        <th>Keterangan</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($item['jadwal'] as $jadwal)
                    <tr wire:key="jadwal-{{ $jadwal->id }}">
                        <td class="font-medium capitalize text-gray-800 dark:text-gray-200">
                            {{ $jadwal->hari }}
                        </td>
                        <td class="font-mono text-sm text-gray-600 dark:text-gray-400">
                            {{ substr($jadwal->jam_mulai, 0, 5) }} – {{ substr($jadwal->jam_selesai, 0, 5) }}
                        </td>
                        <td class="text-sm text-gray-600 dark:text-gray-400">{{ $jadwal->kuota_pasien }} pasien</td>
                        <td class="text-sm text-gray-500">{{ $jadwal->keterangan ?? '-' }}</td>
                        <td>
                            @can('masterdata.edit')
                            <button wire:click="toggle({{ $jadwal->id }})"
                                    @class([
                                        'inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium transition-colors',
                                        'bg-emerald-100 text-emerald-700 hover:bg-emerald-200 dark:bg-emerald-900/40 dark:text-emerald-300' => $jadwal->is_aktif,
                                        'bg-red-100 text-red-700 hover:bg-red-200 dark:bg-red-900/40 dark:text-red-300' => !$jadwal->is_aktif,
                                    ])>
                                <span class="h-1.5 w-1.5 rounded-full {{ $jadwal->is_aktif ? 'bg-emerald-500' : 'bg-red-500' }}"></span>
                                {{ $jadwal->is_aktif ? 'Aktif' : 'Nonaktif' }}
                            </button>
                            @endcan
                        </td>
                        <td>
                            @can('masterdata.edit')
                            <div class="flex gap-1">
                                <button wire:click="openEdit({{ $jadwal->id }})" class="btn-info btn-sm">Edit</button>
                                <x-confirm-button
                                    action="delete({{ $jadwal->id }})"
                                    title="Hapus Jadwal?"
                                    text="Jadwal {{ ucfirst($jadwal->hari) }} {{ substr($jadwal->jam_mulai,0,5) }}–{{ substr($jadwal->jam_selesai,0,5) }} akan dihapus permanen."
                                    confirm="Ya, Hapus"
                                    type="danger"
                                    class="btn-danger btn-sm">
                                    Hapus
                                </x-confirm-button>
                            </div>
                            @endcan
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            @endif
        </div>
    </div>
    @empty
    <div class="card">
        <div class="card-body">
            <div class="empty-state">
                <p class="empty-state-text">Belum ada mapping poli. Tambahkan poli terlebih dahulu di tab "Mapping Poli".</p>
            </div>
        </div>
    </div>
    @endforelse
</div>
