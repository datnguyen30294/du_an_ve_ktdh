<?php

namespace App\Modules\PMC\Policy\Resources;

use App\Common\Resources\BaseResource;
use App\Modules\PMC\Policy\Models\Policy;
use Illuminate\Http\Request;

/** @mixin Policy */
class PolicyListResource extends BaseResource
{
    /**
     * @return array{id: int, type: array{value: string, label: string}, title: string, is_published: bool, updated_at: string|null}
     */
    public function toArray(Request $request): array
    {
        return [
            /** @var int */
            'id' => $this->id,
            /** @var array{value: string, label: string} */
            'type' => [
                'value' => $this->type->value,
                'label' => $this->type->label(),
            ],
            'title' => $this->title,
            /** @var bool */
            'is_published' => $this->is_published,
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
