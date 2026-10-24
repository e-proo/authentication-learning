<?php

namespace App\Services\Authorization;

use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

class OrganizationAccess
{
    /**
     * Organization-level endpoints require an organization-wide membership.
     * Branch-specific memberships will use branch-scoped endpoints instead.
     */
    public function requirePermission(
        User $user,
        Organization $organization,
        string $permission,
    ): Membership {
        if (! $organization->is_active) {
            throw new AuthorizationException('This organization is inactive.');
        }

        $membership = $user->memberships()
            ->where('organization_id', $organization->id)
            ->whereNull('branch_id')
            ->where('status', 'active')
            ->with('roles.permissions')
            ->first();

        if (! $membership) {
            throw new AuthorizationException('You do not have organization-wide access.');
        }

        $allowed = $membership->roles
            ->flatMap(fn ($role) => $role->permissions)
            ->contains('slug', $permission);

        if (! $allowed) {
            throw new AuthorizationException('You do not have the required permission.');
        }

        return $membership;
    }
}
