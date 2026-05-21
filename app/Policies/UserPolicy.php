<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('user.view');
    }

    public function create(User $user): bool
    {
        return $user->can('user.create');
    }

    public function update(User $user, User $target): bool
    {
        if ($target->hasRole('super_admin') && $user->id !== $target->id) {
            return false;
        }
        return $user->can('user.edit');
    }

    public function delete(User $user, User $target): bool
    {
        if ($user->id === $target->id) return false;
        if ($target->hasRole('super_admin')) return false;
        return $user->can('user.delete');
    }

    public function resetPassword(User $user, User $target): bool
    {
        if ($target->hasRole('super_admin') && $user->id !== $target->id) {
            return false;
        }
        return $user->hasRole('super_admin');
    }
}
