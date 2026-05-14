<?php

namespace App\Modules\PMC\Project\Controllers;

use App\Common\Controllers\BaseController;
use App\Modules\PMC\Project\Contracts\ProjectServiceInterface;
use App\Modules\PMC\Project\Requests\CreateProjectRequest;
use App\Modules\PMC\Project\Requests\ListProjectRequest;
use App\Modules\PMC\Project\Requests\SyncMembersRequest;
use App\Modules\PMC\Project\Requests\UpdateProjectRequest;
use App\Modules\PMC\Project\Resources\ProjectResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Symfony\Component\HttpFoundation\Response;

/**
 * @tags Projects
 */
class ProjectController extends BaseController implements HasMiddleware
{
    public function __construct(
        protected ProjectServiceInterface $service,
    ) {}

    /**
     * @return list<Middleware>
     */
    public static function middleware(): array
    {
        return [
            new Middleware('permission:projects.view', only: ['index', 'show']),
            new Middleware('permission:projects.store', only: ['store']),
            new Middleware('permission:projects.update', only: ['update', 'syncMembers']),
            new Middleware('permission:projects.destroy', only: ['destroy']),
        ];
    }

    /**
     * List all projects.
     */
    public function index(ListProjectRequest $request): AnonymousResourceCollection
    {
        $paginator = $this->service->list($request->validated());

        return ProjectResource::collection($paginator)->additional(['success' => true]);
    }

    /**
     * Get a project by ID.
     */
    public function show(int $id): ProjectResource
    {
        return new ProjectResource($this->service->findById($id));
    }

    /**
     * Create a new project.
     */
    public function store(CreateProjectRequest $request): JsonResponse
    {
        return (new ProjectResource($this->service->create($request->validated())))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Update an existing project.
     */
    public function update(UpdateProjectRequest $request, int $id): ProjectResource
    {
        return new ProjectResource($this->service->update($id, $request->validated()));
    }

    /**
     * Sync project members.
     */
    public function syncMembers(SyncMembersRequest $request, int $id): ProjectResource
    {
        return new ProjectResource($this->service->syncMembers($id, $request->validated('account_ids')));
    }

    /**
     * Delete a project.
     */
    public function destroy(int $id): JsonResponse
    {
        $this->service->delete($id);

        return response()->json(['success' => true, 'message' => 'Xoá thành công.']);
    }
}
