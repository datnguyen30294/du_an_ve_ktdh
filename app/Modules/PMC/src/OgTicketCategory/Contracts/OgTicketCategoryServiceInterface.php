<?php

namespace App\Modules\PMC\OgTicketCategory\Contracts;

use App\Modules\PMC\OgTicketCategory\Models\OgTicketCategory;
use Illuminate\Pagination\LengthAwarePaginator;

interface OgTicketCategoryServiceInterface
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function list(array $filters): LengthAwarePaginator;

    public function findById(int $id): OgTicketCategory;

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): OgTicketCategory;

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(int $id, array $data): OgTicketCategory;

    /**
     * @return array{can_delete: bool, message: string, link_count: int}
     */
    public function checkDelete(int $id): array;

    public function delete(int $id): void;
}
