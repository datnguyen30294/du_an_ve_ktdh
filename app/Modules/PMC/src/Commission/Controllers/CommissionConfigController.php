<?php

namespace App\Modules\PMC\Commission\Controllers;

use App\Common\Controllers\BaseController;
use App\Modules\PMC\Commission\Contracts\CommissionConfigServiceInterface;
use App\Modules\PMC\Commission\Requests\ListCommissionProjectRequest;
use App\Modules\PMC\Commission\Requests\SaveCommissionAdjusterRequest;
use App\Modules\PMC\Commission\Requests\SaveCommissionConfigRequest;
use App\Modules\PMC\Commission\Resources\CommissionAdjusterResource;
use App\Modules\PMC\Commission\Resources\CommissionConfigDetailResource;
use App\Modules\PMC\Commission\Resources\CommissionProjectListResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/**
 * @tags Commission
 */
class CommissionConfigController extends BaseController implements HasMiddleware
{
    public function __construct(
        protected CommissionConfigServiceInterface $service,
    ) {}

    /**
     * @return list<Middleware>
     */
    public static function middleware(): array
    {
        return [
            new Middleware('permission:commission.view', only: ['listProjects', 'showConfig', 'getAdjusters', 'availableDepartments']),
            new Middleware('permission:commission.store', only: ['saveConfig', 'saveAdjusters']),
        ];
    }

    /**
     * List projects with commission config status.
     */
    public function listProjects(ListCommissionProjectRequest $request): AnonymousResourceCollection
    {
        $paginator = $this->service->listProjects($request->validated());

        return CommissionProjectListResource::collection($paginator)->additional(['success' => true]);
    }

    /**
     * Get commission config detail for a project.
     */
    public function showConfig(int $projectId): CommissionConfigDetailResource
    {
        $detail = $this->service->getConfigDetail($projectId);

        return new CommissionConfigDetailResource($detail['project'], $detail['config'], $detail['adjusters']);
    }

    /**
     * Save (upsert) commission config for a project.
     */
    public function saveConfig(SaveCommissionConfigRequest $request, int $projectId): CommissionConfigDetailResource
    {
        $this->service->saveConfig($projectId, $request->validated());
        $detail = $this->service->getConfigDetail($projectId);

        return new CommissionConfigDetailResource($detail['project'], $detail['config'], $detail['adjusters']);
    }

    /**
     * Get adjusters for a project.
     */
    public function getAdjusters(int $projectId): JsonResponse
    {
        $adjusters = $this->service->getAdjusters($projectId);

        return response()->json([
            'success' => true,
            'data' => CommissionAdjusterResource::collection($adjusters),
        ]);
    }

    /**
     * Save (sync) adjusters for a project.
     */
    public function saveAdjusters(SaveCommissionAdjusterRequest $request, int $projectId): JsonResponse
    {
        $adjusters = $this->service->saveAdjusters($projectId, $request->validated()['account_ids']);

        return response()->json([
            'success' => true,
            'data' => CommissionAdjusterResource::collection($adjusters),
        ]);
    }

    /**
     * Get available departments with accounts for a project.
     *
     * @return JsonResponse<array{
     *     success: bool,
     *     data: list<array{
     *         id: int,
     *         name: string,
     *         accounts: list<array{id: int, name: string, employee_code: string|null}>,
     *     }>,
     * }>
     */
    public function availableDepartments(int $projectId): JsonResponse
    {
        $departments = $this->service->getAvailableDepartments($projectId);

        return response()->json([
            'success' => true,
            'data' => $departments,
        ]);
    }
}
