<?php

namespace App\Repositories;

use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

class UserRepository implements UserRepositoryInterface
{
    public function paginate(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        return User::query()
            ->with('roles')
            ->when($filters['search'] ?? null, fn ($q, $s) => $q->search($s))
            ->when($filters['role']   ?? null, fn ($q, $r) => $q->role($r))
            ->when(
                isset($filters['is_active']) && $filters['is_active'] !== null,
                fn ($q) => $q->where('is_active', $filters['is_active'])
            )
            ->orderBy($filters['sort_by']  ?? 'created_at', $filters['sort_dir'] ?? 'desc')
            ->paginate($perPage);
    }

    public function findById(int $id): ?User
    {
        return User::with('roles')->find($id);
    }

    public function findByEmail(string $email): ?User
    {
        return User::where('email', $email)->first();
    }

    public function create(array $data): User
    {
        return User::create($data);
    }

    public function update(int $id, array $data): User
    {
        $user = User::findOrFail($id);
        $user->update($data);
        return $user->fresh('roles');
    }

    public function toggleActive(int $id, bool $state): User
    {
        $user = User::findOrFail($id);
        $user->update(['is_active' => $state]);
        return $user;
    }

    public function resetPassword(int $id, string $hashedPassword): void
    {
        User::where('id', $id)->update([
            'password'            => $hashedPassword,
            'password_changed_at' => Carbon::now(),
        ]);
    }

    public function delete(int $id): void
    {
        User::findOrFail($id)->delete();
    }
}
