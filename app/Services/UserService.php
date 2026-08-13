<?php

namespace App\Services;

use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class UserService
{
    private UserRepositoryInterface $repo;

    public function __construct(UserRepositoryInterface $repo)
    {
        $this->repo = $repo;
    }

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

    /**
     * Sinkronkan "Hak Akses Tambahan" -- permission yang diberikan LANGSUNG
     * ke user (di luar apa pun yang sudah didapat dari role-nya), memakai
     * fitur direct permission bawaan spatie/laravel-permission.
     *
     * Dipakai untuk kondisi lapangan di mana satu staf merangkap tugas
     * lebih dari role standarnya (mis. perawat yang juga jadi kasir/FO di
     * klinik kecil) -- tanpa mengubah definisi role standar itu sendiri,
     * supaya baseline SoD (lihat prd/roles_permissions_sod_audit.md) tetap
     * bersih dan pengecualian tercatat jelas per-orang.
     *
     * Wewenang pemanggilan (siapa yang boleh memberi ini) dijaga di
     * Livewire component, bukan di sini -- konsisten dengan pola Fase 1.
     */
    public function syncExtraPermissions(int $id, array $permissions, User $actor): User
    {
        $user = $this->repo->findById($id);

        if (! $user) {
            throw ValidationException::withMessages(['id' => 'User tidak ditemukan.']);
        }

        $sebelum = $user->getDirectPermissions()->pluck('name')->sort()->values()->toArray();
        $sesudah = collect($permissions)->sort()->values()->toArray();

        $user->syncPermissions($permissions);

        if ($sebelum !== $sesudah) {
            activity('user')
                ->performedOn($user)
                ->causedBy($actor)
                ->withProperties(['sebelum' => $sebelum, 'sesudah' => $sesudah])
                ->log('Hak akses tambahan user diubah');
        }

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
