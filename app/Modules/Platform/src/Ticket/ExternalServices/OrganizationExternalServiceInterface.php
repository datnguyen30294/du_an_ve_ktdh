<?php

namespace App\Modules\Platform\Ticket\ExternalServices;

interface OrganizationExternalServiceInterface
{
    /**
     * @return array{id: string, name: string}|null
     */
    public function getOrganizationById(string $id): ?array;

    /**
     * @return list<array{id: int, name: string}>
     */
    public function listActiveOrganizations(): array;

    /**
     * Lookup org name and project name in one call.
     *
     * @return array{org_name: string|null, project_name: string|null}
     */
    public function lookupOrgAndProject(string $orgId, ?int $projectId = null): array;
}
