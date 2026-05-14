<?php

namespace App\Modules\PMC\ExternalApi\Controllers;

use App\Common\Controllers\BaseController;
use App\Modules\PMC\ExternalApi\Requests\ExtCreateShiftRequest;
use App\Modules\PMC\ExternalApi\Requests\ExtUpdateShiftRequest;
use App\Modules\PMC\Shift\Contracts\ShiftServiceInterface;
use App\Modules\PMC\Shift\Enums\ShiftStatusEnum;
use App\Modules\PMC\Shift\Models\Shift;
use App\Modules\PMC\Shift\Resources\ShiftResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Symfony\Component\HttpFoundation\Response;

/**
 * @tags External API - Shifts
 */
class ExtShiftController extends BaseController implements HasMiddleware
{
    /**
     * @return list<Middleware>
     */
    public static function middleware(): array
    {
        return [
            new Middleware('scope:shifts:read', only: ['index', 'show']),
            new Middleware('scope:shifts:write', only: ['store', 'update', 'destroy']),
        ];
    }

    public function __construct(
        protected ShiftServiceInterface $service,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $apiProjectId = (int) $request->attributes->get('api_project_id');

        $shifts = $this->service->allForProject($apiProjectId)
            ->filter(fn (Shift $s) => $s->status === ShiftStatusEnum::Active)
            ->values();

        return ShiftResource::collection($shifts)
            ->additional(['success' => true]);
    }

    public function show(Request $request, int $id): ShiftResource
    {
        $apiProjectId = (int) $request->attributes->get('api_project_id');

        return new ShiftResource($this->service->findByIdForApiProject($id, $apiProjectId));
    }

    public function store(ExtCreateShiftRequest $request): JsonResponse
    {
        $apiProjectId = (int) $request->attributes->get('api_project_id');

        $data = $request->validated();
        $data['project_id'] = $apiProjectId;

        return (new ShiftResource($this->service->create($data)))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function update(ExtUpdateShiftRequest $request, int $id): ShiftResource
    {
        $apiProjectId = (int) $request->attributes->get('api_project_id');
        $this->service->findByIdForApiProject($id, $apiProjectId);

        return new ShiftResource($this->service->update($id, $request->validated()));
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $apiProjectId = (int) $request->attributes->get('api_project_id');
        $this->service->findByIdForApiProject($id, $apiProjectId);
        $this->service->delete($id);

        return response()->json(['success' => true, 'message' => 'Đã xóa ca làm việc.']);
    }
}
