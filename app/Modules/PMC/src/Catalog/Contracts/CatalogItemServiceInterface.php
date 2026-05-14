<?php

namespace App\Modules\PMC\Catalog\Contracts;

use App\Modules\PMC\Catalog\Models\CatalogItem;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;

interface CatalogItemServiceInterface
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function list(array $filters): LengthAwarePaginator;

    public function findById(int $id): CatalogItem;

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): CatalogItem;

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(int $id, array $data): CatalogItem;

    public function delete(int $id): void;

    public function uploadImage(int $id, UploadedFile $file): CatalogItem;

    public function deleteImage(int $id): CatalogItem;

    /**
     * @param  array<string, mixed>  $filters
     */
    public function listPublicServices(array $filters): LengthAwarePaginator;

    public function findPublicBySlug(string $slug): ?CatalogItem;

    /**
     * @param  array<UploadedFile>  $files
     */
    public function uploadGalleryImages(int $id, array $files): CatalogItem;

    public function deleteGalleryImage(int $id, int $imageId): CatalogItem;
}
