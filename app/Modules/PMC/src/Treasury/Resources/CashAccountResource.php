<?php

namespace App\Modules\PMC\Treasury\Resources;

use App\Common\Resources\BaseResource;
use App\Modules\PMC\Treasury\Models\CashAccount;
use Illuminate\Http\Request;

/**
 * @mixin CashAccount
 */
class CashAccountResource extends BaseResource
{
    /**
     * @return array{
     *     id: int,
     *     code: string,
     *     name: string,
     *     type: array{value: string, label: string},
     *     bank_id: int|null,
     *     bank_account_number: string|null,
     *     bank_account_name: string|null,
     *     opening_balance: string,
     *     current_balance: string|null,
     *     is_default: bool,
     *     is_active: bool,
     * }
     */
    public function toArray(Request $request): array
    {
        $currentBalance = $this->resource->getAttribute('current_balance');

        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'type' => [
                'value' => $this->type->value,
                'label' => $this->type->label(),
            ],
            'bank_id' => $this->bank_id,
            'bank_account_number' => $this->bank_account_number,
            'bank_account_name' => $this->bank_account_name,
            'opening_balance' => (string) $this->opening_balance,
            'current_balance' => $currentBalance !== null
                ? number_format((float) $currentBalance, 2, '.', '')
                : null,
            'is_default' => (bool) $this->is_default,
            'is_active' => (bool) $this->is_active,
        ];
    }
}
