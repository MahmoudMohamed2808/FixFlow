<?php

namespace App\Policies;

use App\Models\PreventiveSchedule;
use App\Models\User;

class PreventiveSchedulePolicy
{
    /**
     * Determine whether the user can view any schedules.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('schedules.view');
    }

    /**
     * Determine whether the user can view the schedule.
     */
    public function view(User $user, PreventiveSchedule $schedule): bool
    {
        return $user->hasPermissionTo('schedules.view');
    }

    /**
     * Determine whether the user can create schedules.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('schedules.create');
    }

    /**
     * Determine whether the user can update the schedule.
     */
    public function update(User $user, PreventiveSchedule $schedule): bool
    {
        return $user->hasPermissionTo('schedules.edit');
    }

    /**
     * Determine whether the user can delete the schedule.
     */
    public function delete(User $user, PreventiveSchedule $schedule): bool
    {
        return $user->hasPermissionTo('schedules.delete');
    }

    /**
     * Determine whether the user can restore the schedule.
     */
    public function restore(User $user, PreventiveSchedule $schedule): bool
    {
        return $user->hasPermissionTo('schedules.delete');
    }

    /**
     * Determine whether the user can permanently delete the schedule.
     */
    public function forceDelete(User $user, PreventiveSchedule $schedule): bool
    {
        return $user->hasPermissionTo('schedules.delete');
    }
}
