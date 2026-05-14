<?php

namespace App\Modules\PMC\OgTicketCategory\Controllers;

use App\Common\Controllers\BaseController;
use App\Modules\PMC\OgTicketCategory\Contracts\OgTicketCategoryServiceInterface;
use App\Modules\PMC\OgTicketCategory\Requests\CreateOgTicketCategoryRequest;
use App\Modules\PMC\OgTicketCategory\Requests\ListOgTicketCategoryRequest;
use App\Modules\PMC\OgTicketCategory\Requests\UpdateOgTicketCategoryRequest;
use App\Modules\PMC\OgTicketCategory\Resources\OgTicketCategoryResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Symfony\Component\HttpFoundation\Response;

/**
 * @tags OG Ticket Categories
 */
class OgTicketCategoryController extends BaseController implements HasMiddleware
{
    public function __construct(
        protected OgTicketCategoryServiceInterface $service,
    ) {}

    /**
     * @return list<Middleware>
     */
    public static function middleware(): array
    {
        return [
            new Middleware('permission:og-tickets.view', only: ['index', 'show']),
            new Middleware('permission:og-tickets.update', only: ['store', 'update', 'destroy', 'checkDelete']),
        ];
    }

    /**
     * List OG ticket categories.
     */
    public function index(ListOgTicketCategoryRequest $request): AnonymousResourceCollection
    {
        $paginator = $this->service->list($request->validated());

        return OgTicketCategoryResource::collection($paginator)->additional(['success' => true]);
    }

    /**
     * Get an OG ticket category by ID.
     */
    public function show(int $id): OgTicketCategoryResource
    {
        return new OgTicketCategoryResource($this->service->findById($id));
    }

    /**
     * Create a new OG ticket category.
     */
    public function store(CreateOgTicketCategoryRequest $request): JsonResponse
    {
        return (new OgTicketCategoryResource($this->service->create($request->validated())))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Update an existing OG ticket category.
     */
    public function update(UpdateOgTicketCategoryRequest $request, int $id): OgTicketCategoryResource
    {
        return new OgTicketCategoryResource($this->service->update($id, $request->validated()));
    }

    /**
     * Check if a category can be deleted.
     */
    public function checkDelete(int $id): JsonResponse
    {
        return response()->json($this->service->checkDelete($id));
    }

    /**
     * Delete an OG ticket category.
     */
    public function destroy(int $id): JsonResponse
    {
        $this->service->delete($id);

        return response()->json(['success' => true, 'message' => 'Xoá thành công.']);
    }
}
