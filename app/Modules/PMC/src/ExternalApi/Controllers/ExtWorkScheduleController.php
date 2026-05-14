<?php

namespace App\Modules\PMC\ExternalApi\Controllers;

use App\Common\Controllers\BaseController;
use App\Modules\PMC\ExternalApi\Requests\ExtListWorkScheduleRequest;
use App\Modules\PMC\WorkSchedule\Contracts\WorkScheduleServiceInterface;
use App\Modules\PMC\WorkSchedule\Requests\BulkUpsertWorkScheduleRequest;
use App\Modules\PMC\WorkSchedule\Requests\UpsertWorkScheduleRequest;
use App\Modules\PMC\WorkSchedule\Resources\WorkScheduleResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/**
 * @tags External API - Work Schedules
 */
class ExtWorkScheduleController extends BaseController implements HasMiddleware
{
    public function __construct(
        protected WorkScheduleServiceInterface $service,
    ) {}

    /**
     * @return list<Middleware>
     */
    public static function middleware(): array
    {
        return [
            new Middleware('scope:work-schedules:read', only: ['index', 'show']),
            new Middleware('scope:work-schedules:write', only: ['store', 'update', 'destroy', 'bulkUpsert']),
        ];
    }

    public function index(ExtListWorkScheduleRequest $request): AnonymousResourceCollection
    {
        $filters = array_merge($request->validated(), [
            'project_id' => (int) $request->attributes->get('api_project_id'),
        ]);

        return WorkScheduleResource::collection($this->service->list($filters))
            ->additional(['success' => true]);
    }

    public function show(Request $request, int $id): WorkScheduleResource
    {
        $apiProjectId = (int) $request->attributes->get('api_project_id');

        return new WorkScheduleResource($this->service->findByIdForApiProject($id, $apiProjectId));
    }

    public function store(UpsertWorkScheduleRequest $request): JsonResponse
    {
        $apiProjectId = (int) $request->attributes->get('api_project_id');
        $schedule = $this->service->create($request->validated(), $apiProjectId);

        return (new WorkScheduleResource($schedule->load(['account', 'project', 'shift'])))
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpsertWorkScheduleRequest $request, int $id): WorkScheduleResource
    {
        $apiProjectId = (int) $request->attributes->get('api_project_id');
        $schedule = $this->service->update($id, $request->validated(), $apiProjectId);

        return new WorkScheduleResource($schedule->load(['account', 'project', 'shift']));
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $apiProjectId = (int) $request->attributes->get('api_project_id');
        $this->service->delete($id, $apiProjectId);

        return response()->json(['success' => true, 'message' => 'Đã xóa ca làm việc.']);
    }

    public function bulkUpsert(BulkUpsertWorkScheduleRequest $request): JsonResponse
    {
        $apiProjectId = (int) $request->attributes->get('api_project_id');
        $stats = $this->service->bulkUpsert($request->validated()['items'], $apiProjectId);

        return response()->json([
            'success' => true,
            'message' => 'Bulk upsert hoàn tất.',
            'data' => $stats,
        ]);
    }
}
