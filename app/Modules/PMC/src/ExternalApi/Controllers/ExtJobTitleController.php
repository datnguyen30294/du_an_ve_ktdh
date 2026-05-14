<?php

namespace App\Modules\PMC\ExternalApi\Controllers;

use App\Common\Controllers\BaseController;
use App\Modules\PMC\JobTitle\Contracts\JobTitleServiceInterface;
use App\Modules\PMC\JobTitle\Requests\CreateJobTitleRequest;
use App\Modules\PMC\JobTitle\Requests\ListJobTitleRequest;
use App\Modules\PMC\JobTitle\Requests\UpdateJobTitleRequest;
use App\Modules\PMC\JobTitle\Resources\JobTitleResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/**
 * @tags External API - Job Titles
 */
class ExtJobTitleController extends BaseController implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('scope:job_titles:read', only: ['index', 'show']),
            new Middleware('scope:job_titles:write', only: ['store', 'update', 'destroy']),
        ];
    }

    public function __construct(
        protected JobTitleServiceInterface $service,
    ) {}

    public function index(ListJobTitleRequest $request): AnonymousResourceCollection
    {
        $filters = array_merge($request->validated(), [
            'project_id' => $request->attributes->get('api_project_id'),
        ]);

        return JobTitleResource::collection($this->service->list($filters))
            ->additional(['success' => true]);
    }

    public function show(int $id): JobTitleResource
    {
        return new JobTitleResource($this->service->findById($id));
    }

    public function store(CreateJobTitleRequest $request): JsonResponse
    {
        $data = array_merge($request->validated(), [
            'project_id' => $request->attributes->get('api_project_id'),
        ]);

        return (new JobTitleResource($this->service->create($data)))
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateJobTitleRequest $request, int $id): JobTitleResource
    {
        return new JobTitleResource($this->service->update($id, $request->validated()));
    }

    public function destroy(int $id): JsonResponse
    {
        $this->service->delete($id);

        return response()->json(['success' => true, 'message' => 'Đã xóa chức danh.']);
    }
}
