<?php

namespace App\Modules\PMC\Policy\Services;

use App\Common\Services\BaseService;
use App\Modules\PMC\Policy\Contracts\PolicyServiceInterface;
use App\Modules\PMC\Policy\Enums\PolicyType;
use App\Modules\PMC\Policy\Models\Policy;
use App\Modules\PMC\Policy\Repositories\PolicyRepository;
use Illuminate\Support\Collection;

class PolicyService extends BaseService implements PolicyServiceInterface
{
    public function __construct(
        protected PolicyRepository $repository,
    ) {}

    /** @return Collection<int, Policy> */
    public function getAll(): Collection
    {
        return $this->repository->getAll();
    }

    public function getByType(PolicyType $type): ?Policy
    {
        return $this->repository->findByType($type);
    }

    public function getPublishedByType(PolicyType $type): ?Policy
    {
        return $this->repository->findPublishedByType($type);
    }

    /** @param array<string, mixed> $data */
    public function upsert(PolicyType $type, array $data): Policy
    {
        /** @var Policy */
        return $this->repository->upsertByType($type, $data);
    }
}
