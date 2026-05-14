<?php

namespace App\Modules\PMC\Shift\Controllers;

use App\Common\Controllers\BaseController;
use App\Modules\PMC\Shift\Contracts\ShiftServiceInterface;
use App\Modules\PMC\Shift\Requests\CreateShiftRequest;
use App\Modules\PMC\Shift\Requests\ListShiftRequest;
use App\Modules\PMC\Shift\Requests\UpdateShiftRequest;
use App\Modules\PMC\Shift\Resources\ShiftResource;
use App\Modules\PMC\Shift\Resources\ShiftStatsResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Symfony\Component\HttpFoundation\Response;

/**
 * @tags Shifts
 */
class ShiftController extends BaseController implements HasMiddleware
{
    public function __construct(
        protected ShiftServiceInterface $service,
    ) {}

    /**
     * @return list<Middleware>
     */
    public static function middleware(): array
    {
        return [
            new Middleware('permission:shifts.view', only: ['index', 'show', 'stats']),
            new Middleware('permission:shifts.store', only: ['store']),
            new Middleware('permission:shifts.update', only: ['update']),
            new Middleware('permission:shifts.destroy', only: ['destroy']),
        ];
    }

    /**
     * List all shifts (filter by status/type/work_group, search by code/name).
     */
    public function index(ListShiftRequest $request): AnonymousResourceCollection
    {
        $paginator = $this->service->list($request->validated());

        return ShiftResource::collection($paginator)->additional(['success' => true]);
    }

    /**
     * Get a shift by ID.
     */
    public function show(int $id): ShiftResource
    {
        return new ShiftResource($this->service->findById($id));
    }

    /**
     * Create a new shift.
     */
    public function store(CreateShiftRequest $request): JsonResponse
    {
        return (new ShiftResource($this->service->create($request->validated())))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Update an existing shift.
     */
    public function update(UpdateShiftRequest $request, int $id): ShiftResource
    {
        return new ShiftResource($this->service->update($id, $request->validated()));
    }

    /**
     * Delete a shift (blocked when at least one WorkSchedule references it).
     */
    public function destroy(int $id): JsonResponse
    {
        $this->service->delete($id);

        return response()->json(['success' => true, 'message' => 'Xoá thành công.']);
    }

    /**
     * Aggregate counts across all shifts (total, active, inactive).
     */
    public function stats(): ShiftStatsResource
    {
        return new ShiftStatsResource($this->service->getStats());
    }
}
