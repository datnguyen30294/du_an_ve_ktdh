<?php

namespace App\Modules\PMC\WorkSchedule\Controllers;

use App\Common\Controllers\BaseController;
use App\Modules\PMC\WorkSchedule\Contracts\WorkScheduleServiceInterface;
use App\Modules\PMC\WorkSchedule\Requests\ListWorkScheduleRequest;
use App\Modules\PMC\WorkSchedule\Resources\WorkScheduleResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/**
 * @tags Work Schedules
 */
class WorkScheduleController extends BaseController implements HasMiddleware
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
            new Middleware('permission:work-schedules.view', only: ['index', 'show']),
        ];
    }

    /**
     * List work schedules with optional filters.
     */
    public function index(ListWorkScheduleRequest $request): AnonymousResourceCollection
    {
        $paginator = $this->service->list($request->validated());

        return WorkScheduleResource::collection($paginator)->additional(['success' => true]);
    }

    /**
     * Get a work schedule by ID.
     */
    public function show(int $id): WorkScheduleResource
    {
        return new WorkScheduleResource($this->service->findById($id));
    }
}
