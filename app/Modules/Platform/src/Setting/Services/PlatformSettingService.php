<?php

namespace App\Modules\Platform\Setting\Services;

use App\Common\Services\BaseService;
use App\Modules\Platform\Setting\Contracts\PlatformSettingServiceInterface;
use App\Modules\Platform\Setting\Repositories\PlatformSettingRepository;

class PlatformSettingService extends BaseService implements PlatformSettingServiceInterface
{
    public function __construct(
        protected PlatformSettingRepository $repository,
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
