<?php

namespace App\Modules\PMC\Catalog\Controllers;

use App\Common\Controllers\BaseController;
use App\Modules\PMC\Catalog\Contracts\CatalogItemServiceInterface;
use App\Modules\PMC\Catalog\Requests\CreateCatalogItemRequest;
use App\Modules\PMC\Catalog\Requests\ListCatalogItemRequest;
use App\Modules\PMC\Catalog\Requests\UpdateCatalogItemRequest;
use App\Modules\PMC\Catalog\Requests\UploadCatalogItemGalleryRequest;
use App\Modules\PMC\Catalog\Requests\UploadCatalogItemImageRequest;
use App\Modules\PMC\Catalog\Resources\CatalogItemResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Symfony\Component\HttpFoundation\Response;

/**
 * @tags Catalog Items
 */
class CatalogItemController extends BaseController implements HasMiddleware
{
    public function __construct(
        protected CatalogItemServiceInterface $service,
    ) {}

    /**
     * @return list<Middleware>
     */
    public static function middleware(): array
    {
        return [
            new Middleware('permission:catalog-items.view', only: ['index', 'show']),
            new Middleware('permission:catalog-items.store', only: ['store']),
            new Middleware('permission:catalog-items.update', only: ['update', 'uploadImage', 'deleteImage', 'uploadGallery', 'deleteGalleryImage']),
            new Middleware('permission:catalog-items.destroy', only: ['destroy']),
        ];
    }

    /**
     * List all catalog items.
     */
    public function index(ListCatalogItemRequest $request): AnonymousResourceCollection
    {
        $paginator = $this->service->list($request->validated());

        return CatalogItemResource::collection($paginator)->additional(['success' => true]);
    }

    /**
     * Get a catalog item by ID.
     */
    public function show(int $id): CatalogItemResource
    {
        return new CatalogItemResource($this->service->findById($id));
    }

    /**
     * Create a new catalog item.
     */
    public function store(CreateCatalogItemRequest $request): JsonResponse
    {
        return (new CatalogItemResource($this->service->create($request->validated())))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Update an existing catalog item.
     */
    public function update(UpdateCatalogItemRequest $request, int $id): CatalogItemResource
    {
        return new CatalogItemResource($this->service->update($id, $request->validated()));
    }

    /**
     * Upload image for a catalog item.
     */
    public function uploadImage(UploadCatalogItemImageRequest $request, int $id): CatalogItemResource
    {
        return new CatalogItemResource($this->service->uploadImage($id, $request->file('image')));
    }

    /**
     * Delete image of a catalog item.
     */
    public function deleteImage(int $id): CatalogItemResource
    {
        return new CatalogItemResource($this->service->deleteImage($id));
    }

    /**
     * Upload gallery images for a catalog item.
     */
    public function uploadGallery(UploadCatalogItemGalleryRequest $request, int $id): CatalogItemResource
    {
        /** @var array<\Illuminate\Http\UploadedFile> $files */
        $files = $request->file('images');

        return new CatalogItemResource($this->service->uploadGalleryImages($id, $files));
    }

    /**
     * Delete a gallery image.
     */
    public function deleteGalleryImage(int $id, int $imageId): CatalogItemResource
    {
        return new CatalogItemResource($this->service->deleteGalleryImage($id, $imageId));
    }

    /**
     * Delete a catalog item.
     */
    public function destroy(int $id): JsonResponse
    {
        $this->service->delete($id);

        return response()->json(['success' => true, 'message' => 'Xoá thành công.']);
    }
}
