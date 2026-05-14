<?php

namespace App\Modules\PMC\WorkforceCapacity\Controllers;

use App\Common\Controllers\BaseController;
use App\Modules\PMC\WorkforceCapacity\Contracts\WorkforceCapacityServiceInterface;
use App\Modules\PMC\WorkforceCapacity\Requests\ListWorkforceCapacityRequest;
use App\Modules\PMC\WorkforceCapacity\Resources\WorkforceCapacityRowResource;
use App\Modules\PMC\WorkforceCapacity\Resources\WorkforceCapacitySummaryResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/**
 * @tags Workforce Capacity
 */
class WorkforceCapacityController extends BaseController implements HasMiddleware
{
    public function __construct(
        protected WorkforceCapacityServiceInterface $service,
    ) {}

    /**
     * @return list<Middleware>
     */
    public static function middleware(): array
    {
        return [
            new Middleware('permission:workforce-capacity.view'),
        ];
    }

    /**
     * Năng lực nhân sự — tổng hợp tải việc và điểm đánh giá theo nhân sự.
     */
    public function index(ListWorkforceCapacityRequest $request): JsonResponse
    {
        $projectId = $request->validated('project_id');
        $search = $request->validated('search');

        $data = $this->service->getCapacity(
            $projectId !== null ? (int) $projectId : null,
            $search !== null ? (string) $search : null,
        );

        return response()->json([
            'success' => true,
            'data' => [
                'summary' => (new WorkforceCapacitySummaryResource($data['summary']))->toArray($request),
                'rows' => WorkforceCapacityRowResource::collection($data['rows'])->toArray($request),
            ],
        ]);
    }
}
