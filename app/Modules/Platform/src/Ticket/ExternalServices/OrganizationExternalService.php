<?php

namespace App\Modules\Platform\Ticket\ExternalServices;

use App\Modules\Platform\Tenant\Models\Organization;

class OrganizationExternalService implements OrganizationExternalServiceInterface
{
    /**
     * @return array{id: string, name: string}|null
     */
    public function getOrganizationById(string $id): ?array
    {
        return Organization::find($id)?->only(['id', 'name']);
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    public function listActiveOrganizations(): array
    {
        return Organization::active()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Organization $org) => ['id' => $org->id, 'name' => $org->name])
            ->values()
            ->all();
    }

    public function lookupOrgAndProject(string $orgId, ?int $projectId = null): array
    {
        $tenant = Organization::find($orgId);

        if (! $tenant) {
            return ['org_name' => null, 'project_name' => null];
        }

        $projectName = null;
        if ($projectId) {
            $projectName = $tenant->run(function () use ($projectId): ?string {
                return \App\Modules\PMC\Project\Models\Project::find($projectId)?->name;
            });
        }

        return ['org_name' => $tenant->name, 'project_name' => $projectName];
    }
}
