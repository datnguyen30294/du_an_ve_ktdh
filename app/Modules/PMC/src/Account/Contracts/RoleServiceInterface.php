<?php

namespace App\Modules\PMC\Account\Contracts;

use App\Modules\PMC\Account\Models\Role;
use Illuminate\Pagination\LengthAwarePaginator;

interface RoleServiceInterface
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function list(array $filters): LengthAwarePaginator;

    public function findById(int $id): Role;

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Role;

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(int $id, array $data): Role;

    public function delete(int $id): void;
}
