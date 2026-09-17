<?php

namespace App\Services;

use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Central place that decides WHO gets notified for each domain event.
 *
 * Internal users only. Inactive users never receive notifications.
 */
class NotificationRecipients
{
    /**
     * Admins + Supervisors who are active.
     *
     * @return Collection<int, User>
     */
    public function managers(): Collection
    {
        return $this->byRoles(['Admin', 'Supervisor']);
    }

    /**
     * Everyone who should follow a work order: assignee + reporter + managers.
     *
     * @return Collection<int, User>
     */
    public function forWorkOrder(WorkOrder $order): Collection
    {
        $users = $this->managers();

        foreach ([$order->assigned_to, $order->reported_by] as $id) {
            if ($id === null) {
                continue;
            }

            $user = User::query()->whereKey($id)->where('is_active', true)->first();

            if ($user !== null && ! $users->contains($user)) {
                $users->push($user);
            }
        }

        return $users;
    }

    /**
     * @param  array<int, string>  $roles
     * @return Collection<int, User>
     */
    public function byRoles(array $roles): Collection
    {
        return User::query()
            ->where('is_active', true)
            ->whereHas('roles', fn ($query) => $query->whereIn('name', $roles))
            ->orderBy('id')
            ->get();
    }

    /**
     * All active internal users (used sparingly, e.g. user lifecycle).
     *
     * @return Collection<int, User>
     */
    public function allActive(): Collection
    {
        return User::query()
            ->where('is_active', true)
            ->orderBy('id')
            ->get();
    }
}
