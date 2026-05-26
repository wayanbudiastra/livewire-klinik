<div>
    @if(session('success'))
    <div class="alert alert-success mb-3">{{ session('success') }}</div>
    @endif

    <div class="card">
        <div class="card-header">
            <h3 class="text-sm font-semibold text-gray-700">Asuransi Pasien</h3>
            @can('asuransi.pasien.manage')
            <button type="button" wire:click="$set('showForm', true)" class="btn-primary btn-sm">+ Tambah</button>
            @endcan
        </div>
        <div class="card-body p-0">
            @if($daftarAsuransiPasien->isEmpty())
            <p class="text-center text-gray-400 py-6 text-sm">Pasien belum memiliki asuransi terdaftar.</p>
            @else
            <table class="table">
                <thead>
                    <tr>
                        <th>Asuransi</th>
                        <th>No. Polis</th>
                        <th>Pemegang Polis</th>
                        <th>Berlaku</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($daftarAsuransiPasien as $pa)
                    <tr>
                        <td>
                            <div class="flex items-center gap-2">
                                <span class="font-medium text-gray-900">{{ $pa->asuransi->nama }}</span>
                                @if($pa->is_primary)
                                    <span class="badge badge-primary text-xs">Utama</span>
                                @endif
                            </div>
                            <span class="text-xs text-gray-400">{{ $pa->asuransi->tipe_label }}</span>
                        </td>
                        <td class="font-mono text-sm">{{ $pa->nomor_polis }}</td>
                        <td class="text-sm">{{ $pa->nama_pemegang_polis ?? '-' }}</td>
                        <td class="text-sm">
                            @if($pa->berlaku_sampai)
                                <span class="{{ $pa->berlaku_sampai->isFuture() ? 'text-gray-700' : 'text-red-500' }}">
                                    s/d {{ $pa->berlaku_sampai->format('d/m/Y') }}
                                </span>
                            @else
                                <span class="text-gray-400">-</span>
                            @endif
                        </td>
                        <td>
                            <div class="flex gap-1">
                                @can('asuransi.pasien.manage')
                                @if(!$pa->is_primary)
                                <button wire:click="setPrimary({{ $pa->id }})" class="btn-secondary btn-sm">
                                    Set Utama
                                </button>
                                @endif
                                <button wire:click="hapus({{ $pa->id }})"
                                    wire:confirm="Hapus asuransi ini dari pasien?"
                                    class="btn-danger btn-sm">Hapus</button>
                                @endcan
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            @endif
        </div>
    </div>

    {{-- Modal Tambah --}}
    @if($showForm)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-lg p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="font-semibold text-gray-900">Tambah Asuransi Pasien</h3>
                <button type="button" wire:click="$set('showForm', false)" class="text-gray-400 hover:text-gray-600">&times;</button>
            </div>
            <div class="space-y-4">
                <div class="form-group">
                    <label class="form-label">Asuransi <span class="text-red-500">*</span></label>
                    <select wire:model="asuransiId" class="form-input">
                        <option value="0">-- Pilih Asuransi --</option>
                        @foreach($opsiAsuransi as $a)
                        <option value="{{ $a->id }}">{{ $a->nama }} ({{ $a->tipe_label }})</option>
                        @endforeach
                    </select>
                    @error('asuransiId') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                <div class="form-group">
                    <label class="form-label">Nomor Polis <span class="text-red-500">*</span></label>
                    <input type="text" wire:model="nomorPolis" class="form-input" placeholder="No. kepesertaan / polis" />
                    @error('nomorPolis') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                <div class="form-group">
                    <label class="form-label">Nama Pemegang Polis</label>
                    <input type="text" wire:model="namaPemegang" class="form-input" />
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div class="form-group">
                        <label class="form-label">Berlaku Mulai</label>
                        <input type="date" wire:model="berlakuMulai" class="form-input" />
                    </div>
                    <div class="form-group">
                        <label class="form-label">Berlaku Sampai</label>
                        <input type="date" wire:model="berlakuSampai" class="form-input" />
                        @error('berlakuSampai') <p class="form-error">{{ $message }}</p> @enderror
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <input type="checkbox" wire:model="isPrimary" id="isPrimary" class="form-checkbox" />
                    <label for="isPrimary" class="text-sm text-gray-700">Jadikan asuransi utama</label>
                </div>
            </div>
            <div class="flex justify-end gap-3 mt-5">
                <button type="button" wire:click="$set('showForm', false)" class="btn-secondary">Batal</button>
                <button wire:click="tambah" wire:loading.attr="disabled" class="btn-primary">
                    <span wire:loading wire:target="tambah">Menyimpan...</span>
                    <span wire:loading.remove wire:target="tambah">Simpan</span>
                </button>
            </div>
        </div>
    </div>
    @endif
</div>
