<?php

namespace App\Policies;

use App\Models\Role;
use App\Models\User;

class RolePolicy
{
    /**
     * Only super admins can manage roles.
     */
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function view(User $user, Role $role): bool
    {
        return $user->isSuperAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function update(User $user, Role $role): bool
    {
        // Protected roles cannot be modified
        if (in_array($role->slug, ['super-admin', 'system-owner'])) {
            return false;
        }

        return $user->isSuperAdmin();
    }

    public function delete(User $user, Role $role): bool
    {
        // Cannot delete protected roles
        if (in_array($role->slug, ['super-admin', 'system-owner'])) {
            return false;
        }

        // Cannot delete roles that have users
        if ($role->users()->exists()) {
            return false;
        }

        return $user->isSuperAdmin();
    }

    public function managePermissions(User $user, Role $role): bool
    {
        // Cannot modify permissions of protected roles
        if (in_array($role->slug, ['super-admin', 'system-owner'])) {
            return false;
        }

        return $user->isSuperAdmin();
    }
}
