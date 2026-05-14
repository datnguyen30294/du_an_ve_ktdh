<?php

namespace App\Modules\PMC\Report\CashFlow\Resources;

use App\Common\Resources\BaseResource;
use App\Modules\PMC\Treasury\Enums\CashTransactionCategory;
use App\Modules\PMC\Treasury\Enums\CashTransactionDirection;
use Illuminate\Http\Request;

class CashFlowTransactionResource extends BaseResource
{
    /**
     * @return array{
     *     id: int,
     *     code: string,
     *     transaction_date: string,
     *     direction: array{value: string, label: string},
     *     category: array{value: string, label: string},
     *     amount: string,
     *     project_name: string|null,
     *     order_code: string|null,
     *     note: string|null,
     * }
     */
    public function toArray(Request $request): array
    {
        /** @var object $data */
        $data = $this->resource;

        $direction = CashTransactionDirection::from($data->direction);
        $category = CashTransactionCategory::from($data->category);

        return [
            'id' => (int) $data->id,
            'code' => $data->code,
            'transaction_date' => $data->transaction_date,
            'direction' => ['value' => $direction->value, 'label' => $direction->label()],
            'category' => ['value' => $category->value, 'label' => $category->label()],
            'amount' => number_format((float) $data->amount, 2, '.', ''),
            'project_name' => $data->project_name ?? null,
            'order_code' => $data->order_code ?? null,
            'note' => $data->note ?? null,
        ];
    }
}
