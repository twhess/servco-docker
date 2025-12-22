<?php

namespace App\Policies;

use App\Models\RunInstance;
use App\Models\User;

class RunInstancePolicy
{
    /**
     * Determine if the user can view any run instances.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('runs.view');
    }

    /**
     * Determine if the user can view the run instance.
     */
    public function view(User $user, RunInstance $run): bool
    {
        // Runners can view their own runs
        if ($run->runner_id === $user->id) {
            return true;
        }

        // Dispatchers can view all runs
        return $user->hasPermission('runs.view_all');
    }

    /**
     * Determine if the user can create run instances.
     */
    public function create(User $user): bool
    {
        return $user->hasPermission('runs.create');
    }

    /**
     * Determine if the user can start the run instance.
     */
    public function start(User $user, RunInstance $run): bool
    {
        // Must be assigned runner
        if ($run->runner_id !== $user->id) {
            return false;
        }

        // Run must be in pending status
        return $run->status === 'pending';
    }

    /**
     * Determine if the user can complete the run instance.
     */
    public function complete(User $user, RunInstance $run): bool
    {
        // Must be assigned runner
        if ($run->runner_id !== $user->id) {
            return false;
        }

        // Run must be in progress
        return $run->status === 'in_progress';
    }

    /**
     * Determine if the user can cancel the run instance.
     */
    public function cancel(User $user, RunInstance $run): bool
    {
        // Dispatchers can cancel any run
        if ($user->hasPermission('runs.cancel')) {
            return true;
        }

        // Runners can cancel their own pending runs
        return $run->runner_id === $user->id && $run->status === 'pending';
    }

    /**
     * Determine if the user can reassign the run instance.
     */
    public function reassign(User $user, RunInstance $run): bool
    {
        return $user->hasPermission('runs.reassign');
    }

    /**
     * Determine if the user can arrive at a stop.
     */
    public function arriveAtStop(User $user, RunInstance $run): bool
    {
        // Must be assigned runner and run must be in progress
        return $run->runner_id === $user->id && $run->status === 'in_progress';
    }

    /**
     * Determine if the user can depart from a stop.
     */
    public function departFromStop(User $user, RunInstance $run): bool
    {
        // Must be assigned runner and run must be in progress
        return $run->runner_id === $user->id && $run->status === 'in_progress';
    }

    /**
     * Determine if the user can add notes to the run.
     */
    public function addNote(User $user, RunInstance $run): bool
    {
        // Assigned runner can add notes
        if ($run->runner_id === $user->id) {
            return true;
        }

        // Dispatchers can add notes to any run
        return $user->hasPermission('runs.add_notes');
    }

    /**
     * Determine if the user can view run history.
     */
    public function viewHistory(User $user): bool
    {
        return $user->hasPermission('runs.view_history');
    }

    /**
     * Determine if the user can view their own runs.
     */
    public function viewMyRuns(User $user): bool
    {
        // Any authenticated user with runner role can view their runs
        return $user->hasRole('runner');
    }
}
