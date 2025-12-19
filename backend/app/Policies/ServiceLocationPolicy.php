<?php

namespace App\Policies;

use App\Models\ServiceLocation;
use App\Models\User;

class ServiceLocationPolicy
{
    /**
     * Determine if the user can view any locations
     */
    public function viewAny(User $user): bool
    {
        // All roles except runner/driver can view the list
        return !in_array($user->role, ['runner_driver', 'technician_mobile']);
    }

    /**
     * Determine if the user can view a specific location
     */
    public function view(User $user, ServiceLocation $location): bool
    {
        // Admins and dispatchers can view all
        if ($user->hasDispatchAccess()) {
            return true;
        }

        // Check if user can access this location
        if ($user->canAccessLocation($location->id)) {
            return true;
        }

        // Mobile users can view their assigned location
        if (in_array($user->role, ['runner_driver', 'technician_mobile'])) {
            if ($location->assigned_user_id === $user->id) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine if the user can create locations
     */
    public function create(User $user): bool
    {
        return $user->hasAdminAccess();
    }

    /**
     * Determine if the user can update location details
     */
    public function updateDetails(User $user, ServiceLocation $location): bool
    {
        // Super admin and ops admin can edit all
        if ($user->hasAdminAccess()) {
            return true;
        }

        // Shop manager can edit their shop
        if ($user->role === 'shop_manager' && $location->location_type === 'fixed_shop') {
            return $user->canAccessLocation($location->id);
        }

        // Parts manager can edit vendors
        if ($user->role === 'parts_manager' && $location->location_type === 'vendor') {
            return $user->canAccessLocation($location->id);
        }

        // Dispatcher can edit mobile and customer sites
        if ($user->isDispatcher() && in_array($location->location_type, ['mobile_service_truck', 'parts_runner_vehicle', 'customer_site'])) {
            return true;
        }

        return false;
    }

    /**
     * Determine if the user can update location contacts
     */
    public function updateContacts(User $user, ServiceLocation $location): bool
    {
        // Admins can update all contacts
        if ($user->hasAdminAccess()) {
            return true;
        }

        // Shop manager can update their shop contacts
        if ($user->role === 'shop_manager' && $location->location_type === 'fixed_shop') {
            return $user->canAccessLocation($location->id);
        }

        // Parts manager can update vendor and shop contacts
        if ($user->role === 'parts_manager') {
            return in_array($location->location_type, ['fixed_shop', 'vendor']);
        }

        return false;
    }

    /**
     * Determine if the user can assign users to locations
     */
    public function assignUser(User $user, ServiceLocation $location): bool
    {
        // Only for mobile types
        if (!$location->isMobile()) {
            return false;
        }

        // Admins and dispatchers can assign
        if ($user->hasDispatchAccess()) {
            return true;
        }

        // Parts manager can assign runner vehicles
        if ($user->role === 'parts_manager' && $location->location_type === 'parts_runner_vehicle') {
            return true;
        }

        return false;
    }

    /**
     * Determine if the user can update location status
     */
    public function updateStatus(User $user, ServiceLocation $location): bool
    {
        // Admins and dispatchers can update all
        if ($user->hasDispatchAccess()) {
            return true;
        }

        // Parts manager can update runner vehicles
        if ($user->role === 'parts_manager' && $location->location_type === 'parts_runner_vehicle') {
            return true;
        }

        // Assigned user can update their own status
        if (in_array($user->role, ['runner_driver', 'technician_mobile'])) {
            if ($location->assigned_user_id === $user->id) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine if the user can record position for a location
     */
    public function recordPosition(User $user, ServiceLocation $location): bool
    {
        // Only for mobile types
        if (!$location->isMobile()) {
            return false;
        }

        // Dispatcher can override position
        if ($user->isDispatcher()) {
            return true;
        }

        // Assigned user can record their position
        if (in_array($user->role, ['runner_driver', 'technician_mobile'])) {
            if ($location->assigned_user_id === $user->id) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine if the user can delete/deactivate locations
     */
    public function delete(User $user, ServiceLocation $location): bool
    {
        return $user->hasAdminAccess();
    }

    /**
     * Determine if the user can restore deleted locations
     */
    public function restore(User $user, ServiceLocation $location): bool
    {
        return $user->hasAdminAccess();
    }

    /**
     * Determine if the user can permanently delete locations
     */
    public function forceDelete(User $user, ServiceLocation $location): bool
    {
        return $user->isSuperAdmin();
    }
}
