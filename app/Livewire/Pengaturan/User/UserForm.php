<?php

namespace App\Livewire\Pengaturan\User;

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
    public bool   $is_active = true;

    public function getRules(): array
    {
        $uniqueEmail = $this->isEdit
            ? 'unique:users,email,' . $this->userId
            : 'unique:users,email';

        $uniqueNip = $this->isEdit
            ? 'nullable|string|max:30|unique:users,nip,' . $this->userId
            : 'nullable|string|max:30|unique:users,nip';

        $rules = [
            'nama'     => 'required|string|min:3|max:100',
            'email'    => "required|email|{$uniqueEmail}",
            'role'     => 'required|string|exists:roles,name',
            'nip'      => $uniqueNip,
            'telepon'  => 'nullable|string|max:20',
            'is_active'=> 'boolean',
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
            'nama.min'        => 'Nama minimal 3 karakter.',
            'email.unique'    => 'Email sudah terdaftar.',
            'password.min'    => 'Password minimal 8 karakter.',
            'password.confirmed' => 'Konfirmasi password tidak cocok.',
            'role.exists'     => 'Role tidak valid.',
            'nip.unique'      => 'NIP sudah digunakan.',
        ];
    }

    public function openCreate(): void
    {
        $this->authorize('create', User::class);
        $this->reset(['userId', 'nama', 'email', 'password', 'password_confirmation',
                      'role', 'nip', 'telepon']);
        $this->is_active = true;
        $this->isEdit    = false;
        $this->showModal = true;
        $this->resetValidation();
    }

    public function openEdit(int $userId): void
    {
        $user = User::with('roles')->findOrFail($userId);
        $this->authorize('update', $user);

        $this->userId    = $userId;
        $this->nama      = $user->nama;
        $this->email     = $user->email;
        $this->nip       = $user->nip     ?? '';
        $this->telepon   = $user->telepon ?? '';
        $this->role      = $user->roles->first() ? $user->roles->first()->name : '';
        $this->is_active = $user->is_active;
        $this->isEdit    = true;
        $this->showModal = true;
        $this->resetValidation();
    }

    public function save(UserService $service): void
    {
        $this->validate($this->getRules(), $this->getMessages());

        $data = [
            'nama'      => $this->nama,
            'email'     => $this->email,
            'nip'       => $this->nip     ?: null,
            'telepon'   => $this->telepon ?: null,
            'is_active' => $this->is_active,
        ];

        try {
            if ($this->isEdit) {
                $service->update($this->userId, $data, $this->role);
                $message = 'Data pengguna berhasil diupdate.';
            } else {
                $data['password'] = $this->password;
                $service->create($data, $this->role);
                $message = 'Pengguna baru berhasil ditambahkan.';
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

    public function render()
    {
        return view('livewire.pengaturan.user.user-form');
    }
}
