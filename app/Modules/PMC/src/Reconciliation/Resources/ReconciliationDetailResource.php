<?php

namespace App\Modules\PMC\Reconciliation\Resources;

use App\Common\Resources\BaseResource;
use App\Modules\PMC\Reconciliation\Models\FinancialReconciliation;
use Illuminate\Http\Request;

/**
 * @mixin FinancialReconciliation
 */
class ReconciliationDetailResource extends BaseResource
{
    /**
     * @return array{
     *     id: int,
     *     source: array{type: string, cash_transaction: array|null},
     *     amount: string,
     *     receivable: array|null,
     *     payment_receipt: array|null,
     *     status: array{value: string, label: string},
     *     reconciled_at: string|null,
     *     reconciled_by: array{id: int, name: string}|null,
     *     note: string|null,
     *     created_at: string|null,
     *     updated_at: string|null,
     * }
     */
    public function toArray(Request $request): array
    {
        return [
            /** @var int */
            'id' => $this->id,
            /** @var array{type: string, cash_transaction: array|null} */
            'source' => [
                'type' => $this->isManualSource() ? 'manual_cash' : 'receivable',
                'cash_transaction' => $this->relationLoaded('sourceCashTransaction') && $this->sourceCashTransaction
                    ? [
                        'id' => $this->sourceCashTransaction->id,
                        'code' => $this->sourceCashTransaction->code,
                        'category' => [
                            'value' => $this->sourceCashTransaction->category->value,
                            'label' => $this->sourceCashTransaction->category->label(),
                        ],
                        'direction' => [
                            'value' => $this->sourceCashTransaction->direction->value,
                            'label' => $this->sourceCashTransaction->direction->label(),
                        ],
                        'amount' => $this->sourceCashTransaction->amount,
                        'transaction_date' => $this->sourceCashTransaction->transaction_date?->toDateString(),
                        'note' => $this->sourceCashTransaction->note,
                        'created_by' => $this->sourceCashTransaction->relationLoaded('createdBy') && $this->sourceCashTransaction->createdBy
                            ? ['id' => $this->sourceCashTransaction->createdBy->id, 'name' => $this->sourceCashTransaction->createdBy->name]
                            : null,
                    ]
                    : null,
            ],
            /** @var string */
            'amount' => $this->amount,
            /** @var array|null */
            'receivable' => $this->relationLoaded('receivable') && $this->receivable
                ? [
                    'id' => $this->receivable->id,
                    'order' => $this->receivable->relationLoaded('order') && $this->receivable->order
                        ? ['id' => $this->receivable->order->id, 'code' => $this->receivable->order->code]
                        : null,
                    'og_ticket' => $this->receivable->relationLoaded('order')
                        && $this->receivable->order?->relationLoaded('quote')
                        && $this->receivable->order->quote?->relationLoaded('ogTicket')
                        && $this->receivable->order->quote->ogTicket
                        ? [
                            'id' => $this->receivable->order->quote->ogTicket->id,
                            'subject' => $this->receivable->order->quote->ogTicket->subject,
                            'requester_name' => $this->receivable->order->quote->ogTicket->requester_name,
                            'requester_phone' => $this->receivable->order->quote->ogTicket->requester_phone,
                            'apartment_name' => $this->receivable->order->quote->ogTicket->apartment_name,
                            'customer' => $this->receivable->order->quote->ogTicket->relationLoaded('customer')
                                && $this->receivable->order->quote->ogTicket->customer
                                ? [
                                    'id' => $this->receivable->order->quote->ogTicket->customer->id,
                                    'code' => $this->receivable->order->quote->ogTicket->customer->code,
                                    'full_name' => $this->receivable->order->quote->ogTicket->customer->full_name,
                                    'phone' => $this->receivable->order->quote->ogTicket->customer->phone,
                                ]
                                : null,
                        ]
                        : null,
                    'project' => $this->receivable->relationLoaded('project') && $this->receivable->project
                        ? ['id' => $this->receivable->project->id, 'name' => $this->receivable->project->name]
                        : null,
                    'amount' => $this->receivable->amount,
                    'paid_amount' => $this->receivable->paid_amount,
                    'status' => [
                        'value' => $this->receivable->status->value,
                        'label' => $this->receivable->status->label(),
                    ],
                ]
                : null,
            /** @var array|null */
            'payment_receipt' => $this->relationLoaded('paymentReceipt') && $this->paymentReceipt
                ? [
                    'id' => $this->paymentReceipt->id,
                    'type' => [
                        'value' => $this->paymentReceipt->type->value,
                        'label' => $this->paymentReceipt->type->label(),
                    ],
                    'amount' => $this->paymentReceipt->amount,
                    'payment_method' => [
                        'value' => $this->paymentReceipt->payment_method->value,
                        'label' => $this->paymentReceipt->payment_method->label(),
                    ],
                    'collected_by' => $this->paymentReceipt->relationLoaded('collectedBy') && $this->paymentReceipt->collectedBy
                        ? ['id' => $this->paymentReceipt->collectedBy->id, 'name' => $this->paymentReceipt->collectedBy->name]
                        : null,
                    'note' => $this->paymentReceipt->note,
                    'paid_at' => $this->paymentReceipt->paid_at?->toIso8601String(),
                ]
                : null,
            'status' => [
                'value' => $this->status->value,
                'label' => $this->status->label(),
            ],
            /** @var string|null */
            'reconciled_at' => $this->reconciled_at?->toIso8601String(),
            /** @var array{id: int, name: string}|null */
            'reconciled_by' => $this->relationLoaded('reconciledBy') && $this->reconciledBy
                ? ['id' => $this->reconciledBy->id, 'name' => $this->reconciledBy->name]
                : null,
            /** @var string|null */
            'note' => $this->note,
            /** @var string|null */
            'created_at' => $this->created_at?->toIso8601String(),
            /** @var string|null */
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
