<?php

namespace App\Modules\PMC\Treasury\Resources;

use App\Common\Resources\BaseResource;
use Illuminate\Http\Request;

class TreasuryKpiResource extends BaseResource
{
    /**
     * @return array{
     *     cash_account_id: int,
     *     date_from: string|null,
     *     date_to: string|null,
     *     current_balance: string,
     *     total_inflow: string,
     *     total_outflow: string,
     *     net_flow: string,
     *     transaction_count: int,
     *     inflow_by_category: list<array{category: array{value: string, label: string}, amount: string, count: int}>,
     *     outflow_by_category: list<array{category: array{value: string, label: string}, amount: string, count: int}>,
     * }
     */
    public function toArray(Request $request): array
    {
        /** @var array<string, mixed> $data */
        $data = $this->resource;

        return [
            'cash_account_id' => (int) $data['cash_account_id'],
            'date_from' => $data['date_from'] ?? null,
            'date_to' => $data['date_to'] ?? null,
            'current_balance' => $data['current_balance'],
            'total_inflow' => $data['total_inflow'],
            'total_outflow' => $data['total_outflow'],
            'net_flow' => $data['net_flow'],
            'transaction_count' => (int) $data['transaction_count'],
            'inflow_by_category' => $data['inflow_by_category'],
            'outflow_by_category' => $data['outflow_by_category'],
        ];
    }
}
