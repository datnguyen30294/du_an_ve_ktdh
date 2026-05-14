<?php

namespace App\Modules\Platform\ExternalApi\Controllers;

use App\Common\Controllers\BaseController;
use App\Modules\Platform\Tenant\Models\Organization;
use App\Modules\PMC\Project\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @tags API Client Lookups
 */
class OrganizationLookupController extends BaseController
{
    /**
     * List active organizations for select dropdown.
     */
    public function organizations(Request $request): JsonResponse
    {
        $query = Organization::query()
            ->where('is_active', true)
            ->orderBy('name');

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search): void {
                $q->where('id', 'ilike', "%{$search}%")
                    ->orWhere('name', 'ilike', "%{$search}%");
            });
        }

        $organizations = $query->get(['id', 'name'])
            ->map(fn (Organization $org) => ['id' => $org->id, 'name' => $org->name])
            ->values();

        return response()->json(['success' => true, 'data' => $organizations]);
    }

    /**
     * List projects for a specific organization (queries tenant DB).
     */
    public function projects(Request $request, string $orgId): JsonResponse
    {
        $tenant = Organization::find($orgId);

        if (! $tenant) {
            return response()->json(['success' => true, 'data' => []]);
        }

        $search = $request->query('search');

        $projects = $tenant->run(function () use ($search) {
            $query = Project::query()->orderBy('name');

            if ($search) {
                $query->where(function ($q) use ($search): void {
                    $q->where('code', 'ilike', "%{$search}%")
                        ->orWhere('name', 'ilike', "%{$search}%");
                });
            }

            return $query->get(['id', 'code', 'name'])
                ->map(fn (Project $p) => ['id' => $p->id, 'name' => "{$p->code} - {$p->name}"])
                ->values();
        });

        return response()->json(['success' => true, 'data' => $projects]);
    }
}
