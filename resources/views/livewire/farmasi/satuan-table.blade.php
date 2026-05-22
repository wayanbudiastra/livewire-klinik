<div class="max-w-lg">

    @if ($showForm)
    <div class="card mb-4 animate-fade-in">
        <div class="card-header">
            <h3 class="text-sm font-semibold dark:text-white">{{ $editId ? 'Edit Satuan' : 'Tambah Satuan' }}</h3>
            <button wire:click="$set('showForm', false)" class="text-gray-400 hover:text-gray-600">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
        <div class="card-body">
            <form wire:submit="save" class="flex gap-2">
                <div class="form-group flex-1">
                    <input wire:model="nama" type="text" placeholder="Contoh: Tablet, Botol, Box..."
                           class="form-input dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200"/>
                    @error('nama') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                <button type="submit" class="btn-primary" wire:loading.attr="disabled">Simpan</button>
                <button type="button" wire:click="$set('showForm', false)" class="btn-secondary">Batal</button>
            </form>
        </div>
    </div>
    @else
    <div class="mb-4">
        <button wire:click="openCreate" class="btn-primary">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            Tambah Satuan
        </button>
    </div>
    @endif

    <div class="table-wrapper">
        <table class="table">
            <thead>
                <tr><th>Nama Satuan</th><th>Status</th><th>Aksi</th></tr>
            </thead>
            <tbody>
                @forelse ($this->satuan as $s)
                <tr wire:key="sat-{{ $s->id }}">
                    <td class="font-medium text-gray-900 dark:text-gray-100">{{ $s->nama }}</td>
                    <td>
                        <x-confirm-button
                            action="toggleAktif({{ $s->id }})"
                            title="{{ $s->is_active ? 'Nonaktifkan?' : 'Aktifkan?' }}"
                            text="{{ $s->nama }}"
                            type="{{ $s->is_active ? 'danger' : 'success' }}"
                            confirm="{{ $s->is_active ? 'Ya, Nonaktifkan' : 'Ya, Aktifkan' }}"
                            @class([
                                'inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium',
                                'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300' => $s->is_active,
                                'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300' => !$s->is_active,
                            ])>
                            {{ $s->is_active ? 'Aktif' : 'Nonaktif' }}
                        </x-confirm-button>
                    </td>
                    <td>
                        <button wire:click="openEdit({{ $s->id }})" class="btn-warning btn-sm">Edit</button>
                    </td>
                </tr>
                @empty
                <tr><td colspan="3"><div class="empty-state py-8"><p class="empty-state-text">Belum ada satuan</p></div></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
