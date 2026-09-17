<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WorkOrder;

class WorkOrderPolicy
{
    /**
     * Determine whether the user can view any work orders.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('work_orders.view');
    }

    /**
     * Determine whether the user can view the work order.
     */
    public function view(User $user, WorkOrder $workOrder): bool
    {
        return $user->hasPermissionTo('work_orders.view')
            || $workOrder->assigned_to === $user->id
            || $workOrder->reported_by === $user->id;
    }

    /**
     * Determine whether the user can create work orders.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('work_orders.create');
    }

    /**
     * Determine whether the user can update the work order.
     */
    public function update(User $user, WorkOrder $workOrder): bool
    {
        return $user->hasPermissionTo('work_orders.edit');
    }

    /**
     * Determine whether the user can assign technicians.
     */
    public function assign(User $user, WorkOrder $workOrder): bool
    {
        return $user->hasPermissionTo('work_orders.assign');
    }

    /**
     * Determine whether the user can change the work order status.
     */
    public function updateStatus(User $user, WorkOrder $workOrder): bool
    {
        return $user->hasPermissionTo('work_orders.update_status')
            || $workOrder->assigned_to === $user->id;
    }

    /**
     * Determine whether the user can comment on the work order.
     */
    public function comment(User $user, WorkOrder $workOrder): bool
    {
        return $user->hasPermissionTo('work_orders.comment');
    }

    /**
     * Determine whether the user can delete the work order.
     */
    public function delete(User $user, WorkOrder $workOrder): bool
    {
        return $user->hasPermissionTo('work_orders.delete');
    }

    /**
     * Determine whether the user can restore the work order.
     */
    public function restore(User $user, WorkOrder $workOrder): bool
    {
        return $user->hasPermissionTo('work_orders.delete');
    }

    /**
     * Determine whether the user can permanently delete the work order.
     */
    public function forceDelete(User $user, WorkOrder $workOrder): bool
    {
        return $user->hasPermissionTo('work_orders.delete');
    }
}
