<?php

namespace App\Modules\PMC\Policy\Repositories;

use App\Common\Repositories\BaseRepository;
use App\Modules\PMC\Policy\Enums\PolicyType;
use App\Modules\PMC\Policy\Models\Policy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class PolicyRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct(new Policy);
    }

    /**
     * Get all policies.
     *
     * @return Collection<int, Policy>
     */
    public function getAll(): Collection
    {
        return $this->newQuery()
            ->orderBy('type')
            ->get();
    }

    /**
     * Find a policy by type.
     */
    public function findByType(PolicyType $type): ?Policy
    {
        /** @var Policy|null */
        return $this->newQuery()
            ->where('type', $type->value)
            ->first();
    }

    /**
     * Find a published policy by type.
     */
    public function findPublishedByType(PolicyType $type): ?Policy
    {
        /** @var Policy|null */
        return $this->newQuery()
            ->where('type', $type->value)
            ->where('is_published', true)
            ->first();
    }

    /**
     * Upsert a policy by type.
     */
    public function upsertByType(PolicyType $type, array $attributes): Model
    {
        return $this->newQuery()->updateOrCreate(
            ['type' => $type->value],
            $attributes,
        );
    }
}
