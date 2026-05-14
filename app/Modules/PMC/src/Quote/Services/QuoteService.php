<?php

namespace App\Modules\PMC\Quote\Services;

use App\Common\Exceptions\BusinessException;
use App\Common\Services\BaseService;
use App\Events\QuoteCreatedForTicket;
use App\Modules\PMC\Account\Repositories\AccountRepository;
use App\Modules\PMC\Catalog\Repositories\CatalogItemRepository;
use App\Modules\PMC\Commission\Repositories\CommissionConfigRepository;
use App\Modules\PMC\OgTicket\Contracts\OgTicketLifecycleServiceInterface;
use App\Modules\PMC\OgTicket\Enums\OgTicketStatus;
use App\Modules\PMC\OgTicket\ExternalServices\TicketExternalServiceInterface;
use App\Modules\PMC\OgTicket\Models\OgTicket;
use App\Modules\PMC\OgTicket\Repositories\OgTicketRepository;
use App\Modules\PMC\Order\Contracts\OrderServiceInterface;
use App\Modules\PMC\Order\Enums\OrderStatus;
use App\Modules\PMC\Quote\Contracts\QuoteServiceInterface;
use App\Modules\PMC\Quote\Enums\QuoteLineType;
use App\Modules\PMC\Quote\Enums\QuoteStatus;
use App\Modules\PMC\Quote\Enums\ResidentApprovedVia;
use App\Modules\PMC\Quote\Models\Quote;
use App\Modules\PMC\Quote\Repositories\QuoteRepository;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\Response;

class QuoteService extends BaseService implements QuoteServiceInterface
{
    public function __construct(
        protected QuoteRepository $repository,
        protected AccountRepository $accountRepository,
        protected CatalogItemRepository $catalogItemRepository,
        protected OrderServiceInterface $orderService,
        protected CommissionConfigRepository $commissionConfigRepository,
        protected OgTicketRepository $ogTicketRepository,
        protected OgTicketLifecycleServiceInterface $lifecycleService,
        protected TicketExternalServiceInterface $ticketExternalService,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function list(array $filters): LengthAwarePaginator
    {
        return $this->repository->list($filters);
    }

    public function findById(int $id): Quote
    {
        /** @var Quote */
        return $this->repository->findById($id, ['*'], ['ogTicket', 'ogTicket.customer:id,code,full_name,phone', 'managerApprovedBy', 'residentApprovedBy', 'lines', 'order']);
    }

    public function checkActive(int $ogTicketId): ?Quote
    {
        // Return the effective quote (ManagerApproved/Approved) if one exists,
        // otherwise fall back to latest active. This ensures the FE shows
        // the quote that actually drives ticket status and order.
        $activeQuote = $this->repository->findEffectiveByOgTicket($ogTicketId)
            ?? $this->repository->findLatestActiveByOgTicket($ogTicketId);

        if ($activeQuote) {
            $activeQuote->load('lines');
        }

        return $activeQuote;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Quote
    {
        return $this->executeInTransaction(function () use ($data): Quote {
            $ogTicketId = (int) $data['og_ticket_id'];

            $this->ensureTicketNotCompleted($ogTicketId);
            $this->validateServiceAmountAgainstCommission($ogTicketId, $data['lines']);

            $activeQuote = $this->repository->findActiveByOgTicket($ogTicketId);
            $effectiveQuote = $activeQuote ? $this->repository->findEffectiveByOgTicket($ogTicketId) : null;
            $isOldEffective = $effectiveQuote !== null;

            if ($activeQuote) {
                if (empty($data['replace_active'])) {
                    throw new BusinessException(
                        message: 'Ticket đã có báo giá active. Vui lòng xác nhận thay thế.',
                        errorCode: 'QUOTE_ACTIVE_EXISTS',
                        httpStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY,
                        context: ['active_quote_id' => $activeQuote->id],
                    );
                }

                $this->orderService->ensureCanReplaceQuote($ogTicketId);

                if ($isOldEffective) {
                    // Old quote is effective (ManagerApproved/Approved) → keep it active,
                    // only deactivate non-effective drafts (previous replacement attempts)
                    $this->repository->deactivateByOgTicketExcept($ogTicketId, $effectiveQuote->id);
                } else {
                    // Old quote is not effective (Draft/Sent) → deactivate all
                    $this->repository->deactivateByOgTicket($ogTicketId);
                }
            }

            $lines = $data['lines'];
            $totalAmount = $this->calculateTotalAmount($lines);

            /** @var Quote */
            $quote = $this->repository->create([
                'code' => $this->repository->generateCode(),
                'og_ticket_id' => $ogTicketId,
                'status' => $data['status'],
                'is_active' => true,
                'total_amount' => $totalAmount,
                'note' => $data['note'] ?? null,
            ]);

            $this->createLines($quote, $lines);

            // Re-link draft order to new quote only when old quote is NOT effective.
            // When old quote is effective, order stays linked to it until new quote
            // reaches ManagerApproved.
            if ($activeQuote && ! $isOldEffective) {
                $quote->load('lines');
                $this->orderService->relinkToActiveQuote($ogTicketId, $quote);
            }

            // Sync ticket status — use effective quote (if exists) so ticket status
            // is not downgraded by a draft replacement.
            if ($quote->ogTicket) {
                $statusQuote = $effectiveQuote ?? $quote;
                $activeOrder = $this->orderService->findActiveOrderByTicket($quote->ogTicket->id);
                $this->lifecycleService->syncTicketStatusFromQuoteOrder($quote->ogTicket, $statusQuote, $activeOrder);
            }

            $freshQuote = $this->findById($quote->id);
            $this->dispatchQuoteCreatedEvent($freshQuote);

            return $freshQuote;
        });
    }

    /**
     * Resolve ticket/customer info via ExternalService and dispatch a
     * QuoteCreatedForTicket event so a resident email can be sent
     * asynchronously. Failures here must never abort quote creation.
     */
    private function dispatchQuoteCreatedEvent(Quote $quote): void
    {
        try {
            if (! $quote->ogTicket || ! $quote->ogTicket->ticket_id) {
                return;
            }

            $info = $this->ticketExternalService->getNotificationInfo((int) $quote->ogTicket->ticket_id);

            if ($info === null) {
                return;
            }

            $lines = $quote->lines->map(fn ($line): array => [
                'name' => (string) $line->name,
                'quantity' => (int) $line->quantity,
                'unit' => $line->unit ? (string) $line->unit : null,
                'line_amount' => (float) $line->line_amount,
            ])->all();

            $tenantId = tenant('id');
            QuoteCreatedForTicket::dispatch($info['customer_id'], [
                'ticket_code' => $info['ticket_code'],
                'ticket_subject' => $info['ticket_subject'],
                'quote_code' => (string) $quote->code,
                'quote_total_amount' => (float) $quote->total_amount,
                'quote_lines' => $lines,
                'customer_name' => $info['customer_name'],
                'tenant_subdomain' => is_string($tenantId) ? $tenantId : null,
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(int $id, array $data): Quote
    {
        return $this->executeInTransaction(function () use ($id, $data): Quote {
            $quote = $this->findById($id);

            $this->ensureActive($quote);
            $this->ensureNotApproved($quote);
            $this->validateServiceAmountAgainstCommission($quote->og_ticket_id, $data['lines']);

            // Replace lines
            $quote->lines()->delete();
            $lines = $data['lines'];
            $totalAmount = $this->calculateTotalAmount($lines);
            $this->createLines($quote, $lines);

            $updateData = [
                'total_amount' => $totalAmount,
                'note' => $data['note'] ?? $quote->note,
            ];

            // Sửa báo giá active → luôn reset status về draft
            if ($quote->status !== QuoteStatus::Draft) {
                $updateData['status'] = QuoteStatus::Draft->value;
                $updateData['manager_approved_at'] = null;
                $updateData['manager_approved_by_id'] = null;
                $updateData['resident_approved_at'] = null;
                $updateData['resident_approved_via'] = null;
                $updateData['resident_approved_by_id'] = null;
            }

            $quote->update($updateData);

            return $this->findById($id);
        });
    }

    /**
     * Transition quote to a new status. State machine in QuoteStatus enum validates.
     *
     * @param  array<string, mixed>  $data  { status: string, note?: string }
     */
    public function transition(int $id, array $data): Quote
    {
        return $this->executeInTransaction(function () use ($id, $data): Quote {
            $quote = $this->findById($id);
            $targetStatus = QuoteStatus::from($data['status']);

            $this->ensureActive($quote);

            if (! $quote->status->canTransitionTo($targetStatus)) {
                throw new BusinessException(
                    message: "Không thể chuyển từ \"{$quote->status->label()}\" sang \"{$targetStatus->label()}\".",
                    errorCode: 'QUOTE_INVALID_TRANSITION',
                    httpStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY,
                    context: [
                        'current_status' => $quote->status->value,
                        'target_status' => $targetStatus->value,
                        'allowed' => array_map(fn (QuoteStatus $s) => $s->value, $quote->status->allowedTransitions()),
                    ],
                );
            }

            $updateData = ['status' => $targetStatus->value];

            // Side effects per target status
            match ($targetStatus) {
                QuoteStatus::ManagerApproved => $updateData = array_merge($updateData, [
                    'manager_approved_at' => now(),
                    'manager_approved_by_id' => auth()->id(),
                ]),
                QuoteStatus::Approved => $updateData = array_merge($updateData, [
                    'resident_approved_at' => now(),
                    'resident_approved_via' => auth()->id()
                        ? ResidentApprovedVia::AdminOnBehalf->value
                        : ResidentApprovedVia::ResidentSelf->value,
                    'resident_approved_by_id' => auth()->id(),
                ]),
                QuoteStatus::ManagerRejected, QuoteStatus::ResidentRejected => ! empty($data['note'])
                    ? $updateData['note'] = $data['note']
                    : null,
                default => null,
            };

            // When target is ManagerApproved, deactivate other effective quotes FIRST
            // to avoid violating the partial unique index (one effective per ticket).
            if ($targetStatus === QuoteStatus::ManagerApproved) {
                $this->repository->deactivateByOgTicketExcept($quote->og_ticket_id, $quote->id);
            }

            $quote->update($updateData);

            // Now re-link order to this newly effective quote.
            if ($targetStatus === QuoteStatus::ManagerApproved) {
                $quote->load('lines');
                $this->orderService->relinkToActiveQuote($quote->og_ticket_id, $quote);
            }

            // Sync ticket status — use effective quote so draft/rejected replacements
            // don't affect ticket status when an effective quote exists.
            if ($quote->ogTicket) {
                $effectiveQuote = $this->repository->findEffectiveByOgTicket($quote->og_ticket_id);
                $statusQuote = $effectiveQuote ?? $quote;
                $activeOrder = $this->orderService->findActiveOrderByTicket($quote->ogTicket->id);
                $this->lifecycleService->syncTicketStatusFromQuoteOrder($quote->ogTicket, $statusQuote, $activeOrder);
            }

            return $this->findById($id);
        });
    }

    public function delete(int $id): void
    {
        /** @var Quote */
        $quote = $this->repository->findById($id, ['*'], ['order', 'ogTicket']);

        if (! $quote->is_active) {
            throw new BusinessException(
                message: 'Báo giá đã ngưng hiệu lực.',
                errorCode: 'QUOTE_ALREADY_INACTIVE',
                httpStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        if ($quote->order && \in_array($quote->order->status, [
            OrderStatus::Confirmed,
            OrderStatus::InProgress,
            OrderStatus::Completed,
        ], true)) {
            throw new BusinessException(
                message: "Không thể xoá báo giá khi đơn hàng đang ở trạng thái \"{$quote->order->status->label()}\".",
                errorCode: 'QUOTE_ORDER_IN_PROGRESS',
                httpStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        $this->executeInTransaction(function () use ($quote): void {
            $quote->update(['is_active' => false]);
            $this->orderService->cancelByQuote($quote->id);

            // After deactivating this quote, sync ticket status from the remaining
            // effective quote (if any). This ensures deleting a draft replacement
            // does not leave the ticket in an inconsistent state.
            if ($quote->ogTicket) {
                $effectiveQuote = $this->repository->findEffectiveByOgTicket($quote->og_ticket_id);
                $activeOrder = $this->orderService->findActiveOrderByTicket($quote->og_ticket_id);
                $this->lifecycleService->syncTicketStatusFromQuoteOrder(
                    $quote->ogTicket,
                    $effectiveQuote,
                    $activeOrder,
                    'Báo giá nháp bị xoá',
                );
            }
        });
    }

    /**
     * Cancel all active quotes by OgTicket ID (cascade from OgTicket deletion).
     * Sets is_active = false, status = cancelled, then cascades to Order.
     */
    public function cancelByOgTicket(int $ogTicketId): void
    {
        $activeQuotes = $this->repository->findAllActiveByOgTicket($ogTicketId);

        if ($activeQuotes->isEmpty()) {
            return;
        }

        $this->executeInTransaction(function () use ($activeQuotes): void {
            foreach ($activeQuotes as $quote) {
                $quote->update([
                    'is_active' => false,
                    'status' => QuoteStatus::Cancelled->value,
                ]);
                $this->orderService->cancelByQuote($quote->id);
            }
        });
    }

    /**
     * @return array{can_delete: bool, message: string}
     */
    public function checkDelete(int $id): array
    {
        /** @var Quote */
        $quote = $this->repository->findById($id, ['*'], ['order']);

        if (! $quote->is_active) {
            return [
                'can_delete' => false,
                'message' => 'Báo giá đã ngưng hiệu lực.',
            ];
        }

        if ($quote->order && \in_array($quote->order->status, [
            OrderStatus::Confirmed,
            OrderStatus::InProgress,
            OrderStatus::Completed,
        ], true)) {
            return [
                'can_delete' => false,
                'message' => "Không thể xoá báo giá khi đơn hàng đang ở trạng thái \"{$quote->order->status->label()}\".",
            ];
        }

        return [
            'can_delete' => true,
            'message' => 'Có thể xoá báo giá này.',
        ];
    }

    /**
     * Create quote lines and calculate line_amount.
     *
     * @param  array<int, array<string, mixed>>  $lines
     */
    private function createLines(Quote $quote, array $lines): void
    {
        foreach ($lines as $line) {
            $unitPrice = (float) $line['unit_price'];
            $quantity = (int) $line['quantity'];
            $catalogItem = $this->catalogItemRepository->findById((int) $line['reference_id']);

            $purchasePrice = array_key_exists('purchase_price', $line) && $line['purchase_price'] !== null
                ? (float) $line['purchase_price']
                : ($catalogItem->purchase_price !== null ? (float) $catalogItem->purchase_price : null);

            $quote->lines()->create([
                'line_type' => $line['line_type'],
                'reference_id' => $line['reference_id'],
                'name' => $line['name'],
                'quantity' => $quantity,
                'unit' => $catalogItem->unit,
                'unit_price' => $unitPrice,
                'purchase_price' => $purchasePrice,
                'line_amount' => $unitPrice * $quantity,
            ]);
        }
    }

    /**
     * Calculate total amount from lines.
     *
     * @param  array<int, array<string, mixed>>  $lines
     */
    private function calculateTotalAmount(array $lines): float
    {
        $total = 0;

        foreach ($lines as $line) {
            $total += (float) $line['unit_price'] * (int) $line['quantity'];
        }

        return $total;
    }

    private function ensureActive(Quote $quote): void
    {
        if (! $quote->is_active) {
            throw new BusinessException(
                message: 'Báo giá đã bị thay thế, không thể thao tác.',
                errorCode: 'QUOTE_INACTIVE',
                httpStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }
    }

    private function ensureNotApproved(Quote $quote): void
    {
        if ($quote->status === QuoteStatus::Approved) {
            throw new BusinessException(
                message: 'Báo giá đã được cư dân chấp thuận, không thể chỉnh sửa.',
                errorCode: 'QUOTE_ALREADY_APPROVED',
                httpStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }
    }

    public function getVersionsByTicket(int $ogTicketId): \Illuminate\Database\Eloquent\Collection
    {
        return $this->repository->getByOgTicket($ogTicketId);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getAudits(int $id): array
    {
        /** @var Quote */
        $quote = $this->repository->findById($id);

        $audits = $quote->audits()
            ->with('user')
            ->latest()
            ->get();

        // Collect FK IDs to resolve in batch
        $accountIds = collect();

        foreach ($audits as $audit) {
            foreach (['old_values', 'new_values'] as $prop) {
                $values = $audit->{$prop} ?? [];
                if (isset($values['manager_approved_by_id'])) {
                    $accountIds->push($values['manager_approved_by_id']);
                }
            }
        }

        $accountNames = $this->accountRepository->pluckNamesByIds($accountIds->filter());

        return $audits->map(fn ($audit) => [
            'id' => $audit->id,
            'event' => $audit->event,
            'old_values' => $this->resolveAuditValues($audit->old_values, $accountNames),
            'new_values' => $this->resolveAuditValues($audit->new_values, $accountNames),
            'user' => $audit->user ? [
                'id' => $audit->user->id,
                'name' => $audit->user->name,
            ] : null,
            'created_at' => $audit->created_at?->toIso8601String(),
        ])->all();
    }

    /**
     * Resolve raw audit values: FK IDs → names, enum values → labels.
     *
     * @param  array<string, mixed>|null  $values
     * @param  Collection<int, string>  $accountNames
     * @return array<string, mixed>|null
     */
    private function resolveAuditValues(?array $values, Collection $accountNames): ?array
    {
        if (! $values) {
            return null;
        }

        $resolved = $values;

        if (isset($resolved['manager_approved_by_id'])) {
            $id = $resolved['manager_approved_by_id'];
            $resolved['manager_approved_by_id'] = $accountNames->get($id, $id);
        }

        if (isset($resolved['status'])) {
            $status = QuoteStatus::tryFrom($resolved['status']);
            $resolved['status'] = $status?->label() ?? $resolved['status'];
        }

        return $resolved;
    }

    /**
     * Get total fixed commission amount for the project linked to an OgTicket.
     */
    public function getCommissionFixedTotal(int $ogTicketId): float
    {
        /** @var OgTicket */
        $ogTicket = $this->ogTicketRepository->findById($ogTicketId);

        if (! $ogTicket->project_id) {
            return 0;
        }

        return $this->commissionConfigRepository->getTotalFixedByProject($ogTicket->project_id);
    }

    /**
     * Block quote creation if the ticket or its order is already completed.
     */
    private function ensureTicketNotCompleted(int $ogTicketId): void
    {
        /** @var OgTicket */
        $ogTicket = $this->ogTicketRepository->findById($ogTicketId);

        if ($ogTicket->status === OgTicketStatus::Completed) {
            throw new BusinessException(
                message: 'Không thể tạo báo giá cho ticket đã hoàn thành.',
                errorCode: 'TICKET_COMPLETED',
                httpStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        $this->orderService->ensureOrderNotCompleted($ogTicketId);
    }

    /**
     * Validate that total service + adhoc amount >= project's commission fixed total.
     *
     * @param  array<int, array<string, mixed>>  $lines
     */
    private function validateServiceAmountAgainstCommission(int $ogTicketId, array $lines): void
    {
        $fixedTotal = $this->getCommissionFixedTotal($ogTicketId);

        if ($fixedTotal <= 0) {
            return;
        }

        $serviceAdhocTotal = 0;

        foreach ($lines as $line) {
            if (\in_array($line['line_type'], [QuoteLineType::Service->value, QuoteLineType::Adhoc->value], true)) {
                $serviceAdhocTotal += (float) $line['unit_price'] * (int) $line['quantity'];
            }
        }

        if ($serviceAdhocTotal < $fixedTotal) {
            throw new BusinessException(
                message: 'Tổng tiền dịch vụ + tùy chọn ('.number_format($serviceAdhocTotal, 0, ',', '.').' đ) phải >= tổng tiền cố định chiết khấu dự án ('.number_format($fixedTotal, 0, ',', '.').' đ).',
                errorCode: 'QUOTE_SERVICE_BELOW_COMMISSION_FIXED',
                httpStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY,
                context: [
                    'service_adhoc_total' => $serviceAdhocTotal,
                    'commission_fixed_total' => $fixedTotal,
                ],
            );
        }
    }
}
