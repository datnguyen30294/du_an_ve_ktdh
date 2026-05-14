<?php

namespace App\Modules\PMC\JobTitle\Controllers;

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
use Symfony\Component\HttpFoundation\Response;

/**
 * @tags Job Titles
 */
class JobTitleController extends BaseController implements HasMiddleware
{
    public function __construct(
        protected JobTitleServiceInterface $service,
    ) {}

    /**
     * @return list<Middleware>
     */
    public static function middleware(): array
    {
        return [
            new Middleware('permission:job-titles.view', only: ['index', 'show']),
            new Middleware('permission:job-titles.store', only: ['store']),
            new Middleware('permission:job-titles.update', only: ['update']),
            new Middleware('permission:job-titles.destroy', only: ['destroy', 'checkDelete']),
        ];
    }

    /**
     * List all job titles.
     */
    public function index(ListJobTitleRequest $request): AnonymousResourceCollection
    {
        $paginator = $this->service->list($request->validated());

        return JobTitleResource::collection($paginator)->additional(['success' => true]);
    }

    /**
     * Get a job title by ID.
     */
    public function show(int $id): JobTitleResource
    {
        return new JobTitleResource($this->service->findById($id));
    }

    /**
     * Create a new job title.
     */
    public function store(CreateJobTitleRequest $request): JsonResponse
    {
        return (new JobTitleResource($this->service->create($request->validated())))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Update an existing job title.
     */
    public function update(UpdateJobTitleRequest $request, int $id): JobTitleResource
    {
        return new JobTitleResource($this->service->update($id, $request->validated()));
    }

    /**
     * Check if a job title can be deleted.
     */
    public function checkDelete(int $id): JsonResponse
    {
        return response()->json($this->service->checkDelete($id));
    }

    /**
     * Delete a job title.
     */
    public function destroy(int $id): JsonResponse
    {
        $this->service->delete($id);

        return response()->json(['success' => true, 'message' => 'Xoá thành công.']);
    }
}
