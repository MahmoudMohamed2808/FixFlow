<?php

namespace App\Policies;

use App\Models\Budget;
use App\Models\User;

class BudgetPolicy
{
    /**
     * Determine whether the user can view any budgets.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('budgets.view');
    }

    /**
     * Determine whether the user can view the budget.
     */
    public function view(User $user, Budget $budget): bool
    {
        return $user->hasPermissionTo('budgets.view');
    }

    /**
     * Determine whether the user can create budgets.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('budgets.create');
    }

    /**
     * Determine whether the user can update the budget.
     */
    public function update(User $user, Budget $budget): bool
    {
        return $user->hasPermissionTo('budgets.edit');
    }

    /**
     * Determine whether the user can delete the budget.
     */
    public function delete(User $user, Budget $budget): bool
    {
        return $user->hasPermissionTo('budgets.delete');
    }

    /**
     * Determine whether the user can restore the budget.
     */
    public function restore(User $user, Budget $budget): bool
    {
        return $user->hasPermissionTo('budgets.delete');
    }

    /**
     * Determine whether the user can permanently delete the budget.
     */
    public function forceDelete(User $user, Budget $budget): bool
    {
        return $user->hasPermissionTo('budgets.delete');
    }
}
