<?php

namespace App\Modules\PMC\Commission\Resources;

use App\Common\Resources\BaseResource;
use App\Modules\PMC\Project\Models\Project;
use Illuminate\Http\Request;

/**
 * @mixin Project
 */
class CommissionProjectListResource extends BaseResource
{
    /**
     * @return array{
     *     id: int,
     *     code: string,
     *     name: string,
     *     address: string|null,
     *     is_configured: bool,
     *     dept_rules_count: int,
     * }
     */
    public function toArray(Request $request): array
    {
        return [
            /** @var int */
            'id' => $this->id,
            /** @var string */
            'code' => $this->code,
            /** @var string */
            'name' => $this->name,
            /** @var string|null */
            'address' => $this->address,
            /** @var bool */
            'is_configured' => $this->relationLoaded('commissionConfig') && $this->commissionConfig !== null,
            /** @var int */
            'dept_rules_count' => $this->commissionConfig?->dept_rules_count ?? 0,
        ];
    }
}
