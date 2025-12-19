<?php

namespace App\Policies;

use App\Models\PartsRequest;
use App\Models\User;

class PartsRequestPolicy
{
    /**
     * Determine if the user can view any parts requests
     */
    public function viewAny(User $user): bool
    {
        // Dispatchers and admins can view all
        // Runners can view their assigned requests
        return true;
    }

    /**
     * Determine if the user can view a specific parts request
     */
    public function view(User $user, PartsRequest $partsRequest): bool
    {
        // Admins and dispatchers can view all
        if ($user->hasDispatchAccess()) {
            return true;
        }

        // Runner can view their assigned requests
        return $partsRequest->isAssignedTo($user);
    }

    /**
     * Determine if the user can create parts requests
     */
    public function create(User $user): bool
    {
        // Only dispatchers and admins can create
        return $user->hasDispatchAccess();
    }

    /**
     * Determine if the user can update a parts request
     */
    public function update(User $user, PartsRequest $partsRequest): bool
    {
        // Admins and dispatchers can always update
        if ($user->hasDispatchAccess()) {
            return true;
        }

        // Assigned runner cannot update core fields, but can update status/events
        return false;
    }

    /**
     * Determine if the user can assign runners
     */
    public function assign(User $user, PartsRequest $partsRequest): bool
    {
        // Only dispatchers and admins can assign
        return $user->hasDispatchAccess();
    }

    /**
     * Determine if the user can delete a parts request
     */
    public function delete(User $user, PartsRequest $partsRequest): bool
    {
        // Only admins can delete
        return $user->hasAdminAccess();
    }
}
