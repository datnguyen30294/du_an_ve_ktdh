<?php

namespace App\Modules\PMC\Setting\Services;

use App\Common\Services\BaseService;
use App\Modules\PMC\Setting\Contracts\SystemSettingServiceInterface;
use App\Modules\PMC\Setting\Repositories\SystemSettingRepository;

class SystemSettingService extends BaseService implements SystemSettingServiceInterface
{
    public function __construct(
        protected SystemSettingRepository $repository,
    ) {}

    public function get(string $group, string $key, mixed $default = null): mixed
    {
        $value = $this->repository->getValue($group, $key);

        return $value !== null ? $value : $default;
    }

    /**
     * @return array<string, mixed>
     */
    public function getGroup(string $group): array
    {
        return $this->repository->getGroup($group);
    }

    /**
     * @param  array<string, string|null>  $settings
     */
    public function updateGroup(string $group, array $settings): void
    {
        $this->executeInTransaction(function () use ($group, $settings): void {
            foreach ($settings as $key => $value) {
                $this->repository->setValue($group, $key, $value);
            }
        });
    }
}
