<?php

namespace App\Modules\Platform\ExternalApi\Resources;

use App\Common\Resources\BaseResource;
use App\Modules\Platform\ExternalApi\Models\ApiClient;
use App\Modules\Platform\Tenant\Models\Organization;
use App\Modules\PMC\Project\Models\Project;
use Illuminate\Http\Request;

/**
 * @mixin ApiClient
 */
class ApiClientResource extends BaseResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            /** @var string */
            'id' => $this->id,
            /** @var string */
            'organization_id' => $this->organization_id,
            /** @var string|null */
            'organization_name' => $this->resolveOrganizationName(),
            /** @var int */
            'project_id' => $this->project_id,
            /** @var string|null */
            'project_name' => $this->resolveProjectName(),
            /** @var string */
            'name' => $this->name,
            /** @var string */
            'client_key' => $this->client_key,
            /** @var array<string> */
            'scopes' => $this->scopes,
            /** @var bool */
            'is_active' => $this->is_active,
            /** @var string|null */
            'last_used_at' => $this->last_used_at?->toIso8601String(),
            /** @var string */
            'created_at' => $this->created_at?->toIso8601String(),
            /** @var string */
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    private function resolveOrganizationName(): ?string
    {
        return Organization::find($this->organization_id)?->name;
    }

    private function resolveProjectName(): ?string
    {
        $tenant = Organization::find($this->organization_id);

        if (! $tenant) {
            return null;
        }

        return $tenant->run(fn () => Project::find($this->project_id)?->name);
    }
}
