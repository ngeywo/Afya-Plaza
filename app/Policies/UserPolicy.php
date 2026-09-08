<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    /**
     * Only super admins can manage users.
     */
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function view(User $user, User $model): bool
    {
        return $user->isSuperAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function update(User $user, User $model): bool
    {
        // Super admin cannot modify themselves without additional protection
        if ($model->id === $user->id) {
            return false;
        }

        // Cannot modify the system's primary super admin
        if ($model->hasRole('super-admin') && $model->id === 1) {
            return false;
        }

        return $user->isSuperAdmin();
    }

    public function delete(User $user, User $model): bool
    {
        // Cannot delete yourself
        if ($model->id === $user->id) {
            return false;
        }

        // Cannot delete the primary super admin
        if ($model->hasRole('super-admin') && $model->id === 1) {
            return false;
        }

        return $user->isSuperAdmin();
    }

    public function manageRoles(User $user, User $model): bool
    {
        // Cannot modify own roles
        if ($model->id === $user->id) {
            return false;
        }

        // Cannot modify the primary super admin
        if ($model->hasRole('super-admin') && $model->id === 1) {
            return false;
        }

        return $user->isSuperAdmin();
    }

    public function changeStatus(User $user, User $model): bool
    {
        // Cannot change your own status
        if ($model->id === $user->id) {
            return false;
        }

        // Cannot suspend/activate the primary super admin
        if ($model->hasRole('super-admin') && $model->id === 1) {
            return false;
        }

        return $user->isSuperAdmin();
    }
}
