<?php

namespace App\Modules\PMC\Policy\Resources;

use App\Common\Resources\BaseResource;
use App\Modules\PMC\Policy\Models\Policy;
use Illuminate\Http\Request;

/** @mixin Policy */
class PolicyDetailResource extends BaseResource
{
    /**
     * @return array{id: int, type: array{value: string, label: string}, title: string, content: string|null, is_published: bool, created_at: string|null, updated_at: string|null}
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
            'content' => $this->content,
            /** @var bool */
            'is_published' => $this->is_published,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
