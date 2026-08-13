<div>
    {{-- Trigger events dari user-table --}}
    <span
        x-on:open-create-user.window="$wire.openCreate()"
        x-on:open-edit-user.window="$wire.openEdit($event.detail.userId)"
    ></span>

    {{-- Modal Overlay --}}
    @if ($showModal)
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4"
         x-data x-init="$el.scrollIntoView()">

        {{-- Backdrop --}}
        <div class="absolute inset-0 bg-black/50" wire:click="$set('showModal', false)"></div>

        {{-- Modal --}}
        <div class="relative z-10 w-full max-w-lg rounded-2xl bg-white shadow-2xl
                    dark:bg-gray-800 dark:border dark:border-gray-700
                    animate-fade-in">

            {{-- Header --}}
            <div class="modal-header">
                <h3 class="modal-title dark:text-white">
                    {{ $isEdit ? 'Edit Pengguna' : 'Tambah Pengguna Baru' }}
                </h3>
                <button wire:click="$set('showModal', false)"
                        class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            {{-- Body --}}
            <div class="modal-body">
                <form wire:submit="save" class="space-y-4">

                    {{-- Nama --}}
                    <div class="form-group">
                        <label class="form-label dark:text-gray-300">
                            Nama Lengkap <span class="text-red-500">*</span>
                        </label>
                        <input wire:model="nama" type="text" placeholder="Nama lengkap pengguna"
                               class="form-input dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200"/>
                        @error('nama') <p class="form-error">{{ $message }}</p> @enderror
                    </div>

                    {{-- Email --}}
                    <div class="form-group">
                        <label class="form-label dark:text-gray-300">
                            Email <span class="text-red-500">*</span>
                        </label>
                        <input wire:model="email" type="email" placeholder="email@domain.com"
                               class="form-input dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200"/>
                        @error('email') <p class="form-error">{{ $message }}</p> @enderror
                    </div>

                    {{-- Role --}}
                    <div class="form-group">
                        <label class="form-label dark:text-gray-300">
                            Role <span class="text-red-500">*</span>
                        </label>
                        <select wire:model.live="role"
                                class="form-select dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200">
                            <option value="">-- Pilih Role --</option>
                            @foreach ($this->rolesList as $r)
                                <option value="{{ $r }}">{{ ucfirst(str_replace('_', ' ', $r)) }}</option>
                            @endforeach
                        </select>
                        @error('role') <p class="form-error">{{ $message }}</p> @enderror
                    </div>

                    {{-- NIK Perawat (hanya muncul jika role = perawat) --}}
                    @if($role === 'perawat')
                    <div class="form-group rounded-xl border border-emerald-200 dark:border-emerald-700 bg-emerald-50 dark:bg-emerald-900/10 p-3">
                        <label class="form-label dark:text-gray-300">
                            NIK Perawat
                            <span class="ml-1 text-xs text-emerald-600 dark:text-emerald-400 font-normal">(untuk integrasi IHS SatuSehat)</span>
                        </label>
                        <input wire:model="nikPerawat" type="text" inputmode="numeric" maxlength="16"
                               placeholder="16 digit NIK"
                               class="form-input font-mono dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200"/>
                        @error('nikPerawat') <p class="form-error">{{ $message }}</p> @enderror
                        <p class="text-xs text-gray-400 mt-1">Kosongkan jika belum diketahui. Bisa diisi belakangan.</p>
                    </div>
                    @endif

                    {{-- NIP & Telepon (2 kolom) --}}
                    <div class="grid grid-cols-2 gap-4">
                        <div class="form-group">
                            <label class="form-label dark:text-gray-300">NIP</label>
                            <input wire:model="nip" type="text" placeholder="Nomor Induk Pegawai"
                                   class="form-input dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200"/>
                            @error('nip') <p class="form-error">{{ $message }}</p> @enderror
                        </div>
                        <div class="form-group">
                            <label class="form-label dark:text-gray-300">Telepon</label>
                            <input wire:model="telepon" type="text" placeholder="08xxxxxxxxxx"
                                   class="form-input dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200"/>
                            @error('telepon') <p class="form-error">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    {{-- Password (hanya create) --}}
                    @if (! $isEdit)
                    <div class="grid grid-cols-2 gap-4">
                        <div class="form-group">
                            <label class="form-label dark:text-gray-300">
                                Password <span class="text-red-500">*</span>
                            </label>
                            <input wire:model="password" type="password" placeholder="Min. 8 karakter"
                                   class="form-input dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200"/>
                            @error('password') <p class="form-error">{{ $message }}</p> @enderror
                        </div>
                        <div class="form-group">
                            <label class="form-label dark:text-gray-300">
                                Konfirmasi Password <span class="text-red-500">*</span>
                            </label>
                            <input wire:model="password_confirmation" type="password" placeholder="Ulangi password"
                                   class="form-input dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200"/>
                        </div>
                    </div>
                    @endif

                    {{-- Hak Akses Tambahan (super_admin saja, & hanya saat edit) --}}
                    @if ($isEdit && auth()->user()?->hasRole('super_admin'))
                    <div class="form-group rounded-xl border border-amber-200 dark:border-amber-700 bg-amber-50 dark:bg-amber-900/10 p-3">
                        <label class="form-label dark:text-gray-300 flex items-center gap-1.5">
                            Hak Akses Tambahan
                            <span class="text-xs text-amber-600 dark:text-amber-400 font-normal">(di luar role — untuk staf yang merangkap tugas)</span>
                        </label>
                        <p class="text-xs text-gray-400 mb-2">
                            Permission bertanda <span class="italic">"dari role"</span> sudah otomatis didapat dan tidak perlu dicentang manual — hanya centang yang benar-benar tambahan.
                        </p>
                        <div class="max-h-56 overflow-y-auto scrollbar-thin scrollbar-thumb-gray-200 dark:scrollbar-thumb-gray-600 rounded-lg border border-gray-200 dark:border-gray-600 dark:bg-gray-800/50 p-2 space-y-2">
                            @foreach ($this->permissionGroups as $modul => $permsInModul)
                            <div>
                                <p class="text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wide mb-1">{{ str_replace('_', ' ', $modul) }}</p>
                                <div class="grid grid-cols-2 gap-1">
                                    @foreach ($permsInModul as $perm)
                                        @php $dariRole = in_array($perm, $this->permissionDariRole); @endphp
                                        <label class="flex items-center gap-1.5 text-xs {{ $dariRole ? 'text-gray-400 dark:text-gray-500' : 'text-gray-700 dark:text-gray-300 cursor-pointer' }}">
                                            <input type="checkbox"
                                                   value="{{ $perm }}"
                                                   wire:model="extraPermissions"
                                                   @if($dariRole) checked disabled @endif
                                                   class="form-checkbox h-3.5 w-3.5"/>
                                            {{ $perm }}
                                            @if($dariRole)<span class="italic">(dari role)</span>@endif
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                            @endforeach
                        </div>
                    </div>
                    @endif

                    {{-- Status Aktif --}}
                    <div class="flex items-center gap-3">
                        <button type="button" wire:click="$set('is_active', !{{ $is_active ? 'true' : 'false' }})"
                                @class([
                                    'relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-1',
                                    'bg-primary-600' => $is_active,
                                    'bg-gray-300 dark:bg-gray-600' => !$is_active,
                                ])>
                            <span @class([
                                'inline-block h-4 w-4 rounded-full bg-white shadow-sm transition-transform',
                                'translate-x-6' => $is_active,
                                'translate-x-1' => !$is_active,
                            ])></span>
                        </button>
                        <span class="text-sm text-gray-700 dark:text-gray-300">
                            {{ $is_active ? 'Akun Aktif' : 'Akun Nonaktif' }}
                        </span>
                    </div>

                    {{-- Footer --}}
                    <div class="modal-footer -mx-5 -mb-5 mt-2">
                        <button type="button" wire:click="$set('showModal', false)" class="btn-secondary">
                            Batal
                        </button>
                        <button type="submit" class="btn-primary" wire:loading.attr="disabled">
                            <span wire:loading.remove wire:target="save">
                                {{ $isEdit ? 'Simpan Perubahan' : 'Tambah Pengguna' }}
                            </span>
                            <span wire:loading wire:target="save" class="flex items-center gap-2">
                                <div class="spinner h-4 w-4 border-white border-t-transparent"></div>
                                Menyimpan...
                            </span>
                        </button>
                    </div>

                </form>
            </div>
        </div>
    </div>
    @endif
</div>
