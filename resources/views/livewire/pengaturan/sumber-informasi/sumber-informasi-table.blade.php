<div>
    <div class="page-header">
        <div>
            <h1 class="page-title">Master Sumber Informasi</h1>
            <p class="page-subtitle">Kelola pilihan sumber informasi pasien baru</p>
        </div>
        <button type="button" wire:click="buatBaru" class="btn-primary">+ Tambah Sumber</button>
    </div>

    <div class="card">
        <div class="card-body p-0">
            <table class="table">
                <thead>
                    <tr>
                        <th class="w-16 text-center">Urutan</th>
                        <th class="w-12 text-center">Icon</th>
                        <th>Nama</th>
                        <th>Kategori</th>
                        <th class="text-center">Butuh Keterangan</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($daftar as $s)
                    <tr>
                        <td class="text-center text-gray-400 text-sm">{{ $s->urutan }}</td>
                        <td class="text-center text-xl">{{ $s->icon }}</td>
                        <td class="font-medium text-gray-900">{{ $s->nama }}</td>
                        <td>
                            <span class="badge badge-gray text-xs">
                                {{ ucfirst(str_replace('_', ' ', $s->kategori)) }}
                            </span>
                        </td>
                        <td class="text-center">
                            @if($s->butuh_keterangan)
                                <span class="text-green-600 font-bold">✓</span>
                            @else
                                <span class="text-gray-300">—</span>
                            @endif
                        </td>
                        <td>
                            @if($s->is_active)
                                <span class="badge badge-success">Aktif</span>
                            @else
                                <span class="badge badge-gray">Nonaktif</span>
                            @endif
                        </td>
                        <td>
                            <div class="flex gap-1">
                                <button wire:click="edit({{ $s->id }})" class="btn-warning btn-sm">Edit</button>
                                <button wire:click="toggleActive({{ $s->id }})"
                                    wire:loading.attr="disabled"
                                    wire:target="toggleActive({{ $s->id }})"
                                    class="btn-secondary btn-sm">
                                    {{ $s->is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                                </button>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="text-center text-gray-400 py-8">Belum ada data sumber informasi.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Modal Form --}}
    @if($showForm)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50"
         x-data @click.self="$wire.set('showForm', false)">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-md mx-4">
            <div class="modal-header flex items-center justify-between px-5 py-4 border-b">
                <h3 class="text-base font-semibold text-gray-800">
                    {{ $editId ? 'Edit' : 'Tambah' }} Sumber Informasi
                </h3>
                <button wire:click="$set('showForm', false)" class="text-gray-400 hover:text-gray-600 text-lg leading-none">✕</button>
            </div>
            <div class="px-5 py-4 space-y-4">
                <div class="grid grid-cols-3 gap-3">
                    <div class="form-group">
                        <label class="form-label">Icon (emoji)</label>
                        <input type="text" wire:model="icon"
                            class="form-input text-center text-xl"
                            placeholder="🔍" maxlength="2" />
                    </div>
                    <div class="form-group col-span-2">
                        <label class="form-label">Nama <span class="text-red-500">*</span></label>
                        <input type="text" wire:model="nama"
                            class="form-input @error('nama') border-red-400 @enderror"
                            placeholder="Nama sumber informasi" />
                        @error('nama') <p class="form-error">{{ $message }}</p> @enderror
                    </div>
                </div>

                @if(!$editId)
                <div class="form-group">
                    <label class="form-label">Kode (opsional)</label>
                    <input type="text" wire:model="kode" class="form-input font-mono text-sm"
                        placeholder="Auto-generate dari nama" />
                    <p class="text-xs text-gray-400 mt-1">Kosongkan untuk generate otomatis dari nama.</p>
                </div>
                @endif

                <div class="grid grid-cols-2 gap-3">
                    <div class="form-group">
                        <label class="form-label">Kategori</label>
                        <select wire:model="kategori" class="form-select @error('kategori') border-red-400 @enderror">
                            <option value="digital">Digital</option>
                            <option value="sosial_media">Sosial Media</option>
                            <option value="word_of_mouth">Word of Mouth</option>
                            <option value="offline">Offline</option>
                            <option value="lainnya">Lainnya</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Urutan Tampil</label>
                        <input type="number" wire:model="urutan" class="form-input" min="0" />
                    </div>
                </div>

                <div class="flex items-center gap-6">
                    <label class="flex items-center gap-2 cursor-pointer select-none">
                        <input type="checkbox" wire:model="butuhKeterangan" class="form-checkbox" />
                        <span class="text-sm text-gray-700">Butuh keterangan bebas</span>
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer select-none">
                        <input type="checkbox" wire:model="isActive" class="form-checkbox" />
                        <span class="text-sm text-gray-700">Aktif</span>
                    </label>
                </div>
            </div>
            <div class="flex justify-end gap-3 px-5 py-4 border-t bg-gray-50 rounded-b-xl">
                <button wire:click="$set('showForm', false)" class="btn-secondary">Batal</button>
                <button wire:click="simpan" wire:loading.attr="disabled" class="btn-primary">
                    <span wire:loading.remove wire:target="simpan">Simpan</span>
                    <span wire:loading wire:target="simpan">Menyimpan...</span>
                </button>
            </div>
        </div>
    </div>
    @endif
</div>
