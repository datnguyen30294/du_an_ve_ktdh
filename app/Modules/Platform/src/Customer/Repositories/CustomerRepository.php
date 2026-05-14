<?php

namespace App\Modules\Platform\Customer\Repositories;

use App\Common\Repositories\BaseRepository;
use App\Modules\Platform\Customer\Models\Customer;
use Illuminate\Pagination\LengthAwarePaginator;

class CustomerRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct(new Customer);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function list(array $filters): LengthAwarePaginator
    {
        $query = $this->newQuery()->withCount('tickets');

        if (! empty($filters['search'])) {
            $query->search($filters['search']);
        }

        $this->applySorting($query, $filters);

        return $query->paginate($this->getPerPage($filters));
    }

    public function findByPhone(string $phone): ?Customer
    {
        /** @var Customer|null */
        return $this->newQuery()->where('phone', $phone)->first();
    }
}
