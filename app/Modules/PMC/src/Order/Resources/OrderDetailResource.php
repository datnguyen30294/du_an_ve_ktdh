<?php

namespace App\Modules\PMC\Order\Resources;

use App\Common\Resources\BaseResource;
use App\Modules\PMC\Commission\Repositories\CommissionConfigRepository;
use App\Modules\PMC\Order\Models\Order;
use App\Modules\PMC\Quote\Enums\QuoteLineType;
use Illuminate\Http\Request;

/**
 * @mixin Order
 */
class OrderDetailResource extends BaseResource
{
    /**
     * @return array{
     *     id: int,
     *     code: string,
     *     status: array{value: string, label: string},
     *     quote: array{id: int, code: string, status: array{value: string, label: string}}|null,
     *     og_ticket: array{id: int, subject: string, requester_name: string, project_id: int|null, customer: array{id: int, code: string|null, full_name: string, phone: string}|null}|null,
     *     total_amount: string,
     *     note: string|null,
     *     lines: list<array{id: int, line_type: array{value: string, label: string}, reference_id: int, name: string, quantity: int, unit: string, unit_price: string, line_amount: string}>,
     *     created_at: string|null,
     *     updated_at: string|null,
     * }
     */
    public function toArray(Request $request): array
    {
        return [
            /** @var int */
            'id' => $this->id,
            /** @var string */
            'code' => $this->code,
            'status' => [
                'value' => $this->status->value,
                'label' => $this->status->label(),
            ],
            /** @var array{id: int, code: string, status: array{value: string, label: string}}|null */
            'quote' => $this->relationLoaded('quote') && $this->quote
                ? [
                    'id' => $this->quote->id,
                    'code' => $this->quote->code,
                    'status' => [
                        'value' => $this->quote->status->value,
                        'label' => $this->quote->status->label(),
                    ],
                ]
                : null,
            /** @var array{id: int, subject: string, requester_name: string, project_id: int|null, customer: array{id: int, code: string|null, full_name: string, phone: string}|null}|null */
            'og_ticket' => $this->relationLoaded('quote') && $this->quote?->relationLoaded('ogTicket') && $this->quote->ogTicket
                ? [
                    'id' => $this->quote->ogTicket->id,
                    'subject' => $this->quote->ogTicket->subject,
                    'requester_name' => $this->quote->ogTicket->requester_name,
                    'project_id' => $this->quote->ogTicket->project_id,
                    'customer' => $this->quote->ogTicket->relationLoaded('customer') && $this->quote->ogTicket->customer
                        ? [
                            'id' => $this->quote->ogTicket->customer->id,
                            'code' => $this->quote->ogTicket->customer->code,
                            'full_name' => $this->quote->ogTicket->customer->full_name,
                            'phone' => $this->quote->ogTicket->customer->phone,
                        ]
                        : null,
                ]
                : null,
            /** @var string */
            'total_amount' => $this->total_amount,
            /** @var string|null */
            'note' => $this->note,
            /** @var OrderLineResource[] */
            'lines' => $this->relationLoaded('lines')
                ? OrderLineResource::collection($this->lines)
                : [],
            /** @var string */
            'commissionable_total' => $this->calculateCommissionableTotal(),
            /** @var bool */
            'has_commission_overrides' => $this->relationLoaded('commissionOverrides')
                && $this->commissionOverrides->isNotEmpty(),
            /** @var bool */
            'is_adjuster' => $this->checkIsAdjuster(),
            /** @var bool */
            'is_financially_locked' => $this->isFinanciallyLocked(),
            /** @var string|null */
            'created_at' => $this->created_at?->toIso8601String(),
            /** @var string|null */
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    private function calculateCommissionableTotal(): string
    {
        if (! $this->relationLoaded('lines')) {
            return '0.00';
        }

        $total = $this->lines
            ->filter(fn ($line) => in_array($line->line_type, [QuoteLineType::Service, QuoteLineType::Adhoc]))
            ->sum('line_amount');

        return number_format((float) $total, 2, '.', '');
    }

    private function checkIsAdjuster(): bool
    {
        $accountId = (int) auth()->id();
        if (! $accountId) {
            return false;
        }

        $projectId = $this->quote?->ogTicket?->project_id;
        if (! $projectId) {
            return false;
        }

        return app(CommissionConfigRepository::class)->hasAdjuster($accountId, $projectId);
    }
}
