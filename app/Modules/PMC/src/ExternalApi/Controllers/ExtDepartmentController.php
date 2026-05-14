<?php

namespace App\Modules\PMC\ExternalApi\Controllers;

use App\Common\Controllers\BaseController;
use App\Modules\PMC\Department\Contracts\DepartmentServiceInterface;
use App\Modules\PMC\Department\Requests\CreateDepartmentRequest;
use App\Modules\PMC\Department\Requests\ListDepartmentRequest;
use App\Modules\PMC\Department\Requests\UpdateDepartmentRequest;
use App\Modules\PMC\Department\Resources\DepartmentResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/**
 * @tags External API - Departments
 */
class ExtDepartmentController extends BaseController implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('scope:departments:read', only: ['index', 'show']),
            new Middleware('scope:departments:write', only: ['store', 'update', 'destroy']),
        ];
    }

    public function __construct(
        protected DepartmentServiceInterface $service,
    ) {}

    public function index(ListDepartmentRequest $request): AnonymousResourceCollection
    {
        $filters = array_merge($request->validated(), [
            'project_id' => $request->attributes->get('api_project_id'),
        ]);

        return DepartmentResource::collection($this->service->list($filters))
            ->additional(['success' => true]);
    }

    public function show(int $id): DepartmentResource
    {
        return new DepartmentResource($this->service->findById($id));
    }

    public function store(CreateDepartmentRequest $request): JsonResponse
    {
        $data = array_merge($request->validated(), [
            'project_id' => $request->attributes->get('api_project_id'),
        ]);

        return (new DepartmentResource($this->service->create($data)))
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateDepartmentRequest $request, int $id): DepartmentResource
    {
        return new DepartmentResource($this->service->update($id, $request->validated()));
    }

    public function destroy(int $id): JsonResponse
    {
        $this->service->delete($id);

        return response()->json(['success' => true, 'message' => 'Đã xóa phòng ban.']);
    }
}
