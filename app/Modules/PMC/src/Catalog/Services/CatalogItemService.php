<?php

namespace App\Modules\PMC\Catalog\Services;

use App\Common\Contracts\StorageServiceInterface;
use App\Common\Services\BaseService;
use App\Modules\PMC\Catalog\Contracts\CatalogItemServiceInterface;
use App\Modules\PMC\Catalog\Enums\CatalogItemType;
use App\Modules\PMC\Catalog\Models\CatalogItem;
use App\Modules\PMC\Catalog\Models\CatalogItemImage;
use App\Modules\PMC\Catalog\Repositories\CatalogItemRepository;
use App\Modules\PMC\Catalog\Repositories\CatalogSupplierRepository;
use App\Modules\PMC\Catalog\Repositories\ServiceCategoryRepository;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;

class CatalogItemService extends BaseService implements CatalogItemServiceInterface
{
    public const IMAGE_DIRECTORY = 'catalog-items';

    public function __construct(
        protected CatalogItemRepository $repository,
        protected CatalogSupplierRepository $supplierRepository,
        protected ServiceCategoryRepository $serviceCategoryRepository,
        protected StorageServiceInterface $storageService,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function list(array $filters): LengthAwarePaginator
    {
        return $this->repository->list($filters);
    }

    public function findById(int $id): CatalogItem
    {
        /** @var CatalogItem */
        return $this->repository->findById($id, ['*'], ['supplier', 'serviceCategory', 'images']);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): CatalogItem
    {
        $data = $this->fillCommissionRateFromSupplier($data);
        $data = $this->fillSlug($data);

        /** @var CatalogItem */
        $item = $this->repository->create($data);

        return $item->refresh()->load(['supplier', 'serviceCategory', 'images']);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(int $id, array $data): CatalogItem
    {
        $data = $this->fillCommissionRateFromSupplier($data);

        if (isset($data['name'])) {
            $item = $this->repository->findById($id);
            $data = $this->fillSlug($data, $item->type, $id);
        }

        $this->repository->update($id, $data);

        /** @var CatalogItem */
        return $this->repository->findById($id, ['*'], ['supplier', 'serviceCategory', 'images']);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function fillCommissionRateFromSupplier(array $data): array
    {
        if (empty($data['commission_rate']) && ! empty($data['supplier_id'])) {
            $supplier = $this->supplierRepository->findById($data['supplier_id']);
            $data['commission_rate'] = $supplier->commission_rate;
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function fillSlug(array $data, ?CatalogItemType $existingType = null, ?int $ignoreId = null): array
    {
        if (! empty($data['name'])) {
            $type = $existingType ?? CatalogItemType::from($data['type']);
            $data['slug'] = CatalogItem::generateSlug($data['name'], $type, $ignoreId);
        }

        return $data;
    }

    public function delete(int $id): void
    {
        $item = $this->findById($id);

        if ($item->image_path) {
            $this->storageService->delete($item->image_path);
        }

        foreach ($item->images as $image) {
            $this->storageService->delete($image->image_path);
        }

        $item->delete();
    }

    public function uploadImage(int $id, UploadedFile $file): CatalogItem
    {
        return $this->executeInTransaction(function () use ($id, $file): CatalogItem {
            $item = $this->findById($id);

            if ($item->image_path) {
                $this->storageService->delete($item->image_path);
            }

            $path = $this->storageService->upload($file, self::IMAGE_DIRECTORY);
            $item->update(['image_path' => $path]);

            return $item->refresh()->load(['supplier', 'serviceCategory']);
        });
    }

    public function deleteImage(int $id): CatalogItem
    {
        $item = $this->findById($id);

        if ($item->image_path) {
            $this->storageService->delete($item->image_path);
            $item->update(['image_path' => null]);
        }

        return $item->refresh()->load(['supplier', 'serviceCategory']);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function listPublicServices(array $filters): LengthAwarePaginator
    {
        if (! empty($filters['category_code'])) {
            $category = $this->serviceCategoryRepository->findActiveByCode($filters['category_code']);
            $filters['service_category_id'] = $category?->id;

            if (! $category) {
                $filters['service_category_id'] = 0;
            }
        }

        return $this->repository->listPublicServices($filters);
    }

    public function findPublicBySlug(string $slug): ?CatalogItem
    {
        return $this->repository->findBySlug($slug, CatalogItemType::Service);
    }

    /**
     * @param  array<UploadedFile>  $files
     */
    public function uploadGalleryImages(int $id, array $files): CatalogItem
    {
        return $this->executeInTransaction(function () use ($id, $files): CatalogItem {
            $item = $this->findById($id);
            $maxSort = $item->images->max('sort_order') ?? 0;

            foreach ($files as $file) {
                $path = $this->storageService->upload($file, self::IMAGE_DIRECTORY);
                $item->images()->create([
                    'image_path' => $path,
                    'sort_order' => ++$maxSort,
                ]);
            }

            return $item->refresh()->load(['supplier', 'serviceCategory', 'images']);
        });
    }

    public function deleteGalleryImage(int $id, int $imageId): CatalogItem
    {
        $item = $this->findById($id);

        /** @var CatalogItemImage|null $image */
        $image = $item->images->firstWhere('id', $imageId);

        if ($image) {
            $this->storageService->delete($image->image_path);
            $image->delete();
        }

        return $item->refresh()->load(['supplier', 'serviceCategory', 'images']);
    }
}
