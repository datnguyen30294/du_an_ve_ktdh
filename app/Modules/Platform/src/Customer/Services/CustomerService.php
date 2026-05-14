<?php

namespace App\Modules\Platform\Customer\Services;

use App\Modules\Platform\Customer\Contracts\CustomerServiceInterface;
use App\Modules\Platform\Customer\Models\Customer;
use App\Modules\Platform\Customer\Repositories\CustomerRepository;
use Illuminate\Pagination\LengthAwarePaginator;

class CustomerService implements CustomerServiceInterface
{
    public function __construct(
        protected CustomerRepository $repository,
    ) {}

    public function list(array $filters): LengthAwarePaginator
    {
        return $this->repository->list($filters);
    }

    public function findById(int $id): Customer
    {
        /** @var Customer */
        $customer = $this->repository->findById($id, ['*'], ['tickets']);

        return $customer;
    }
}
