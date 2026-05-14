<?php

namespace App\Modules\PMC\Account\Controllers;

use App\Common\Controllers\BaseController;
use App\Modules\PMC\Account\Contracts\RoleServiceInterface;
use App\Modules\PMC\Account\Requests\CreateRoleRequest;
use App\Modules\PMC\Account\Requests\ListRoleRequest;
use App\Modules\PMC\Account\Requests\UpdateRoleRequest;
use App\Modules\PMC\Account\Resources\RoleResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Symfony\Component\HttpFoundation\Response;

/**
 * @tags Roles
 */
class RoleController extends BaseController implements HasMiddleware
{
    public function __construct(
        protected RoleServiceInterface $service,
    ) {}

    /**
     * @return list<Middleware>
     */
    public static function middleware(): array
    {
        return [
            new Middleware('permission:roles.view', only: ['index', 'show']),
            new Middleware('permission:roles.store', only: ['store']),
            new Middleware('permission:roles.update', only: ['update']),
            new Middleware('permission:roles.destroy', only: ['destroy']),
        ];
    }

    /**
     * List all roles.
     */
    public function index(ListRoleRequest $request): AnonymousResourceCollection
    {
        $paginator = $this->service->list($request->validated());

        return RoleResource::collection($paginator)->additional(['success' => true]);
    }

    /**
     * Get a role by ID.
     */
    public function show(int $id): RoleResource
    {
        return new RoleResource($this->service->findById($id));
    }

    /**
     * Create a new role.
     */
    public function store(CreateRoleRequest $request): JsonResponse
    {
        return (new RoleResource($this->service->create($request->validated())))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Update an existing role.
     */
    public function update(UpdateRoleRequest $request, int $id): RoleResource
    {
        return new RoleResource($this->service->update($id, $request->validated()));
    }

    /**
     * Delete a role.
     */
    public function destroy(int $id): JsonResponse
    {
        $this->service->delete($id);

        return response()->json(['success' => true, 'message' => 'Xoá thành công.']);
    }
}
