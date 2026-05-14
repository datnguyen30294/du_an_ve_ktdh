<?php

namespace App\Modules\PMC\Catalog\Controllers;

use App\Common\Controllers\BaseController;
use App\Modules\PMC\Catalog\Contracts\CatalogSupplierServiceInterface;
use App\Modules\PMC\Catalog\Requests\CreateCatalogSupplierRequest;
use App\Modules\PMC\Catalog\Requests\ListCatalogSupplierRequest;
use App\Modules\PMC\Catalog\Requests\UpdateCatalogSupplierRequest;
use App\Modules\PMC\Catalog\Resources\CatalogSupplierResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Symfony\Component\HttpFoundation\Response;

/**
 * @tags Catalog Suppliers
 */
class CatalogSupplierController extends BaseController implements HasMiddleware
{
    public function __construct(
        protected CatalogSupplierServiceInterface $service,
    ) {}

    /**
     * @return list<Middleware>
     */
    public static function middleware(): array
    {
        return [
            new Middleware('permission:catalog-suppliers.view', only: ['index', 'show']),
            new Middleware('permission:catalog-suppliers.store', only: ['store']),
            new Middleware('permission:catalog-suppliers.update', only: ['update']),
            new Middleware('permission:catalog-suppliers.destroy', only: ['destroy', 'checkDelete']),
        ];
    }

    /**
     * List all catalog suppliers.
     */
    public function index(ListCatalogSupplierRequest $request): AnonymousResourceCollection
    {
        $paginator = $this->service->list($request->validated());

        return CatalogSupplierResource::collection($paginator)->additional(['success' => true]);
    }

    /**
     * Get a catalog supplier by ID.
     */
    public function show(int $id): CatalogSupplierResource
    {
        return new CatalogSupplierResource($this->service->findById($id));
    }

    /**
     * Create a new catalog supplier.
     */
    public function store(CreateCatalogSupplierRequest $request): JsonResponse
    {
        return (new CatalogSupplierResource($this->service->create($request->validated())))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Update an existing catalog supplier.
     */
    public function update(UpdateCatalogSupplierRequest $request, int $id): CatalogSupplierResource
    {
        return new CatalogSupplierResource($this->service->update($id, $request->validated()));
    }

    /**
     * Check if a catalog supplier can be deleted.
     */
    public function checkDelete(int $id): JsonResponse
    {
        return response()->json($this->service->checkDelete($id));
    }

    /**
     * Delete a catalog supplier.
     */
    public function destroy(int $id): JsonResponse
    {
        $this->service->delete($id);

        return response()->json(['success' => true, 'message' => 'Xoá thành công.']);
    }
}
