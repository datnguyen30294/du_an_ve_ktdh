<?php

namespace App\Modules\PMC\Catalog\Controllers;

use App\Common\Controllers\BaseController;
use App\Modules\PMC\Catalog\Contracts\ServiceCategoryServiceInterface;
use App\Modules\PMC\Catalog\Requests\CreateServiceCategoryRequest;
use App\Modules\PMC\Catalog\Requests\ListServiceCategoryRequest;
use App\Modules\PMC\Catalog\Requests\UpdateServiceCategoryRequest;
use App\Modules\PMC\Catalog\Requests\UploadServiceCategoryImageRequest;
use App\Modules\PMC\Catalog\Resources\ServiceCategoryResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Symfony\Component\HttpFoundation\Response;

/**
 * @tags Service Categories
 */
class ServiceCategoryController extends BaseController implements HasMiddleware
{
    public function __construct(
        protected ServiceCategoryServiceInterface $service,
    ) {}

    /**
     * @return list<Middleware>
     */
    public static function middleware(): array
    {
        return [
            new Middleware('permission:service-categories.view', only: ['index', 'show']),
            new Middleware('permission:service-categories.store', only: ['store']),
            new Middleware('permission:service-categories.update', only: ['update', 'uploadImage', 'deleteImage']),
            new Middleware('permission:service-categories.destroy', only: ['destroy', 'checkDelete']),
        ];
    }

    /**
     * List all service categories.
     */
    public function index(ListServiceCategoryRequest $request): AnonymousResourceCollection
    {
        $paginator = $this->service->list($request->validated());

        return ServiceCategoryResource::collection($paginator)->additional(['success' => true]);
    }

    /**
     * Get a service category by ID.
     */
    public function show(int $id): ServiceCategoryResource
    {
        return new ServiceCategoryResource($this->service->findById($id));
    }

    /**
     * Create a new service category.
     */
    public function store(CreateServiceCategoryRequest $request): JsonResponse
    {
        return (new ServiceCategoryResource($this->service->create($request->validated())))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Update an existing service category.
     */
    public function update(UpdateServiceCategoryRequest $request, int $id): ServiceCategoryResource
    {
        return new ServiceCategoryResource($this->service->update($id, $request->validated()));
    }

    /**
     * Check if a service category can be deleted.
     */
    public function checkDelete(int $id): JsonResponse
    {
        return response()->json($this->service->checkDelete($id));
    }

    /**
     * Upload image for a service category.
     */
    public function uploadImage(UploadServiceCategoryImageRequest $request, int $id): ServiceCategoryResource
    {
        return new ServiceCategoryResource($this->service->uploadImage($id, $request->file('image')));
    }

    /**
     * Delete image of a service category.
     */
    public function deleteImage(int $id): ServiceCategoryResource
    {
        return new ServiceCategoryResource($this->service->deleteImage($id));
    }

    /**
     * Delete a service category.
     */
    public function destroy(int $id): JsonResponse
    {
        $this->service->delete($id);

        return response()->json(['success' => true, 'message' => 'Xoá thành công.']);
    }
}
