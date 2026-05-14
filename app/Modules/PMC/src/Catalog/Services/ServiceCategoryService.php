<?php

namespace App\Modules\PMC\Catalog\Services;

use App\Common\Contracts\StorageServiceInterface;
use App\Common\Exceptions\BusinessException;
use App\Common\Services\BaseService;
use App\Modules\PMC\Catalog\Contracts\ServiceCategoryServiceInterface;
use App\Modules\PMC\Catalog\Models\ServiceCategory;
use App\Modules\PMC\Catalog\Repositories\ServiceCategoryRepository;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\Response;

class ServiceCategoryService extends BaseService implements ServiceCategoryServiceInterface
{
    public const IMAGE_DIRECTORY = 'service-categories';

    public function __construct(
        protected ServiceCategoryRepository $repository,
        protected StorageServiceInterface $storageService,
    ) {}

    public function list(array $filters): LengthAwarePaginator
    {
        return $this->repository->list($filters);
    }

    public function findById(int $id): ServiceCategory
    {
        /** @var ServiceCategory */
        return $this->repository->findById($id);
    }

    public function create(array $data): ServiceCategory
    {
        /** @var ServiceCategory */
        $category = $this->repository->create($data);

        return $category->refresh();
    }

    public function update(int $id, array $data): ServiceCategory
    {
        $category = $this->findById($id);
        $category->update($data);

        return $category->refresh();
    }

    public function checkDelete(int $id): array
    {
        $category = $this->findById($id);
        $itemCount = $category->items()->count();

        if ($itemCount > 0) {
            return [
                'can_delete' => false,
                'message' => "Không thể xoá: còn {$itemCount} dịch vụ đang liên kết với danh mục này. Hãy gỡ liên kết trước.",
                'item_count' => $itemCount,
            ];
        }

        return [
            'can_delete' => true,
            'message' => '',
            'item_count' => 0,
        ];
    }

    public function delete(int $id): void
    {
        $category = $this->findById($id);
        $itemCount = $category->items()->count();

        if ($itemCount > 0) {
            throw new BusinessException(
                "Không thể xoá: còn {$itemCount} dịch vụ đang liên kết với danh mục này. Hãy gỡ liên kết trước.",
                'CATEGORY_HAS_ITEMS',
                Response::HTTP_UNPROCESSABLE_ENTITY,
                ['item_count' => $itemCount],
            );
        }

        if ($category->image_path) {
            $this->storageService->delete($category->image_path);
        }

        $category->delete();
    }

    public function uploadImage(int $id, UploadedFile $file): ServiceCategory
    {
        return $this->executeInTransaction(function () use ($id, $file): ServiceCategory {
            $category = $this->findById($id);

            if ($category->image_path) {
                $this->storageService->delete($category->image_path);
            }

            $path = $this->storageService->upload($file, self::IMAGE_DIRECTORY);
            $category->update(['image_path' => $path]);

            return $category->refresh();
        });
    }

    public function deleteImage(int $id): ServiceCategory
    {
        $category = $this->findById($id);

        if ($category->image_path) {
            $this->storageService->delete($category->image_path);
            $category->update(['image_path' => null]);
        }

        return $category->refresh();
    }

    public function listActiveCategories(array $columns = ['*']): Collection
    {
        return $this->repository->listActive($columns);
    }
}
