<?php

namespace App\Modules\PMC\Order\Resources;

use App\Common\Resources\BaseResource;
use App\Modules\PMC\Order\Models\Order;
use Illuminate\Http\Request;

/**
 * @mixin Order
 */
class OrderListResource extends BaseResource
{
    /**
     * @return array{
     *     id: int,
     *     code: string,
     *     status: array{value: string, label: string},
     *     quote: array{id: int, code: string}|null,
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
            /** @var array{id: int, code: string}|null */
            'quote' => $this->relationLoaded('quote') && $this->quote
                ? ['id' => $this->quote->id, 'code' => $this->quote->code]
                : null,
            /** @var array{id: int, subject: string}|null */
            'og_ticket' => $this->relationLoaded('quote') && $this->quote?->relationLoaded('ogTicket') && $this->quote->ogTicket
                ? ['id' => $this->quote->ogTicket->id, 'subject' => $this->quote->ogTicket->subject]
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
