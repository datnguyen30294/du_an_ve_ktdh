<?php

namespace App\Modules\PMC\Treasury\Resources;

use App\Common\Resources\BaseResource;
use App\Modules\PMC\Treasury\Models\CashTransaction;
use Illuminate\Http\Request;

/**
 * @mixin CashTransaction
 */
class CashTransactionListResource extends BaseResource
{
    /**
     * @return array{
     *     id: int,
     *     code: string,
     *     direction: array{value: string, label: string},
     *     amount: string,
     *     category: array{value: string, label: string},
     *     transaction_date: string|null,
     *     source: array{type: string, id: int|null, order_id: int|null, order_code: string|null},
     *     manual_reconciliation: array{id: int, status: array{value: string, label: string}, reconciled_at: string|null, reconciled_by: array{id: int, name: string}|null}|null,
     *     note: string|null,
     *     created_by: array{id: int, name: string}|null,
     *     is_deleted: bool,
     *     auto_deleted: bool,
     *     delete_reason: string|null,
     *     deleted_by: array{id: int, name: string}|null,
     *     deleted_at: string|null,
     * }
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'direction' => [
                'value' => $this->direction->value,
                'label' => $this->direction->label(),
            ],
            'amount' => (string) $this->amount,
            'category' => [
                'value' => $this->category->value,
                'label' => $this->category->label(),
            ],
            'transaction_date' => $this->transaction_date?->toDateString(),
            'source' => $this->sourcePayload(),
            'manual_reconciliation' => $this->manualReconciliationPayload(),
            'note' => $this->note,
            'created_by' => $this->relationLoaded('createdBy') && $this->createdBy
                ? ['id' => $this->createdBy->id, 'name' => $this->createdBy->name]
                : null,
            'is_deleted' => $this->trashed(),
            'auto_deleted' => (bool) $this->auto_deleted,
            'delete_reason' => $this->delete_reason,
            'deleted_by' => $this->relationLoaded('deletedBy') && $this->deletedBy
                ? ['id' => $this->deletedBy->id, 'name' => $this->deletedBy->name]
                : null,
            'deleted_at' => $this->deleted_at?->toIso8601String(),
        ];
    }

    /**
     * Pending/Reconciled/Rejected audit marker attached to manual cash transactions.
     * Auto-sourced txs (receivable / commission) return null — their reconciliation
     * lives elsewhere and is implicit in the `source` field.
     *
     * @return array{id: int, status: array{value: string, label: string}, reconciled_at: string|null, reconciled_by: array{id: int, name: string}|null}|null
     */
    protected function manualReconciliationPayload(): ?array
    {
        if (! $this->relationLoaded('manualReconciliation') || ! $this->manualReconciliation) {
            return null;
        }

        $fr = $this->manualReconciliation;

        return [
            'id' => $fr->id,
            'status' => [
                'value' => $fr->status->value,
                'label' => $fr->status->label(),
            ],
            'reconciled_at' => $fr->reconciled_at?->toIso8601String(),
            'reconciled_by' => $fr->relationLoaded('reconciledBy') && $fr->reconciledBy
                ? ['id' => $fr->reconciledBy->id, 'name' => $fr->reconciledBy->name]
                : null,
        ];
    }

    /**
     * @return array{type: string, id: int|null, order_id: int|null, order_code: string|null}
     */
    protected function sourcePayload(): array
    {
        if ($this->financial_reconciliation_id !== null) {
            return [
                'type' => 'reconciliation',
                'id' => $this->financial_reconciliation_id,
                'order_id' => $this->order_id,
                'order_code' => $this->relationLoaded('order') ? $this->order?->code : null,
            ];
        }

        if ($this->commission_snapshot_id !== null) {
            return [
                'type' => 'commission_snapshot',
                'id' => $this->commission_snapshot_id,
                'order_id' => $this->order_id,
                'order_code' => $this->relationLoaded('order') ? $this->order?->code : null,
            ];
        }

        return [
            'type' => 'manual',
            'id' => null,
            'order_id' => null,
            'order_code' => null,
        ];
    }
}
