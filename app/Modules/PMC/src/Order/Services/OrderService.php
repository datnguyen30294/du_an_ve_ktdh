<?php

namespace App\Modules\PMC\Order\Services;

use App\Common\Exceptions\BusinessException;
use App\Common\Services\BaseService;
use App\Modules\PMC\Account\Repositories\AccountRepository;
use App\Modules\PMC\OgTicket\Contracts\OgTicketLifecycleServiceInterface;
use App\Modules\PMC\Order\Contracts\OrderServiceInterface;
use App\Modules\PMC\Order\Enums\OrderStatus;
use App\Modules\PMC\Order\Models\Order;
use App\Modules\PMC\Order\Repositories\OrderLineRepository;
use App\Modules\PMC\Order\Repositories\OrderRepository;
use App\Modules\PMC\Quote\Enums\QuoteLineType;
use App\Modules\PMC\Quote\Enums\QuoteStatus;
use App\Modules\PMC\Quote\Models\Quote;
use App\Modules\PMC\Quote\Repositories\QuoteRepository;
use App\Modules\PMC\Receivable\Contracts\ReceivableServiceInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Symfony\Component\HttpFoundation\Response;

class OrderService extends BaseService implements OrderServiceInterface
{
    public function __construct(
        protected OrderRepository $repository,
        protected OrderLineRepository $orderLineRepository,
        protected QuoteRepository $quoteRepository,
        protected OgTicketLifecycleServiceInterface $lifecycleService,
        protected ReceivableServiceInterface $receivableService,
        protected AccountRepository $accountRepository,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function list(array $filters): LengthAwarePaginator
    {
        return $this->repository->list($filters);
    }

    public function findById(int $id): Order
    {
        /** @var Order */
        return $this->repository->findById($id, ['*'], ['quote.ogTicket', 'quote.ogTicket.customer:id,code,full_name,phone', 'lines.advancePayer', 'lines.advancePaymentRecords', 'commissionOverrides']);
    }

    /**
     * @return Collection<int, \App\Modules\PMC\Quote\Models\Quote>
     */
    public function availableQuotes(): Collection
    {
        $excludeTicketIds = $this->repository->getTicketIdsWithActiveOrder();

        return $this->quoteRepository->findActiveExcludingTickets($excludeTicketIds);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Order
    {
        return $this->executeInTransaction(function () use ($data): Order {
            $quoteId = (int) $data['quote_id'];

            /** @var \App\Modules\PMC\Quote\Models\Quote */
            $quote = $this->quoteRepository->findById($quoteId, ['*'], ['lines', 'ogTicket']);

            // Validate quote is active
            if (! $quote->is_active) {
                throw new BusinessException(
                    message: 'Chỉ có thể tạo đơn hàng từ báo giá đang hoạt động.',
                    errorCode: 'ORDER_QUOTE_NOT_ELIGIBLE',
                    httpStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY,
                );
            }

            // Validate no active order for this ticket
            if ($this->repository->hasActiveOrder($quote->og_ticket_id)) {
                throw new BusinessException(
                    message: 'Báo giá này đã có đơn hàng.',
                    errorCode: 'ORDER_ALREADY_EXISTS',
                    httpStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY,
                );
            }

            // Calculate total from quote lines
            $totalAmount = $quote->lines->sum('line_amount');

            /** @var Order */
            $order = $this->repository->create([
                'code' => $this->repository->generateCode(),
                'quote_id' => $quoteId,
                'status' => OrderStatus::Draft->value,
                'total_amount' => $totalAmount,
                'note' => $data['note'] ?? null,
            ]);

            // Copy QuoteLines → OrderLines
            $this->copyLinesFromQuote($order, $quote);

            // Sync ticket status (order mới → ordered)
            if ($quote->ogTicket) {
                $this->lifecycleService->syncTicketStatusFromQuoteOrder($quote->ogTicket, $quote, $order);
            }

            return $this->findById($order->id);
        });
    }

    /**
     * Update order note. Can be updated at any status.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(int $id, array $data): Order
    {
        $order = $this->findById($id);

        $order->update([
            'note' => array_key_exists('note', $data) ? $data['note'] : $order->note,
        ]);

        return $this->findById($id);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function transition(int $id, array $data): Order
    {
        return $this->executeInTransaction(function () use ($id, $data): Order {
            $order = $this->findById($id);
            $targetStatus = OrderStatus::from($data['status']);

            if (! $order->status->canTransitionTo($targetStatus)) {
                throw new BusinessException(
                    message: "Không thể chuyển từ \"{$order->status->label()}\" sang \"{$targetStatus->label()}\".",
                    errorCode: 'ORDER_INVALID_TRANSITION',
                    httpStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY,
                    context: [
                        'current_status' => $order->status->value,
                        'target_status' => $targetStatus->value,
                        'allowed' => array_map(fn (OrderStatus $s) => $s->value, $order->status->allowedTransitions()),
                    ],
                );
            }

            // Non-cancel transitions require quote to be approved
            if ($targetStatus !== OrderStatus::Cancelled) {
                $quote = $order->quote;
                if (! $quote || $quote->status !== QuoteStatus::Approved) {
                    throw new BusinessException(
                        message: 'Không thể chuyển trạng thái khi báo giá chưa được chấp thuận.',
                        errorCode: 'ORDER_QUOTE_NOT_APPROVED',
                        httpStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY,
                    );
                }
            }

            $updateData = ['status' => $targetStatus->value];
            if ($targetStatus === OrderStatus::Completed && $order->completed_at === null) {
                $updateData['completed_at'] = now();
            }
            $order->update($updateData);

            // Receivable side effects
            if ($targetStatus === OrderStatus::Confirmed) {
                $this->receivableService->createFromOrder($order);
            }

            if ($targetStatus === OrderStatus::Cancelled) {
                $this->receivableService->handleOrderCancelled($order);
            }

            // Sync ticket status theo order thay đổi
            $ogTicket = $order->quote?->ogTicket;
            if ($ogTicket) {
                $note = $targetStatus === OrderStatus::Cancelled ? 'Đơn hàng bị huỷ' : null;
                $this->lifecycleService->syncTicketStatusFromQuoteOrder($ogTicket, $order->quote, $order, $note);
            }

            return $this->findById($id);
        });
    }

    public function delete(int $id): void
    {
        $this->executeInTransaction(function () use ($id): void {
            $order = $this->findById($id);

            $this->ensureDraft($order, 'xoá');

            $ogTicket = $order->quote?->ogTicket;
            $quote = $order->quote;
            $order->delete();

            // Sync ticket status (order xoá → approved nếu quote approved)
            if ($ogTicket && $quote) {
                $this->lifecycleService->syncTicketStatusFromQuoteOrder($ogTicket, $quote, null, 'Đơn hàng bị xoá');
            }
        });
    }

    /**
     * @return array{can_delete: bool, message: string}
     */
    public function checkDelete(int $id): array
    {
        /** @var Order */
        $order = $this->repository->findById($id);

        if ($order->status !== OrderStatus::Draft) {
            return [
                'can_delete' => false,
                'message' => 'Không thể xoá đơn hàng đã xác nhận hoặc đang thực hiện.',
            ];
        }

        return [
            'can_delete' => true,
            'message' => 'Có thể xoá đơn hàng này.',
        ];
    }

    /**
     * Cancel order by quote ID (cascade from Quote deletion).
     */
    public function cancelByQuote(int $quoteId): void
    {
        $order = $this->repository->findByQuoteId($quoteId);

        if (! $order || $order->status === OrderStatus::Cancelled) {
            return;
        }

        $order->update(['status' => OrderStatus::Cancelled->value]);
    }

    /**
     * Ensure that the ticket's order (if any) allows quote replacement.
     * Throws if a non-draft order exists.
     */
    public function findActiveOrderByTicket(int $ogTicketId): ?Order
    {
        return $this->repository->findActiveOrder($ogTicketId);
    }

    public function ensureOrderNotCompleted(int $ogTicketId): void
    {
        $order = $this->repository->findActiveOrder($ogTicketId);

        if ($order && $order->status === OrderStatus::Completed) {
            throw new BusinessException(
                message: 'Không thể tạo báo giá khi đơn hàng đã hoàn thành.',
                errorCode: 'ORDER_COMPLETED',
                httpStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }
    }

    public function ensureCanReplaceQuote(int $ogTicketId): void
    {
        $order = $this->repository->findActiveOrder($ogTicketId);

        if ($order && $order->status === OrderStatus::Completed) {
            throw new BusinessException(
                message: 'Không thể thay thế báo giá khi đơn hàng đã hoàn thành.',
                errorCode: 'ORDER_COMPLETED_FOR_RELINK',
                httpStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }
    }

    /**
     * Re-link an active order to a new active quote.
     * Resets order to draft and re-syncs lines from the new quote.
     */
    public function relinkToActiveQuote(int $ogTicketId, Quote $newQuote): void
    {
        $order = $this->repository->findActiveOrder($ogTicketId);

        if (! $order) {
            return;
        }

        // Re-link quote, reset to draft, re-sync lines
        $order->lines()->delete();

        $order->update([
            'quote_id' => $newQuote->id,
            'status' => OrderStatus::Draft->value,
            'total_amount' => $newQuote->lines->sum('line_amount'),
        ]);

        $this->copyLinesFromQuote($order, $newQuote);

        // Sync receivable amount so the KPI stays aligned with the new quote.
        $this->receivableService->syncAmountFromOrder($order);
    }

    /**
     * Set or clear the advance payer on a specific order line.
     */
    public function setAdvancePayer(int $orderId, int $lineId, ?int $advancePayerId): Order
    {
        $line = $this->orderLineRepository->findInOrder($orderId, $lineId);

        if (! $line) {
            throw new BusinessException(
                message: 'Không tìm thấy dòng đơn hàng.',
                errorCode: 'ORDER_LINE_NOT_FOUND',
                httpStatusCode: Response::HTTP_NOT_FOUND,
            );
        }

        if ($line->line_type !== QuoteLineType::Material) {
            throw new BusinessException(
                message: 'Chỉ có thể gán người ứng tiền cho dòng vật tư.',
                errorCode: 'ADVANCE_PAYER_NOT_MATERIAL_LINE',
                httpStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        $this->orderLineRepository->update($line->id, ['advance_payer_id' => $advancePayerId]);

        return $this->findById($orderId);
    }

    /**
     * Update unit_price and purchase_price on a specific order line.
     * Recalculates line_amount for the row, order total_amount, and
     * syncs the linked receivable (if any).
     *
     * @param  array{unit_price: float|int|string, purchase_price?: float|int|string|null}  $data
     */
    public function updateLinePrices(int $orderId, int $lineId, array $data): Order
    {
        return $this->executeInTransaction(function () use ($orderId, $lineId, $data): Order {
            $order = $this->repository->findById($orderId);

            if ($order->status === OrderStatus::Cancelled) {
                throw new BusinessException(
                    message: 'Không thể sửa giá trên đơn hàng đã huỷ.',
                    errorCode: 'ORDER_CANCELLED',
                    httpStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY,
                );
            }

            if ($order->isFinanciallyLocked()) {
                throw new BusinessException(
                    message: 'Đơn hàng đã chốt kỳ kế toán, không thể sửa giá.',
                    errorCode: 'ORDER_FINANCIALLY_LOCKED',
                    httpStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY,
                );
            }

            $line = $this->orderLineRepository->findInOrder($orderId, $lineId);

            if (! $line) {
                throw new BusinessException(
                    message: 'Không tìm thấy dòng đơn hàng.',
                    errorCode: 'ORDER_LINE_NOT_FOUND',
                    httpStatusCode: Response::HTTP_NOT_FOUND,
                );
            }

            $unitPrice = (float) $data['unit_price'];
            $purchasePrice = array_key_exists('purchase_price', $data) && $data['purchase_price'] !== null
                ? (float) $data['purchase_price']
                : null;

            $this->orderLineRepository->update($line->id, [
                'unit_price' => $unitPrice,
                'purchase_price' => $purchasePrice,
                'line_amount' => $unitPrice * $line->quantity,
            ]);

            // Recalculate order total from all lines
            $newTotal = $this->orderLineRepository->sumOrderTotal($orderId);
            $this->repository->update($orderId, ['total_amount' => $newTotal]);

            // Sync linked receivable KPI (if any) to match the new order total
            $order->refresh();
            $this->receivableService->syncAmountFromOrder($order);

            return $this->findById($orderId);
        });
    }

    /**
     * List active accounts (candidates for advance payer selection) with optional search.
     *
     * @return Collection<int, \App\Modules\PMC\Account\Models\Account>
     */
    public function listActiveAccounts(?string $search = null): Collection
    {
        return $this->accountRepository->listActive($search);
    }

    private function copyLinesFromQuote(Order $order, Quote $quote): void
    {
        $order->lines()->createMany(
            $quote->lines->map(fn ($line) => [
                'line_type' => $line->line_type->value,
                'reference_id' => $line->reference_id,
                'name' => $line->name,
                'quantity' => $line->quantity,
                'unit' => $line->unit,
                'unit_price' => $line->unit_price,
                'purchase_price' => $line->purchase_price,
                'line_amount' => $line->line_amount,
            ])->all()
        );
    }

    private function ensureDraft(Order $order, string $action): void
    {
        if ($order->status !== OrderStatus::Draft) {
            throw new BusinessException(
                message: "Chỉ có thể {$action} đơn hàng ở trạng thái \"Nháp\".",
                errorCode: 'ORDER_NOT_DRAFT',
                httpStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }
    }
}
