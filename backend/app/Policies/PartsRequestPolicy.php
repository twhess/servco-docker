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

    /**
     * Determine if the user can assign request to a run
     */
    public function assignToRun(User $user, PartsRequest $partsRequest): bool
    {
        // Only dispatchers and admins can assign to runs
        return $user->hasPermission('parts_requests.assign_to_run');
    }

    /**
     * Determine if the user can override run assignment
     */
    public function overrideRun(User $user, PartsRequest $partsRequest): bool
    {
        // Only dispatchers and admins can override
        return $user->hasPermission('parts_requests.override_run');
    }

    /**
     * Determine if the user can schedule request for future date
     */
    public function scheduleForDate(User $user, PartsRequest $partsRequest): bool
    {
        // Dispatchers and admins can schedule
        return $user->hasPermission('parts_requests.schedule');
    }

    /**
     * Determine if the user can execute actions on the request
     */
    public function executeAction(User $user, PartsRequest $partsRequest): bool
    {
        // Check if user has permission based on their role
        // This is a general check - specific action validation happens in RequestWorkflowService

        // Dispatchers can execute any action
        if ($user->hasDispatchAccess()) {
            return true;
        }

        // Shop staff can execute actions for requests at their location
        if ($user->primary_location_id === $partsRequest->origin_location_id ||
            $user->primary_location_id === $partsRequest->receiving_location_id) {
            return true;
        }

        // Assigned runner can execute actions
        if ($partsRequest->isAssignedTo($user)) {
            return true;
        }

        return false;
    }

    /**
     * Determine if the user can link items to request
     */
    public function linkItems(User $user, PartsRequest $partsRequest): bool
    {
        // Dispatchers, shop staff at origin, or assigned runner
        if ($user->hasDispatchAccess()) {
            return true;
        }

        if ($user->primary_location_id === $partsRequest->origin_location_id) {
            return true;
        }

        return $partsRequest->isAssignedTo($user);
    }

    /**
     * Determine if the user can view the request feed
     */
    public function viewFeed(User $user): bool
    {
        // Dispatchers can view feed
        return $user->hasPermission('parts_requests.view_feed');
    }

    /**
     * Determine if the user can archive requests
     */
    public function archive(User $user, PartsRequest $partsRequest): bool
    {
        // Only dispatchers and admins can archive
        return $user->hasPermission('parts_requests.archive');
    }

    /**
     * Determine if the user can bulk schedule requests
     */
    public function bulkSchedule(User $user): bool
    {
        // Only dispatchers and admins
        return $user->hasPermission('parts_requests.bulk_schedule');
    }
}
