<?php

namespace App\Policies;

use App\Models\Route;
use App\Models\User;

class RoutePolicy
{
    /**
     * Determine if the user can view any routes.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('routes.view');
    }

    /**
     * Determine if the user can view the route.
     */
    public function view(User $user, Route $route): bool
    {
        return $user->hasPermission('routes.view');
    }

    /**
     * Determine if the user can create routes.
     */
    public function create(User $user): bool
    {
        return $user->hasPermission('routes.create');
    }

    /**
     * Determine if the user can update the route.
     */
    public function update(User $user, Route $route): bool
    {
        return $user->hasPermission('routes.update');
    }

    /**
     * Determine if the user can delete/deactivate the route.
     */
    public function delete(User $user, Route $route): bool
    {
        return $user->hasPermission('routes.delete');
    }

    /**
     * Determine if the user can manage route stops.
     */
    public function manageStops(User $user, Route $route): bool
    {
        return $user->hasPermission('routes.manage_stops');
    }

    /**
     * Determine if the user can manage route schedules.
     */
    public function manageSchedules(User $user, Route $route): bool
    {
        return $user->hasPermission('routes.manage_schedules');
    }

    /**
     * Determine if the user can rebuild the route graph cache.
     */
    public function rebuildCache(User $user): bool
    {
        return $user->hasPermission('routes.rebuild_cache');
    }

    /**
     * Determine if the user can find paths between locations.
     */
    public function findPath(User $user): bool
    {
        return $user->hasPermission('routes.view');
    }
}
