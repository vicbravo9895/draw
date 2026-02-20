<?php

namespace App\Policies;

use App\Models\Inspection;
use App\Models\User;

class InspectionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('inspections.view');
    }

    public function view(User $user, Inspection $inspection): bool
    {
        if (! $user->hasPermissionTo('inspections.view')) {
            return false;
        }

        // super_admin can see all
        if ($user->hasRole('super_admin')) {
            return true;
        }

        if (! $user->hasAccessToCompany($inspection->company_id)) {
            return false;
        }

        // Inspectors can only view inspections they're assigned to (lead or in inspectors list)
        if ($user->hasRole('inspector')) {
            return $inspection->assigned_inspector_id === $user->id
                || $inspection->scheduled_by === $user->id
                || $inspection->inspectors()->where('user_id', $user->id)->exists();
        }

        return true;
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('inspections.create');
    }

    public function update(User $user, Inspection $inspection): bool
    {
        if (! $user->hasPermissionTo('inspections.edit')) {
            return false;
        }

        // Completed inspections cannot be edited
        if ($inspection->isCompleted()) {
            return false;
        }

        if ($user->hasRole('super_admin')) {
            return true;
        }

        if (! $user->hasAccessToCompany($inspection->company_id)) {
            return false;
        }

        // Inspectors can only edit inspections they're assigned to, and only when in_progress
        if ($user->hasRole('inspector')) {
            if (! $inspection->isInProgress()) {
                return false;
            }

            return $inspection->assigned_inspector_id === $user->id
                || $inspection->scheduled_by === $user->id
                || $inspection->inspectors()->where('user_id', $user->id)->exists();
        }

        return true;
    }

    /**
     * Can the user access the factory-floor capture UI?
     * Same logic as update — must be in_progress and user must have edit permission.
     */
    public function capture(User $user, Inspection $inspection): bool
    {
        return $this->update($user, $inspection);
    }

    /**
     * Can the user start this inspection? (pending -> in_progress)
     */
    public function start(User $user, Inspection $inspection): bool
    {
        if (! $inspection->isPending()) {
            return false;
        }

        if ($user->hasRole('super_admin')) {
            return true;
        }

        if (! $user->hasAccessToCompany($inspection->company_id)) {
            return false;
        }

        // Inspector can start if they're one of the assigned inspectors
        if ($user->hasRole('inspector')) {
            return $inspection->assigned_inspector_id === $user->id
                || $inspection->inspectors()->where('user_id', $user->id)->exists();
        }

        // Admin/supervisor can also start
        return $user->hasPermissionTo('inspections.edit');
    }

    /**
     * Can the user complete this inspection? (in_progress -> completed)
     * Only admin or supervisor — NOT inspector.
     */
    public function complete(User $user, Inspection $inspection): bool
    {
        if (! $user->hasPermissionTo('inspections.complete')) {
            return false;
        }

        // Can only complete in_progress inspections
        if (! $inspection->isInProgress()) {
            return false;
        }

        if ($user->hasRole('super_admin')) {
            return true;
        }

        if ($user->company_id !== $inspection->company_id) {
            return false;
        }

        // Inspectors explicitly CANNOT complete
        if ($user->hasRole('inspector')) {
            return false;
        }

        return true;
    }

    public function delete(User $user, Inspection $inspection): bool
    {
        if (! $user->hasPermissionTo('inspections.delete')) {
            return false;
        }

        // Cannot delete completed inspections
        if ($inspection->isCompleted()) {
            return false;
        }

        if ($user->hasRole('super_admin')) {
            return true;
        }

        return $user->hasAccessToCompany($inspection->company_id);
    }

    public function export(User $user, Inspection $inspection): bool
    {
        if (! ($user->hasPermissionTo('exports.pdf') || $user->hasPermissionTo('exports.csv'))) {
            return false;
        }

        if ($user->hasRole('super_admin')) {
            return true;
        }

        return $user->hasAccessToCompany($inspection->company_id);
    }
}
