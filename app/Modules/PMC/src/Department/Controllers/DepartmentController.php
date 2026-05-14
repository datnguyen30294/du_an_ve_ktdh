<?php

namespace App\Modules\PMC\Department\Controllers;

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
use Symfony\Component\HttpFoundation\Response;

/**
 * @tags Departments
 */
class DepartmentController extends BaseController implements HasMiddleware
{
    public function __construct(
        protected DepartmentServiceInterface $service,
    ) {}

    /**
     * @return list<Middleware>
     */
    public static function middleware(): array
    {
        return [
            new Middleware('permission:departments.view', only: ['index', 'show', 'descendantIds']),
            new Middleware('permission:departments.store', only: ['store']),
            new Middleware('permission:departments.update', only: ['update']),
            new Middleware('permission:departments.destroy', only: ['destroy']),
        ];
    }

    /**
     * List all departments.
     */
    public function index(ListDepartmentRequest $request): AnonymousResourceCollection
    {
        $paginator = $this->service->list($request->validated());

        return DepartmentResource::collection($paginator)->additional(['success' => true]);
    }

    /**
     * Get a department by ID.
     */
    public function show(int $id): DepartmentResource
    {
        return new DepartmentResource($this->service->findById($id));
    }

    /**
     * Create a new department.
     */
    public function store(CreateDepartmentRequest $request): JsonResponse
    {
        return (new DepartmentResource($this->service->create($request->validated())))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Update an existing department.
     */
    public function update(UpdateDepartmentRequest $request, int $id): DepartmentResource
    {
        return new DepartmentResource($this->service->update($id, $request->validated()));
    }

    /**
     * Get all descendant IDs of a department.
     *
     * @return array{data: list<int>}
     */
    public function descendantIds(int $id): JsonResponse
    {
        return response()->json([
            'data' => $this->service->getDescendantIds($id),
        ]);
    }

    /**
     * Delete a department.
     */
    public function destroy(int $id): JsonResponse
    {
        $this->service->delete($id);

        return response()->json(['success' => true, 'message' => 'Xoá thành công.']);
    }
}
