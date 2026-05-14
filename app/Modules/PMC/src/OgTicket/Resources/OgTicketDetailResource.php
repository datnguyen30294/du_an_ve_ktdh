<?php

namespace App\Modules\PMC\OgTicket\Resources;

use App\Common\Resources\AttachmentResource;
use App\Common\Resources\BaseResource;
use App\Modules\PMC\OgTicket\Models\OgTicket;
use App\Modules\PMC\Reconciliation\Enums\ReconciliationStatus;
use Illuminate\Http\Request;

/**
 * @mixin OgTicket
 */
class OgTicketDetailResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        $order = $this->relationLoaded('activeQuote') && $this->activeQuote
            && $this->activeQuote->relationLoaded('order')
            ? $this->activeQuote->order
            : null;

        $receivable = $order && $order->relationLoaded('receivable')
            ? $order->receivable
            : null;

        return [
            /** @var int */
            'id' => $this->id,
            /** @var int */
            'ticket_id' => $this->ticket_id,
            /** @var string|null */
            'code' => $this->relationLoaded('ticket') && $this->ticket
                ? $this->ticket->code
                : null,
            'subject' => $this->subject,
            'requester_name' => $this->requester_name,
            'requester_phone' => $this->requester_phone,
            /** @var array{id: int, code: string|null, full_name: string, phone: string, email: string|null}|null */
            'customer' => $this->relationLoaded('customer') && $this->customer
                ? [
                    'id' => $this->customer->id,
                    'code' => $this->customer->code,
                    'full_name' => $this->customer->full_name,
                    'phone' => $this->customer->phone,
                    'email' => $this->customer->email,
                ]
                : null,
            'status' => [
                'value' => $this->status->value,
                'label' => $this->status->label(),
            ],
            'priority' => [
                'value' => $this->priority->value,
                'label' => $this->priority->label(),
            ],
            /** @var array{id: int, name: string}|null */
            'project' => $this->relationLoaded('project') && $this->project
                ? ['id' => $this->project->id, 'name' => $this->project->name]
                : null,
            /** @var string|null */
            'received_at' => $this->received_at?->toIso8601String(),
            /** @var array{id: int, name: string}|null */
            'received_by' => $this->relationLoaded('receivedBy') && $this->receivedBy
                ? ['id' => $this->receivedBy->id, 'name' => $this->receivedBy->name]
                : null,
            /** @var array<int, array{id: int, name: string, avatar_url: string|null, capability_rating: int|null}> */
            'assignees' => $this->relationLoaded('assignees')
                ? $this->assignees->map(fn ($a) => [
                    'id' => $a->id,
                    'name' => $a->name,
                    'avatar_url' => $a->avatar_url,
                    'capability_rating' => $a->capability_rating !== null ? (int) $a->capability_rating : null,
                ])->values()->all()
                : [],
            /** @var array<int, array{id: int, name: string}> */
            'categories' => $this->relationLoaded('categories')
                ? $this->categories->map(fn ($c) => ['id' => $c->id, 'name' => $c->name])->values()->all()
                : [],
            /** @var string|null */
            'sla_quote_due_at' => $this->sla_quote_due_at?->toIso8601String(),
            /** @var string|null */
            'sla_completion_due_at' => $this->sla_completion_due_at?->toIso8601String(),
            /** @var string|null */
            'created_at' => $this->created_at?->toIso8601String(),
            /** @var bool */
            'is_from_pool' => $this->relationLoaded('ticket') && $this->ticket
                ? (bool) $this->ticket->is_from_pool
                : true,
            'ticket' => $this->buildTicketData(),
            /** @var string|null */
            'description' => $this->description,
            /** @var string|null */
            'address' => $this->address,
            /** @var string|null */
            'latitude' => $this->latitude,
            /** @var string|null */
            'longitude' => $this->longitude,
            /** @var string|null */
            'apartment_name' => $this->apartment_name,
            'channel' => [
                'value' => $this->channel->value,
                'label' => $this->channel->label(),
            ],
            /** @var string|null */
            'internal_note' => $this->internal_note,
            /** @var AttachmentResource[] */
            'attachments' => $this->relationLoaded('attachments')
                ? AttachmentResource::collection($this->attachments)
                : [],
            /** @var int|null */
            'resident_rating' => $this->resident_rating,
            /** @var string|null */
            'resident_rating_comment' => $this->resident_rating_comment,
            /** @var string|null */
            'resident_rated_at' => $this->resident_rated_at?->toIso8601String(),
            /** @var array{value: string, label: string, color: string, amount: string|null, paid_amount: string|null, receivable_id: int|null, order_id: int|null}|null */
            'payment_status' => $receivable
                ? [
                    'value' => $receivable->status->value,
                    'label' => $receivable->status->label(),
                    'color' => $receivable->status->color(),
                    'amount' => $receivable->amount !== null ? (string) $receivable->amount : null,
                    'paid_amount' => $receivable->paid_amount !== null ? (string) $receivable->paid_amount : null,
                    'receivable_id' => $receivable->id,
                    'order_id' => $order?->id,
                ]
                : null,
            /** @var array{value: string, label: string, color: string, receivable_id: int|null, amount: string, reconciled_amount: string, pending_amount: string, count_total: int, count_reconciled: int}|null */
            'reconciliation_status' => $receivable
                ? $this->buildReconciliationStatus($receivable)
                : null,
            /** @var string|null */
            'updated_at' => $this->updated_at?->toIso8601String(),
            'lifecycle_segments' => $this->relationLoaded('lifecycleSegments')
                ? OgTicketLifecycleSegmentResource::collection($this->lifecycleSegments)
                : [],
            /** @var list<array{id: int, subject: string, description: string, requester_name: string, attachments: AttachmentResource[], created_at: string|null}> */
            'warranty_requests' => $this->relationLoaded('warrantyRequests')
                ? $this->warrantyRequests->map(fn ($wr) => [
                    'id' => $wr->id,
                    'subject' => $wr->subject,
                    'description' => $wr->description,
                    'requester_name' => $wr->requester_name,
                    'attachments' => $wr->relationLoaded('attachments')
                        ? AttachmentResource::collection($wr->attachments)
                        : [],
                    'created_at' => $wr->created_at?->toIso8601String(),
                ])->all()
                : [],
        ];
    }

    /**
     * Build reconciliation_status from FinancialReconciliation rows linked to the receivable.
     *
     * Progress = SUM(amount WHERE status=reconciled) / receivable.amount
     * States:
     *  - none (0%)          → neutral "Chưa đối soát"
     *  - partial (0%<x<100) → warning "Đối soát 1 phần"
     *  - full (>=100%)      → success "Đã đối soát đủ"
     *
     * @return array{value: string, label: string, color: string, receivable_id: int|null, amount: string, reconciled_amount: string, pending_amount: string, count_total: int, count_reconciled: int}
     */
    private function buildReconciliationStatus(
        \App\Modules\PMC\Receivable\Models\Receivable $receivable,
    ): array {
        $total = (float) ($receivable->amount ?? 0);
        $reconciled = 0.0;
        $pending = 0.0;
        $countReconciled = 0;
        $countTotal = 0;

        if ($receivable->relationLoaded('reconciliations')) {
            foreach ($receivable->reconciliations as $rec) {
                $countTotal++;
                if ($rec->status === ReconciliationStatus::Reconciled) {
                    $reconciled += (float) $rec->amount;
                    $countReconciled++;
                } elseif ($rec->status === ReconciliationStatus::Pending) {
                    $pending += (float) $rec->amount;
                }
            }
        }

        if ($reconciled <= 0) {
            $value = 'none';
            $label = 'Chưa đối soát';
            $color = 'neutral';
        } elseif ($total > 0 && $reconciled >= $total - 0.0001) {
            $value = 'full';
            $label = 'Đã đối soát đủ';
            $color = 'success';
        } else {
            $value = 'partial';
            $label = 'Đối soát 1 phần';
            $color = 'warning';
        }

        return [
            'value' => $value,
            'label' => $label,
            'color' => $color,
            'receivable_id' => $receivable->id,
            'amount' => number_format($total, 2, '.', ''),
            'reconciled_amount' => number_format($reconciled, 2, '.', ''),
            'pending_amount' => number_format($pending, 2, '.', ''),
            'count_total' => $countTotal,
            'count_reconciled' => $countReconciled,
        ];
    }

    /**
     * @return array{code: string, subject: string, requester_name: string, requester_phone: string, description: string|null, address: string|null, latitude: string|null, longitude: string|null, status: array{value: string, label: string}, channel: array{value: string, label: string}, attachments: AttachmentResource[], created_at: string|null}|null
     */
    private function buildTicketData(): ?array
    {
        if (! $this->relationLoaded('ticket') || ! $this->ticket) {
            return null;
        }

        return [
            'code' => $this->ticket->code,
            'subject' => $this->ticket->subject,
            'requester_name' => $this->ticket->requester_name,
            'requester_phone' => $this->ticket->requester_phone,
            /** @var string|null */
            'description' => $this->ticket->description,
            /** @var string|null */
            'address' => $this->ticket->address,
            /** @var string|null */
            'latitude' => $this->ticket->latitude,
            /** @var string|null */
            'longitude' => $this->ticket->longitude,
            'status' => [
                'value' => $this->ticket->status->value,
                'label' => $this->ticket->status->label(),
            ],
            'channel' => [
                'value' => $this->ticket->channel->value,
                'label' => $this->ticket->channel->label(),
            ],
            /** @var AttachmentResource[] */
            'attachments' => $this->ticket->relationLoaded('attachments')
                ? AttachmentResource::collection($this->ticket->attachments)
                : [],
            /** @var string|null */
            'created_at' => $this->ticket->created_at?->toIso8601String(),
        ];
    }
}
