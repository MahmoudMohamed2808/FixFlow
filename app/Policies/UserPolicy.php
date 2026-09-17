<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    /**
     * Determine whether the user can view any users.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('users.manage');
    }

    /**
     * Determine whether the user can view the user.
     */
    public function view(User $user, User $model): bool
    {
        return $user->hasPermissionTo('users.manage');
    }

    /**
     * Determine whether the user can create users.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('users.manage');
    }

    /**
     * Determine whether the user can update the user.
     */
    public function update(User $user, User $model): bool
    {
        return $user->hasPermissionTo('users.manage');
    }

    /**
     * Determine whether the user can toggle the user's active status.
     */
    public function toggleActive(User $user, User $model): bool
    {
        return $user->hasPermissionTo('users.manage')
            && $user->id !== $model->id;
    }

    /**
     * Determine whether the user can delete the user.
     */
    public function delete(User $user, User $model): bool
    {
        return $user->hasPermissionTo('users.manage')
            && $user->id !== $model->id;
    }
}
