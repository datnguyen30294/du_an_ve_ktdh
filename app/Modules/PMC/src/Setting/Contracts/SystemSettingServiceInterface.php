<?php

namespace App\Modules\PMC\Setting\Contracts;

interface SystemSettingServiceInterface
{
    /**
     * Get a single setting value with fallback default.
     */
    public function get(string $group, string $key, mixed $default = null): mixed;

    /**
     * Get all settings for a group as key => value map.
     *
     * @return array<string, mixed>
     */
    public function getGroup(string $group): array;

    /**
     * Update batch settings for a group (upsert).
     *
     * @param  array<string, string|null>  $settings
     */
    public function updateGroup(string $group, array $settings): void;
}
