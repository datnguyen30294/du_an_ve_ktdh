<?php

namespace App\Modules\Platform\Setting\Repositories;

use App\Common\Repositories\BaseRepository;
use App\Modules\Platform\Setting\Models\PlatformSetting;
use Illuminate\Database\Eloquent\Model;

class PlatformSettingRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct(new PlatformSetting);
    }

    /**
     * Get all settings for a group as key => value map.
     *
     * @return array<string, string|null>
     */
    public function getGroup(string $group): array
    {
        return $this->newQuery()
            ->where('group', $group)
            ->pluck('value', 'key')
            ->all();
    }

    /**
     * Get a single setting value.
     */
    public function getValue(string $group, string $key): ?string
    {
        /** @var PlatformSetting|null */
        $setting = $this->newQuery()
            ->where('group', $group)
            ->where('key', $key)
            ->first();

        return $setting?->value;
    }

    /**
     * Upsert a single setting.
     */
    public function setValue(string $group, string $key, ?string $value): Model
    {
        return $this->newQuery()->updateOrCreate(
            ['group' => $group, 'key' => $key],
            ['value' => $value],
        );
    }
}
