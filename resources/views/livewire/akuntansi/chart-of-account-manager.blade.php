<div class="space-y-4">

    <div class="flex justify-end">
        <button wire:click="bukaForm" class="btn-primary">+ Tambah Akun</button>
    </div>

    <div class="card">
        <div class="card-body p-0">
            <table class="table">
                <thead>
                    <tr>
                        <th style="width:90px">Kode</th>
                        <th>Nama Akun</th>
                        <th>Golongan</th>
                        <th class="text-center">Tipe Normal</th>
                        <th class="text-right">Saldo</th>
                        <th class="text-center">Status</th>
                        <th class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($this->akunList as $akun)
                    <tr wire:key="coa-{{ $akun->id }}">
                        <td class="font-mono text-sm font-semibold text-[#0a3d62] dark:text-blue-400">{{ $akun->kode }}</td>
                        <td class="text-sm">{{ $akun->nama }}</td>
                        <td class="text-sm text-gray-500 capitalize">{{ $akun->golongan }}</td>
                        <td class="text-center text-xs capitalize">{{ $akun->tipe_normal }}</td>
                        <td class="text-right text-sm font-medium">Rp {{ number_format($akun->saldo, 0, ',', '.') }}</td>
                        <td class="text-center">
                            <button wire:click="toggleAktif({{ $akun->id }})"
                                @class([
                                    'badge cursor-pointer',
                                    'badge-success' => $akun->is_aktif,
                                    'badge-gray' => !$akun->is_aktif,
                                ])>
                                {{ $akun->is_aktif ? 'Aktif' : 'Nonaktif' }}
                            </button>
                        </td>
                        <td class="text-center">
                            <button wire:click="bukaForm({{ $akun->id }})" class="btn-xs btn-secondary">Edit</button>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="7" class="empty-state py-10"><p class="empty-state-text">Belum ada akun</p></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Modal Form --}}
    @if($showForm)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" wire:click.self="$set('showForm', false)">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-xl w-full max-w-md p-6 space-y-4">
            <h3 class="text-base font-semibold dark:text-white">{{ $editId ? 'Edit Akun' : 'Tambah Akun Baru' }}</h3>

            <div class="form-group">
                <label class="form-label">Kode Akun <span class="text-red-500">*</span></label>
                <input type="text" wire:model="kode" placeholder="1-1100" maxlength="10"
                    class="form-input dark:bg-gray-900 dark:border-gray-600 dark:text-gray-200" />
                @error('kode') <p class="form-error">{{ $message }}</p> @enderror
            </div>
            <div class="form-group">
                <label class="form-label">Nama Akun <span class="text-red-500">*</span></label>
                <input type="text" wire:model="nama" class="form-input dark:bg-gray-900 dark:border-gray-600 dark:text-gray-200" />
                @error('nama') <p class="form-error">{{ $message }}</p> @enderror
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div class="form-group">
                    <label class="form-label">Golongan</label>
                    <select wire:model="golongan" class="form-input dark:bg-gray-900 dark:border-gray-600 dark:text-gray-200">
                        <option value="aset">Aset</option>
                        <option value="liabilitas">Liabilitas</option>
                        <option value="ekuitas">Ekuitas</option>
                        <option value="pendapatan">Pendapatan</option>
                        <option value="biaya">Biaya</option>
                        <option value="lainnya">Lainnya</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Tipe Normal</label>
                    <select wire:model="tipe_normal" class="form-input dark:bg-gray-900 dark:border-gray-600 dark:text-gray-200">
                        <option value="debit">Debit</option>
                        <option value="kredit">Kredit</option>
                    </select>
                </div>
            </div>
            <label class="flex items-center gap-2">
                <input type="checkbox" wire:model="is_aktif" class="rounded text-blue-600" />
                <span class="text-sm">Akun aktif</span>
            </label>

            <div class="flex justify-end gap-2 pt-2">
                <button wire:click="$set('showForm', false)" class="btn-secondary">Batal</button>
                <button wire:click="simpan" class="btn-primary">Simpan</button>
            </div>
        </div>
    </div>
    @endif

</div>
