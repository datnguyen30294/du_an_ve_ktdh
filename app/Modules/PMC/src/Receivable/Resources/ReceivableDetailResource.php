<?php

namespace App\Modules\PMC\Receivable\Resources;

use App\Common\Resources\BaseResource;
use App\Modules\PMC\Receivable\Enums\ReceivableStatus;
use App\Modules\PMC\Receivable\Models\Receivable;
use App\Modules\PMC\Reconciliation\Enums\ReconciliationStatus;
use Illuminate\Http\Request;

/**
 * @mixin Receivable
 */
class ReceivableDetailResource extends BaseResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $totalPayments = $this->relationLoaded('reconciliations')
            ? $this->reconciliations->count()
            : $this->reconciliations()->count();
        $reconciledCount = $this->relationLoaded('reconciliations')
            ? $this->reconciliations->where('status', ReconciliationStatus::Reconciled)->count()
            : $this->reconciliations()->where('status', ReconciliationStatus::Reconciled->value)->count();

        $canComplete = $this->status === ReceivableStatus::Paid
            && $totalPayments > 0
            && $reconciledCount >= $totalPayments;

        return [
            /** @var int */
            'id' => $this->id,
            /** @var array{id: int, code: string, status: array{value: string, label: string}}|null */
            'order' => $this->relationLoaded('order') && $this->order
                ? [
                    'id' => $this->order->id,
                    'code' => $this->order->code,
                    'status' => [
                        'value' => $this->order->status->value,
                        'label' => $this->order->status->label(),
                    ],
                ]
                : null,
            /** @var array{id: int, subject: string, requester_name: string, requester_phone: string|null, apartment_name: string, customer: array{id: int, code: string|null, full_name: string, phone: string, email: string|null}|null}|null */
            'og_ticket' => $this->relationLoaded('order')
                && $this->order?->relationLoaded('quote')
                && $this->order->quote?->relationLoaded('ogTicket')
                && $this->order->quote->ogTicket
                ? [
                    'id' => $this->order->quote->ogTicket->id,
                    'subject' => $this->order->quote->ogTicket->subject,
                    'requester_name' => $this->order->quote->ogTicket->requester_name,
                    'requester_phone' => $this->order->quote->ogTicket->requester_phone,
                    'apartment_name' => $this->order->quote->ogTicket->apartment_name,
                    'customer' => $this->order->quote->ogTicket->relationLoaded('customer') && $this->order->quote->ogTicket->customer
                        ? [
                            'id' => $this->order->quote->ogTicket->customer->id,
                            'code' => $this->order->quote->ogTicket->customer->code,
                            'full_name' => $this->order->quote->ogTicket->customer->full_name,
                            'phone' => $this->order->quote->ogTicket->customer->phone,
                            'email' => $this->order->quote->ogTicket->customer->email,
                        ]
                        : null,
                ]
                : null,
            /** @var array{id: int, name: string}|null */
            'project' => $this->relationLoaded('project') && $this->project
                ? ['id' => $this->project->id, 'name' => $this->project->name]
                : null,
            /** @var string */
            'amount' => $this->amount,
            /** @var string */
            'paid_amount' => $this->paid_amount,
            /** @var string */
            'outstanding' => $this->outstanding,
            /** @var string */
            'overpaid_amount' => $this->overpaid_amount,
            'status' => [
                'value' => $this->status->value,
                'label' => $this->status->label(),
            ],
            /** @var bool */
            'can_collect' => in_array($this->status, ReceivableStatus::payable()),
            /** @var bool */
            'can_refund' => in_array($this->status, ReceivableStatus::refundable()),
            /** @var bool */
            'can_complete' => $canComplete,
            'reconciliation_progress' => [
                'total' => $totalPayments,
                'reconciled' => $reconciledCount,
                'pending' => $totalPayments - $reconciledCount,
            ],
            /** @var string */
            'due_date' => $this->due_date->toDateString(),
            /** @var int */
            'aging_days' => $this->aging_days,
            /** @var string|null */
            'issued_at' => $this->issued_at?->toIso8601String(),
            'payments' => $this->relationLoaded('payments')
                ? PaymentReceiptResource::collection($this->payments)
                : [],
            /** @var string|null */
            'created_at' => $this->created_at?->toIso8601String(),
            /** @var string|null */
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
