<?php

namespace App\Modules\PMC\ExternalApi\Controllers;

use App\Common\Controllers\BaseController;
use App\Modules\PMC\Project\Contracts\ProjectServiceInterface;
use App\Modules\PMC\Project\Requests\CreateProjectRequest;
use App\Modules\PMC\Project\Requests\ListProjectRequest;
use App\Modules\PMC\Project\Requests\UpdateProjectRequest;
use App\Modules\PMC\Project\Resources\ProjectResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/**
 * @tags External API - Projects
 */
class ExtProjectController extends BaseController implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('scope:projects:read', only: ['index', 'show']),
            new Middleware('scope:projects:write', only: ['store', 'update', 'destroy']),
        ];
    }

    public function __construct(
        protected ProjectServiceInterface $service,
    ) {}

    public function index(ListProjectRequest $request): AnonymousResourceCollection
    {
        return ProjectResource::collection($this->service->list($request->validated()))
            ->additional(['success' => true]);
    }

    public function show(int $id): ProjectResource
    {
        return new ProjectResource($this->service->findById($id));
    }

    public function store(CreateProjectRequest $request): JsonResponse
    {
        return (new ProjectResource($this->service->create($request->validated())))
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateProjectRequest $request, int $id): ProjectResource
    {
        return new ProjectResource($this->service->update($id, $request->validated()));
    }

    public function destroy(int $id): JsonResponse
    {
        $this->service->delete($id);

        return response()->json(['success' => true, 'message' => 'Đã xóa dự án.']);
    }
}
