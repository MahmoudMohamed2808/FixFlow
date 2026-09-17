<?php

namespace App\Policies;

use App\Models\SparePart;
use App\Models\User;

class SparePartPolicy
{
    /**
     * Determine whether the user can view any spare parts.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('inventory.view');
    }

    /**
     * Determine whether the user can view the spare part.
     */
    public function view(User $user, SparePart $sparePart): bool
    {
        return $user->hasPermissionTo('inventory.view');
    }

    /**
     * Determine whether the user can create spare parts.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('inventory.create');
    }

    /**
     * Determine whether the user can update the spare part.
     */
    public function update(User $user, SparePart $sparePart): bool
    {
        return $user->hasPermissionTo('inventory.edit');
    }

    /**
     * Determine whether the user can adjust the spare part stock.
     */
    public function adjustStock(User $user, SparePart $sparePart): bool
    {
        return $user->hasPermissionTo('inventory.edit');
    }

    /**
     * Determine whether the user can delete the spare part.
     */
    public function delete(User $user, SparePart $sparePart): bool
    {
        return $user->hasPermissionTo('inventory.delete');
    }

    /**
     * Determine whether the user can restore the spare part.
     */
    public function restore(User $user, SparePart $sparePart): bool
    {
        return $user->hasPermissionTo('inventory.delete');
    }

    /**
     * Determine whether the user can permanently delete the spare part.
     */
    public function forceDelete(User $user, SparePart $sparePart): bool
    {
        return $user->hasPermissionTo('inventory.delete');
    }
}
