<?php

namespace App\Modules\Platform\Customer\Contracts;

use App\Modules\Platform\Customer\Models\Customer;
use Illuminate\Pagination\LengthAwarePaginator;

interface CustomerServiceInterface
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function list(array $filters): LengthAwarePaginator;

    public function findById(int $id): Customer;
}
