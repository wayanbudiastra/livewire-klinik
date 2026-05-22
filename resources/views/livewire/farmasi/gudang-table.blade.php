<div class="max-w-xl">

    @if ($showForm)
    <div class="card mb-4 animate-fade-in">
        <div class="card-header">
            <h3 class="text-sm font-semibold dark:text-white">{{ $editId ? 'Edit Lokasi Gudang' : 'Tambah Lokasi Gudang' }}</h3>
            <button wire:click="$set('showForm', false)" class="text-gray-400 hover:text-gray-600">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
        <div class="card-body space-y-3">
            <form wire:submit="save" class="space-y-3">
                <div class="grid grid-cols-2 gap-3">
                    <div class="form-group">
                        <label class="form-label dark:text-gray-300">Kode <span class="text-red-500">*</span></label>
                        <input wire:model="kode" type="text" placeholder="GD-UTAMA"
                               class="form-input uppercase dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200"/>
                        @error('kode') <p class="form-error">{{ $message }}</p> @enderror
                    </div>
                    <div class="form-group">
                        <label class="form-label dark:text-gray-300">Nama <span class="text-red-500">*</span></label>
                        <input wire:model="nama" type="text" placeholder="Gudang Utama"
                               class="form-input dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200"/>
                        @error('nama') <p class="form-error">{{ $message }}</p> @enderror
                    </div>
                </div>
                <div class="flex gap-2 justify-end">
                    <button type="button" wire:click="$set('showForm', false)" class="btn-secondary">Batal</button>
                    <button type="submit" class="btn-primary" wire:loading.attr="disabled">Simpan</button>
                </div>
            </form>
        </div>
    </div>
    @else
    <div class="mb-4">
        <button wire:click="openCreate" class="btn-primary">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            Tambah Lokasi Gudang
        </button>
    </div>
    @endif

    <div class="table-wrapper">
        <table class="table">
            <thead>
                <tr><th>Kode</th><th>Nama Lokasi</th><th>Status</th><th>Aksi</th></tr>
            </thead>
            <tbody>
                @forelse ($this->gudang as $g)
                <tr wire:key="gd-{{ $g->id }}">
                    <td class="font-mono text-sm font-semibold text-gray-700 dark:text-gray-300">{{ $g->kode }}</td>
                    <td class="font-medium text-gray-900 dark:text-gray-100">{{ $g->nama }}</td>
                    <td>
                        <x-confirm-button
                            action="toggleAktif({{ $g->id }})"
                            title="{{ $g->is_active ? 'Nonaktifkan?' : 'Aktifkan?' }}"
                            text="{{ $g->nama }}"
                            type="{{ $g->is_active ? 'danger' : 'success' }}"
                            confirm="{{ $g->is_active ? 'Ya, Nonaktifkan' : 'Ya, Aktifkan' }}"
                            @class([
                                'inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium',
                                'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300' => $g->is_active,
                                'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300' => !$g->is_active,
                            ])>
                            {{ $g->is_active ? 'Aktif' : 'Nonaktif' }}
                        </x-confirm-button>
                    </td>
                    <td>
                        <button wire:click="openEdit({{ $g->id }})" class="btn-warning btn-sm">Edit</button>
                    </td>
                </tr>
                @empty
                <tr><td colspan="4"><div class="empty-state py-8"><p class="empty-state-text">Belum ada lokasi gudang</p></div></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
