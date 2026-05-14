<?php

namespace App\Modules\PMC\Treasury\Resources;

use App\Modules\PMC\Treasury\Models\CashTransaction;
use Illuminate\Http\Request;
use OwenIt\Auditing\Models\Audit;

/**
 * @mixin CashTransaction
 */
class CashTransactionDetailResource extends CashTransactionListResource
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
     *     cash_account: array{id: int, code: string, name: string, type: array{value: string, label: string}}|null,
     *     reconciliation: array{id: int, status: string|null, reconciled_at: string|null, payment_receipt: array{id: int, amount: string, paid_at: string|null, type: array{value: string, label: string}}|null}|null,
     *     commission_snapshot: array{id: int, amount: string, paid_out_at: string|null, order_id: int|null}|null,
     *     audit_history: list<array{event: string, old_values: array<string, mixed>|null, new_values: array<string, mixed>|null, user: array{id: int, name: string}|null, created_at: string|null}>,
     * }
     */
    public function toArray(Request $request): array
    {
        $base = parent::toArray($request);

        return array_merge($base, [
            'cash_account' => $this->relationLoaded('cashAccount') && $this->cashAccount
                ? [
                    'id' => $this->cashAccount->id,
                    'code' => $this->cashAccount->code,
                    'name' => $this->cashAccount->name,
                    'type' => [
                        'value' => $this->cashAccount->type->value,
                        'label' => $this->cashAccount->type->label(),
                    ],
                ]
                : null,
            'reconciliation' => $this->reconciliationPayload(),
            'commission_snapshot' => $this->commissionSnapshotPayload(),
            'audit_history' => $this->auditHistoryPayload(),
        ]);
    }

    /**
     * @return array{id: int, status: string|null, reconciled_at: string|null, payment_receipt: array{id: int, amount: string, paid_at: string|null, type: array{value: string, label: string}}|null}|null
     */
    protected function reconciliationPayload(): ?array
    {
        if (! $this->relationLoaded('financialReconciliation') || ! $this->financialReconciliation) {
            return null;
        }

        $recon = $this->financialReconciliation;
        $receipt = $recon->relationLoaded('paymentReceipt') ? $recon->paymentReceipt : null;

        return [
            'id' => $recon->id,
            'status' => $recon->status?->value,
            'reconciled_at' => $recon->reconciled_at?->toIso8601String(),
            'payment_receipt' => $receipt
                ? [
                    'id' => $receipt->id,
                    'amount' => (string) $receipt->amount,
                    'paid_at' => $receipt->paid_at?->toDateString(),
                    'type' => [
                        'value' => $receipt->type->value,
                        'label' => $receipt->type->label(),
                    ],
                ]
                : null,
        ];
    }

    /**
     * @return array{id: int, amount: string, paid_out_at: string|null, order_id: int|null}|null
     */
    private function commissionSnapshotPayload(): ?array
    {
        if (! $this->relationLoaded('commissionSnapshot') || ! $this->commissionSnapshot) {
            return null;
        }

        $snapshot = $this->commissionSnapshot;

        return [
            'id' => $snapshot->id,
            'amount' => (string) $snapshot->amount,
            'paid_out_at' => $snapshot->paid_out_at?->toIso8601String(),
            'order_id' => $snapshot->order_id,
        ];
    }

    /**
     * @return list<array{event: string, old_values: array<string, mixed>|null, new_values: array<string, mixed>|null, user: array{id: int, name: string}|null, created_at: string|null}>
     */
    private function auditHistoryPayload(): array
    {
        $audits = Audit::query()
            ->where('auditable_type', $this->resource::class)
            ->where('auditable_id', $this->id)
            ->with('user:id,name')
            ->orderBy('created_at')
            ->get();

        return $audits->map(fn (Audit $audit) => [
            'event' => $audit->event,
            'old_values' => $audit->old_values,
            'new_values' => $audit->new_values,
            'user' => $audit->user
                ? ['id' => $audit->user->id, 'name' => $audit->user->name]
                : null,
            'created_at' => $audit->created_at?->toIso8601String(),
        ])->values()->all();
    }
}
