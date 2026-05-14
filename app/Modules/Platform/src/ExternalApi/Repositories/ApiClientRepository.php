<?php

namespace App\Modules\Platform\ExternalApi\Repositories;

use App\Common\Repositories\BaseRepository;
use App\Modules\Platform\ExternalApi\Models\ApiClient;
use Illuminate\Pagination\LengthAwarePaginator;

class ApiClientRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct(new ApiClient);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function list(array $filters): LengthAwarePaginator
    {
        $query = $this->newQuery();

        if (! empty($filters['search'])) {
            $query->where('name', 'ilike', "%{$filters['search']}%");
        }

        if (! empty($filters['organization_id'])) {
            $query->where('organization_id', $filters['organization_id']);
        }

        if (isset($filters['is_active'])) {
            $query->where('is_active', $filters['is_active']);
        }

        $this->applySorting($query, $filters);

        return $query->paginate($this->getPerPage($filters));
    }

    public function findByClientKey(string $clientKey): ?ApiClient
    {
        /** @var ApiClient|null */
        return $this->newQuery()
            ->where('client_key', $clientKey)
            ->first();
    }
}
