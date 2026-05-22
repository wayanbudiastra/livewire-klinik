<div>
    <div class="mb-4 flex flex-col sm:flex-row gap-3 justify-between">
        <div class="flex flex-wrap gap-2">
            <div class="relative">
                <span class="absolute inset-y-0 left-3 flex items-center text-gray-400 pointer-events-none">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 11A6 6 0 1 1 5 11a6 6 0 0 1 12 0z"/></svg>
                </span>
                <input wire:model.live.debounce.400ms="search" type="text" placeholder="Nama / kode supplier..."
                       class="form-input pl-9 w-64 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200"/>
            </div>
            <select wire:model.live="tipe" class="form-select w-40 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200">
                <option value="">Semua Tipe</option>
                @foreach (\App\Models\Supplier::getTipeOptions() as $val => $lbl)
                    <option value="{{ $val }}">{{ $lbl }}</option>
                @endforeach
            </select>
        </div>
        <button wire:click="$dispatch('open-supplier-create')" class="btn-primary whitespace-nowrap">+ Tambah Supplier</button>
    </div>

    <div class="table-wrapper">
        <table class="table">
            <thead><tr><th>Kode</th><th>Nama</th><th>Tipe</th><th>PIC / Telepon</th><th>Lead Time</th><th>TOP</th><th>Barang</th><th>Status</th><th>Aksi</th></tr></thead>
            <tbody>
                @forelse ($this->suppliers as $s)
                <tr wire:key="sup-{{ $s->id }}">
                    <td class="font-mono text-xs font-semibold text-gray-600 dark:text-gray-400">{{ $s->kode }}</td>
                    <td>
                        <p class="font-medium text-gray-900 dark:text-gray-100">{{ $s->nama }}</p>
                        @if($s->email)<p class="text-xs text-gray-400">{{ $s->email }}</p>@endif
                    </td>
                    <td><span class="badge-primary">{{ ucfirst($s->tipe) }}</span></td>
                    <td class="text-sm text-gray-600 dark:text-gray-400">
                        <p>{{ $s->pic ?? '-' }}</p>
                        <p class="text-xs text-gray-400">{{ $s->telepon ?? '-' }}</p>
                    </td>
                    <td class="text-sm text-center text-gray-600">{{ $s->lead_time_hari }} hari</td>
                    <td class="text-sm text-center text-gray-600">{{ $s->top_hari }} hari</td>
                    <td class="text-center"><span class="badge-gray">{{ $s->barang_count }}</span></td>
                    <td>
                        <x-confirm-button action="toggleAktif({{ $s->id }})"
                            title="{{ $s->is_active ? 'Nonaktifkan?' : 'Aktifkan?' }}" text="{{ $s->nama }}"
                            type="{{ $s->is_active ? 'danger' : 'success' }}"
                            confirm="{{ $s->is_active ? 'Ya, Nonaktifkan' : 'Ya, Aktifkan' }}"
                            @class(['inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium',
                                'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300' => $s->is_active,
                                'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300' => !$s->is_active])>
                            {{ $s->is_active ? 'Aktif' : 'Nonaktif' }}
                        </x-confirm-button>
                    </td>
                    <td><button wire:click="$dispatch('open-supplier-edit', { id: {{ $s->id }} })" class="btn-warning btn-sm">Edit</button></td>
                </tr>
                @empty
                <tr><td colspan="9"><div class="empty-state"><p class="empty-state-text">Belum ada supplier</p></div></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $this->suppliers->links() }}</div>
</div>
