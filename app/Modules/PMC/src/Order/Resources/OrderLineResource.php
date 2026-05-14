<?php

namespace App\Modules\PMC\Order\Resources;

use App\Common\Resources\BaseResource;
use App\Modules\PMC\Order\Models\OrderLine;
use Illuminate\Http\Request;

/**
 * @mixin OrderLine
 */
class OrderLineResource extends BaseResource
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
     *     advance_amount: string,
     *     advance_status: string,
     *     advance_payer: array{id: int, name: string, employee_code: string|null, bank_info: array{bin: string, label: string, account_number: string, account_name: string}|null}|null,
     * }
     */
    public function toArray(Request $request): array
    {
        $payer = $this->relationLoaded('advancePayer') ? $this->advancePayer : null;

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
            /** @var string */
            'advance_amount' => number_format($this->advanceAmount(), 2, '.', ''),
            /** @var string */
            'advance_status' => $this->advanceStatus(),
            /** @var array{id: int, name: string, employee_code: string|null, bank_info: array{bin: string, label: string, account_number: string, account_name: string}|null}|null */
            'advance_payer' => $payer
                ? [
                    'id' => $payer->id,
                    'name' => $payer->name,
                    'employee_code' => $payer->employee_code,
                    'bank_info' => $payer->bankInfo(),
                ]
                : null,
        ];
    }
}
