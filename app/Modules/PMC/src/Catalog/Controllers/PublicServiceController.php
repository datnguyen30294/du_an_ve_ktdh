<?php

namespace App\Modules\PMC\Catalog\Controllers;

use App\Common\Controllers\BaseController;
use App\Modules\PMC\Catalog\Contracts\CatalogItemServiceInterface;
use App\Modules\PMC\Catalog\Contracts\ServiceCategoryServiceInterface;
use App\Modules\PMC\Catalog\Models\CatalogItem;
use App\Modules\PMC\Catalog\Models\CatalogItemImage;
use App\Modules\PMC\Catalog\Models\ServiceCategory;
use App\Modules\PMC\Catalog\Requests\ListPublicServiceRequest;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * @tags Public Services
 */
class PublicServiceController extends BaseController
{
    public function __construct(
        protected CatalogItemServiceInterface $catalogItemService,
        protected ServiceCategoryServiceInterface $serviceCategoryService,
    ) {}

    /**
     * List active services with categories for public /dich-vu page.
     */
    public function index(ListPublicServiceRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $services = $this->catalogItemService->listPublicServices([
            'category_code' => $validated['category'] ?? null,
            'search' => $validated['search'] ?? null,
            'per_page' => (int) ($validated['per_page'] ?? 12),
        ]);

        $categories = $this->serviceCategoryService->listActiveCategories(['id', 'name', 'code'])
            ->map(fn (ServiceCategory $cat) => [
                /** @var int */
                'id' => $cat->id,
                'name' => $cat->name,
                'code' => $cat->code,
            ])->values();

        return response()->json([
            'success' => true,
            'categories' => $categories,
            'data' => $services->getCollection()->map(fn (CatalogItem $item) => [
                /** @var int */
                'id' => $item->id,
                'name' => $item->name,
                'slug' => $item->slug,
                /** @var string|null */
                'description' => $item->description,
                /** @var string */
                'unit_price' => $item->unit_price,
                /** @var string|null */
                'price_note' => $item->price_note,
                'unit' => $item->unit,
                /** @var string|null */
                'image_url' => $item->image_url,
                /** @var bool */
                'is_featured' => $item->is_featured,
                /** @var array{id: int, name: string, code: string}|null */
                'category' => $item->serviceCategory ? [
                    'id' => $item->serviceCategory->id,
                    'name' => $item->serviceCategory->name,
                    'code' => $item->serviceCategory->code,
                ] : null,
            ]),
            'meta' => [
                'current_page' => $services->currentPage(),
                'last_page' => $services->lastPage(),
                'per_page' => $services->perPage(),
                'total' => $services->total(),
            ],
        ]);
    }

    /**
     * Get public service detail by slug.
     */
    public function show(string $slug): JsonResponse
    {
        $item = $this->catalogItemService->findPublicBySlug($slug);

        if (! $item) {
            return response()->json([
                'success' => false,
                'message' => 'Dịch vụ không tồn tại.',
            ], Response::HTTP_NOT_FOUND);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $item->id,
                'name' => $item->name,
                'slug' => $item->slug,
                'description' => $item->description,
                'content' => $item->content,
                'unit_price' => $item->unit_price,
                'price_note' => $item->price_note,
                'unit' => $item->unit,
                'image_url' => $item->image_url,
                'is_featured' => $item->is_featured,
                'category' => $item->serviceCategory ? [
                    'id' => $item->serviceCategory->id,
                    'name' => $item->serviceCategory->name,
                    'code' => $item->serviceCategory->code,
                ] : null,
                'images' => $item->images->map(fn (CatalogItemImage $img) => [
                    'id' => $img->id,
                    'image_url' => $img->image_url,
                    'sort_order' => $img->sort_order,
                ])->values()->all(),
            ],
        ]);
    }
}
