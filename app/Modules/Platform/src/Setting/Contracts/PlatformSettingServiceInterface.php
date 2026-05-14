<?php

namespace App\Modules\Platform\Setting\Contracts;

interface PlatformSettingServiceInterface
{
    public function get(string $group, string $key, mixed $default = null): mixed;

    /**
     * @return array<string, mixed>
     */
    public function getGroup(string $group): array;

    /**
     * @param  array<string, string|null>  $settings
     */
    public function updateGroup(string $group, array $settings): void;
}
