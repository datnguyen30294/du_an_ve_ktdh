<?php

namespace App\Modules\PMC\Setting\Resources;

use App\Common\Resources\BaseResource;
use App\Modules\PMC\Setting\Models\SystemSetting;
use Illuminate\Http\Request;

/**
 * @mixin SystemSetting
 */
class SystemSettingResource extends BaseResource
{
    /**
     * @return array{group: string, key: string, value: string|null}
     */
    public function toArray(Request $request): array
    {
        return [
            /** @var string */
            'group' => $this->group,
            /** @var string */
            'key' => $this->key,
            /** @var string|null */
            'value' => $this->value,
        ];
    }
}
