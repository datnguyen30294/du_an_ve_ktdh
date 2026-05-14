<?php

namespace App\Modules\PMC\Catalog\Services;

use App\Common\Exceptions\BusinessException;
use App\Common\Services\BaseService;
use App\Modules\PMC\Catalog\Contracts\CatalogSupplierServiceInterface;
use App\Modules\PMC\Catalog\Models\CatalogSupplier;
use App\Modules\PMC\Catalog\Repositories\CatalogSupplierRepository;
use Illuminate\Pagination\LengthAwarePaginator;
use Symfony\Component\HttpFoundation\Response;

class CatalogSupplierService extends BaseService implements CatalogSupplierServiceInterface
{
    public function __construct(
        protected CatalogSupplierRepository $repository,
    ) {}

    public function list(array $filters): LengthAwarePaginator
    {
        return $this->repository->list($filters);
    }

    public function findById(int $id): CatalogSupplier
    {
        /** @var CatalogSupplier */
        return $this->repository->findById($id);
    }

    public function create(array $data): CatalogSupplier
    {
        return $this->executeInTransaction(function () use ($data): CatalogSupplier {
            /** @var CatalogSupplier */
            $supplier = $this->repository->create($data);

            return $supplier->refresh();
        });
    }

    public function update(int $id, array $data): CatalogSupplier
    {
        return $this->executeInTransaction(function () use ($id, $data): CatalogSupplier {
            $supplier = $this->findById($id);
            $supplier->update($data);

            return $supplier->refresh();
        });
    }

    public function checkDelete(int $id): array
    {
        $supplier = $this->findById($id);
        $itemCount = $supplier->items()->count();

        if ($itemCount > 0) {
            return [
                'can_delete' => false,
                'message' => "Không thể xoá: còn {$itemCount} danh mục hàng đang liên kết với nhà cung cấp này. Hãy gỡ liên kết trước.",
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
        $this->executeInTransaction(function () use ($id): void {
            $supplier = $this->findById($id);
            $itemCount = $supplier->items()->count();

            if ($itemCount > 0) {
                throw new BusinessException(
                    "Không thể xoá: còn {$itemCount} danh mục hàng đang liên kết với nhà cung cấp này. Hãy gỡ liên kết trước.",
                    'SUPPLIER_HAS_ITEMS',
                    Response::HTTP_UNPROCESSABLE_ENTITY,
                    ['item_count' => $itemCount],
                );
            }

            $supplier->delete();
        });
    }
}
