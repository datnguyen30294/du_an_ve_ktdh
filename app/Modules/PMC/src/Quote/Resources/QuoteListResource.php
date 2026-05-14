<?php

namespace App\Modules\PMC\Quote\Resources;

use App\Common\Resources\BaseResource;
use App\Modules\PMC\Quote\Models\Quote;
use Illuminate\Http\Request;

/**
 * @mixin Quote
 */
class QuoteListResource extends BaseResource
{
    /**
     * @return array{
     *     id: int,
     *     code: string,
     *     status: array{value: string, label: string},
     *     is_active: bool,
     *     og_ticket: array{id: int, subject: string}|null,
     *     total_amount: string,
     *     lines_count: int,
     *     created_at: string|null,
     * }
     */
    public function toArray(Request $request): array
    {
        return [
            /** @var int */
            'id' => $this->id,
            /** @var string */
            'code' => $this->code,
            'status' => [
                'value' => $this->status->value,
                'label' => $this->status->label(),
            ],
            /** @var bool */
            'is_active' => $this->is_active,
            /** @var array{id: int, subject: string}|null */
            'og_ticket' => $this->relationLoaded('ogTicket') && $this->ogTicket
                ? ['id' => $this->ogTicket->id, 'subject' => $this->ogTicket->subject]
                : null,
            /** @var string */
            'total_amount' => $this->total_amount,
            /** @var int */
            'lines_count' => $this->lines_count ?? 0,
            /** @var string|null */
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
