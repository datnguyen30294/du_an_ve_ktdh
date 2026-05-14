<?php

namespace App\Modules\PMC\Quote\Resources;

use App\Common\Resources\BaseResource;
use App\Modules\PMC\Quote\Models\QuoteLine;
use Illuminate\Http\Request;

/**
 * @mixin QuoteLine
 */
class QuoteLineResource extends BaseResource
{
    /**
     * @return array{
     *     id: int,
     *     line_type: array{value: string, label: string},
     *     reference_id: int,
     *     name: string,
     *     quantity: int,
     *     unit: string,
     *     unit_price: string,
     *     purchase_price: string|null,
     *     line_amount: string,
     * }
     */
    public function toArray(Request $request): array
    {
        return [
            /** @var int */
            'id' => $this->id,
            'line_type' => [
                'value' => $this->line_type->value,
                'label' => $this->line_type->label(),
            ],
            /** @var int */
            'reference_id' => $this->reference_id,
            /** @var string */
            'name' => $this->name,
            /** @var int */
            'quantity' => $this->quantity,
            /** @var string */
            'unit' => $this->unit,
            /** @var string */
            'unit_price' => $this->unit_price,
            /** @var string|null */
            'purchase_price' => $this->purchase_price,
            /** @var string */
            'line_amount' => $this->line_amount,
        ];
    }
}
