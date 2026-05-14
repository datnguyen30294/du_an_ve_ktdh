<?php

namespace App\Modules\PMC\Catalog\Contracts;

use App\Modules\PMC\Catalog\Models\CatalogSupplier;
use Illuminate\Pagination\LengthAwarePaginator;

interface CatalogSupplierServiceInterface
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function list(array $filters): LengthAwarePaginator;

    public function findById(int $id): CatalogSupplier;

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): CatalogSupplier;

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(int $id, array $data): CatalogSupplier;

    /**
     * @return array{can_delete: bool, message: string, item_count: int}
     */
    public function checkDelete(int $id): array;

    public function delete(int $id): void;
}
