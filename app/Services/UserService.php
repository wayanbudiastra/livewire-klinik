<?php

namespace App\Services;

use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class UserService
{
    public function __construct(
        private readonly UserRepositoryInterface $repo
    ) {}

    public function paginate(array $filters, int $perPage = 20): LengthAwarePaginator
    {
        return $this->repo->paginate($filters, $perPage);
    }

    public function create(array $data, string $role): User
    {
        if ($this->repo->findByEmail($data['email'])) {
            throw ValidationException::withMessages([
                'email' => 'Email sudah digunakan oleh user lain.',
            ]);
        }

        $data['password'] = Hash::make($data['password']);
        $user = $this->repo->create($data);
        $user->assignRole($role);

        activity('user')
            ->performedOn($user)
            ->causedBy(auth()->user())
            ->withProperties(['role' => $role])
            ->log('User baru dibuat');

        return $user;
    }

    public function update(int $id, array $data, string $role): User
    {
        $existing = $this->repo->findByEmail($data['email'] ?? '');
        if ($existing && $existing->id !== $id) {
            throw ValidationException::withMessages([
                'email' => 'Email sudah digunakan oleh user lain.',
            ]);
        }

        $user = $this->repo->update($id, $data);
        $user->syncRoles([$role]);

        activity('user')
            ->performedOn($user)
            ->causedBy(auth()->user())
            ->log('Data user diupdate');

        return $user;
    }

    public function toggleActive(int $id, bool $state): User
    {
        $user = $this->repo->toggleActive($id, $state);

        activity('user')
            ->performedOn($user)
            ->causedBy(auth()->user())
            ->log($state ? 'User diaktifkan' : 'User dinonaktifkan');

        return $user;
    }

    public function resetPassword(int $id, string $newPassword): void
    {
        $user = $this->repo->findById($id);

        if (! $user) {
            throw ValidationException::withMessages(['id' => 'User tidak ditemukan.']);
        }

        if ($user->hasRole('super_admin') && auth()->id() !== $user->id) {
            throw ValidationException::withMessages([
                'new_password' => 'Password Super Admin tidak dapat direset melalui panel ini.',
            ]);
        }

        $this->repo->resetPassword($id, Hash::make($newPassword));

        activity('user')
            ->performedOn($user)
            ->causedBy(auth()->user())
            ->log('Password direset oleh admin');
    }

    public function delete(int $id): void
    {
        $user = $this->repo->findById($id);

        if ($user && $user->id === auth()->id()) {
            throw ValidationException::withMessages([
                'id' => 'Tidak dapat menghapus akun sendiri.',
            ]);
        }

        if ($user && $user->hasRole('super_admin')) {
            throw ValidationException::withMessages([
                'id' => 'Akun Super Admin tidak dapat dihapus.',
            ]);
        }

        activity('user')
            ->performedOn($user)
            ->causedBy(auth()->user())
            ->log('User dihapus');

        $this->repo->delete($id);
    }
}
