<?php

namespace App\Modules\PMC\Catalog\Contracts;

use App\Modules\PMC\Catalog\Models\ServiceCategory;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface ServiceCategoryServiceInterface
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function list(array $filters): LengthAwarePaginator;

    public function findById(int $id): ServiceCategory;

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): ServiceCategory;

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(int $id, array $data): ServiceCategory;

    /**
     * @return array{can_delete: bool, message: string, item_count: int}
     */
    public function checkDelete(int $id): array;

    public function delete(int $id): void;

    public function uploadImage(int $id, UploadedFile $file): ServiceCategory;

    public function deleteImage(int $id): ServiceCategory;

    /**
     * @param  array<string>  $columns
     * @return Collection<int, ServiceCategory>
     */
    public function listActiveCategories(array $columns = ['*']): Collection;
}
