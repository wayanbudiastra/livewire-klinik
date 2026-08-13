<?php

namespace App\Livewire\Pengaturan\User;

use App\Models\Perawat;
use App\Models\User;
use App\Services\UserService;
use Livewire\Component;
use Spatie\Permission\Models\Role;

class UserForm extends Component
{
    public bool  $showModal = false;
    public ?int  $userId    = null;
    public bool  $isEdit    = false;

    public string $nama      = '';
    public string $email     = '';
    public string $password  = '';
    public string $password_confirmation = '';
    public string $role      = '';
    public string $nip       = '';
    public string $telepon   = '';
    public string $nikPerawat = '';
    public bool   $is_active = true;

    // Hak Akses Tambahan (langsung ke user, di luar role) -- hanya
    // relevan & terlihat kalau yang login super_admin. Lihat §Fase 3
    // di prd/roles_permissions_sod_audit.md untuk konteksnya.
    public array $extraPermissions = [];

    public function getRules(): array
    {
        $uniqueEmail = $this->isEdit
            ? 'unique:users,email,' . $this->userId
            : 'unique:users,email';

        $uniqueNip = $this->isEdit
            ? 'nullable|string|max:30|unique:users,nip,' . $this->userId
            : 'nullable|string|max:30|unique:users,nip';

        $rules = [
            'nama'       => 'required|string|min:3|max:100',
            'email'      => "required|email|{$uniqueEmail}",
            'role'       => 'required|string|exists:roles,name',
            'nip'        => $uniqueNip,
            'telepon'    => 'nullable|string|max:20',
            'nikPerawat' => 'nullable|string|size:16|regex:/^[0-9]*$/',
            'is_active'  => 'boolean',
        ];

        if (! $this->isEdit) {
            $rules['password']              = 'required|string|min:8|confirmed';
            $rules['password_confirmation'] = 'required';
        }

        return $rules;
    }

    public function getMessages(): array
    {
        return [
            'nama.min'           => 'Nama minimal 3 karakter.',
            'email.unique'       => 'Email sudah terdaftar.',
            'password.min'       => 'Password minimal 8 karakter.',
            'password.confirmed' => 'Konfirmasi password tidak cocok.',
            'role.exists'        => 'Role tidak valid.',
            'nip.unique'         => 'NIP sudah digunakan.',
            'nikPerawat.size'    => 'NIK harus 16 digit.',
            'nikPerawat.regex'   => 'NIK hanya boleh berisi angka.',
        ];
    }

    public function openCreate(): void
    {
        $this->authorize('create', User::class);
        $this->reset(['userId', 'nama', 'email', 'password', 'password_confirmation',
                      'role', 'nip', 'telepon', 'nikPerawat', 'extraPermissions']);
        $this->is_active = true;
        $this->isEdit    = false;
        $this->showModal = true;
        $this->resetValidation();
    }

    public function openEdit(int $userId): void
    {
        $user = User::with(['roles', 'perawat'])->findOrFail($userId);
        $this->authorize('update', $user);

        $this->userId     = $userId;
        $this->nama       = $user->nama;
        $this->email      = $user->email;
        $this->nip        = $user->nip     ?? '';
        $this->telepon    = $user->telepon  ?? '';
        $this->role       = $user->roles->first()?->name ?? '';
        $this->nikPerawat = $user->perawat?->nik ?? '';
        $this->is_active  = $user->is_active;

        // Hak akses tambahan cuma diambil kalau yang login super_admin --
        // supaya admin biasa yang buka form ini tidak ikut mengosongkan
        // pengecualian yang sudah diberikan super_admin sebelumnya (field-nya
        // tidak dikirim balik saat submit kalau memang tidak dimuat di sini).
        $this->extraPermissions = auth()->user()?->hasRole('super_admin')
            ? $user->getDirectPermissions()->pluck('name')->toArray()
            : [];

        $this->isEdit     = true;
        $this->showModal  = true;
        $this->resetValidation();
    }

    public function save(UserService $service): void
    {
        $this->validate($this->getRules(), $this->getMessages());

        $data = [
            'nama'      => $this->nama,
            'email'     => $this->email,
            'nip'       => $this->nip     ?: null,
            'telepon'   => $this->telepon  ?: null,
            'is_active' => $this->is_active,
        ];

        try {
            if ($this->isEdit) {
                $user    = $service->update($this->userId, $data, $this->role);
                $message = 'Data pengguna berhasil diupdate.';
            } else {
                $data['password'] = $this->password;
                $user    = $service->create($data, $this->role);
                $message = 'Pengguna baru berhasil ditambahkan.';
            }

            // Sync NIK ke perawat jika role perawat
            if ($this->role === 'perawat') {
                Perawat::updateOrCreate(
                    ['user_id' => $user->id],
                    ['nik'     => $this->nikPerawat ?: null]
                );
            }

            // Hak akses tambahan -- HANYA diproses kalau yang login super_admin.
            // Backend-enforced di sini (bukan cuma disembunyikan di Blade),
            // supaya admin biasa tidak bisa memberi dirinya sendiri/orang lain
            // permission ekstra lewat form yang sama (lihat temuan F1-F3 di
            // prd/roles_permissions_sod_audit.md -- pola yang sama sengaja
            // dihindari di sini sejak awal).
            if (auth()->user()?->hasRole('super_admin')) {
                $service->syncExtraPermissions($user->id, $this->extraPermissions, auth()->user());
            }

            $this->showModal = false;
            $this->dispatch('user-saved');
            $this->dispatch('notify', type: 'success', message: $message);

        } catch (\Illuminate\Validation\ValidationException $e) {
            foreach ($e->errors() as $field => $messages) {
                $this->addError($field, $messages[0]);
            }
            $this->dispatch('notify', type: 'error', message: $e->errors()[array_key_first($e->errors())][0]);
        } catch (\Exception $e) {
            $this->dispatch('notify', type: 'error', message: 'Terjadi kesalahan: ' . $e->getMessage());
        }
    }

    public function getRolesListProperty()
    {
        return Role::whereNotIn('name', ['super_admin', 'pasien'])
            ->orderBy('name')
            ->pluck('name');
    }

    /** Semua permission yang ada, dikelompokkan per modul (prefix sebelum titik) untuk checklist. */
    public function getPermissionGroupsProperty(): array
    {
        return \Spatie\Permission\Models\Permission::orderBy('name')
            ->pluck('name')
            ->groupBy(fn (string $name) => str($name)->before('.')->toString())
            ->toArray();
    }

    /** Permission yang sudah otomatis didapat dari role yang sedang dipilih di form -- supaya tidak dobel dicentang sebagai "tambahan". */
    public function getPermissionDariRoleProperty(): array
    {
        if (! $this->role) return [];

        return Role::where('name', $this->role)->first()
            ?->permissions->pluck('name')->toArray() ?? [];
    }

    public function render()
    {
        return view('livewire.pengaturan.user.user-form');
    }
}
