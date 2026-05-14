<?php

namespace App\Modules\PMC\Setting\Repositories;

use App\Common\Repositories\BaseRepository;
use App\Modules\PMC\Setting\Models\SystemSetting;
use Illuminate\Database\Eloquent\Model;

class SystemSettingRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct(new SystemSetting);
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
        /** @var SystemSetting|null */
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
