<?php

namespace App\Policies;

use App\Models\Asset;
use App\Models\User;

class AssetPolicy
{
    /**
     * Determine whether the user can view any assets.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission(['assets.view', 'assets.create', 'assets.edit', 'assets.delete']);
    }

    /**
     * Determine whether the user can view the asset.
     */
    public function view(User $user, Asset $asset): bool
    {
        return true;
    }

    /**
     * Determine whether the user can create assets.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('assets.create');
    }

    /**
     * Determine whether the user can update the asset.
     */
    public function update(User $user, Asset $asset): bool
    {
        return $user->hasPermissionTo('assets.edit');
    }

    /**
     * Determine whether the user can delete the asset.
     */
    public function delete(User $user, Asset $asset): bool
    {
        return $user->hasPermissionTo('assets.delete');
    }

    /**
     * Determine whether the user can restore the asset.
     */
    public function restore(User $user, Asset $asset): bool
    {
        return $user->hasPermissionTo('assets.delete');
    }

    /**
     * Determine whether the user can permanently delete the asset.
     */
    public function forceDelete(User $user, Asset $asset): bool
    {
        return $user->hasPermissionTo('assets.delete');
    }
}
