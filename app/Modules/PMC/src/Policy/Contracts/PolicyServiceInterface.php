<?php

namespace App\Modules\PMC\Policy\Contracts;

use App\Modules\PMC\Policy\Enums\PolicyType;
use App\Modules\PMC\Policy\Models\Policy;
use Illuminate\Support\Collection;

interface PolicyServiceInterface
{
    /**
     * Get all policies.
     *
     * @return Collection<int, Policy>
     */
    public function getAll(): Collection;

    /**
     * Get a policy by type.
     */
    public function getByType(PolicyType $type): ?Policy;

    /**
     * Get a published policy by type (for public display).
     */
    public function getPublishedByType(PolicyType $type): ?Policy;

    /**
     * Create or update a policy by type.
     *
     * @param  array<string, mixed>  $data
     */
    public function upsert(PolicyType $type, array $data): Policy;
}
