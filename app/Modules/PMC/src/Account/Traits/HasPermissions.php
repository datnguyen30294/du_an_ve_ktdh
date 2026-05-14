<?php

namespace App\Modules\PMC\Account\Traits;

use Illuminate\Support\Collection;

trait HasPermissions
{
    /**
     * Get all permissions for this account through its role.
     *
     * @return Collection<int, \App\Modules\PMC\Account\Models\Permission>
     */
    public function getPermissions(): Collection
    {
        if (! $this->relationLoaded('role')) {
            $this->load('role.permissions');
        } elseif ($this->role && ! $this->role->relationLoaded('permissions')) {
            $this->role->load('permissions');
        }

        if ($this->role && ! $this->role->is_active) {
            return collect();
        }

        return $this->role?->permissions ?? collect();
    }

    /**
     * Get flat list of permission name strings.
     *
     * @return list<string>
     */
    public function getPermissionNames(): array
    {
        return $this->getPermissions()->pluck('name')->toArray();
    }

    /**
     * Check if this account has a specific permission.
     */
    public function hasPermission(string $permissionName): bool
    {
        return $this->getPermissions()->contains('name', $permissionName);
    }

    /**
     * Check if this account has any of the given permissions.
     *
     * @param  list<string>  $permissionNames
     */
    public function hasAnyPermission(array $permissionNames): bool
    {
        $permissions = $this->getPermissions()->pluck('name');

        foreach ($permissionNames as $name) {
            if ($permissions->contains($name)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if this account has all of the given permissions.
     *
     * @param  list<string>  $permissionNames
     */
    public function hasAllPermissions(array $permissionNames): bool
    {
        $permissions = $this->getPermissions()->pluck('name');

        foreach ($permissionNames as $name) {
            if (! $permissions->contains($name)) {
                return false;
            }
        }

        return true;
    }
}
