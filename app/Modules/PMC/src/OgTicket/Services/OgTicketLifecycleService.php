<?php

namespace App\Modules\PMC\OgTicket\Services;

use App\Common\Services\BaseService;
use App\Modules\PMC\OgTicket\Contracts\OgTicketLifecycleServiceInterface;
use App\Modules\PMC\OgTicket\Enums\OgTicketStatus;
use App\Modules\PMC\OgTicket\ExternalServices\TicketExternalServiceInterface;
use App\Modules\PMC\OgTicket\Models\OgTicket;
use App\Modules\PMC\OgTicket\Repositories\OgTicketLifecycleSegmentRepository;
use App\Modules\PMC\OgTicket\Repositories\OgTicketRepository;
use App\Modules\PMC\Order\Enums\OrderStatus;
use App\Modules\PMC\Order\Models\Order;
use App\Modules\PMC\Quote\Enums\QuoteStatus;
use App\Modules\PMC\Quote\Models\Quote;
use App\Modules\PMC\Setting\Contracts\SystemSettingServiceInterface;

class OgTicketLifecycleService extends BaseService implements OgTicketLifecycleServiceInterface
{
    public function __construct(
        private OgTicketLifecycleSegmentRepository $repository,
        private OgTicketRepository $ogTicketRepository,
        private TicketExternalServiceInterface $ticketExternalService,
        private SystemSettingServiceInterface $settingService,
    ) {}

    /**
     * Tính ticket status dựa trên trạng thái quote + order.
     *
     * Quy tắc ưu tiên:
     * 1. Terminal (cancelled/completed) → giữ nguyên
     * 2. Có order active + quote approved → theo order status
     * 3. Có quote active → theo quote status
     * 4. Không có quote → giữ status hiện tại (manual workflow)
     */
    public function resolveTicketStatus(
        OgTicket $ogTicket,
        ?Quote $activeQuote,
        ?Order $activeOrder,
    ): OgTicketStatus {
        // 1. Terminal states — không thay đổi
        if ($ogTicket->status === OgTicketStatus::Cancelled) {
            return OgTicketStatus::Cancelled;
        }

        // 2. Order-driven (order tồn tại + quote approved)
        if ($activeOrder && $activeQuote?->status === QuoteStatus::Approved) {
            return match ($activeOrder->status) {
                OrderStatus::Completed => OgTicketStatus::Completed,
                OrderStatus::Accepted => OgTicketStatus::Accepted,
                OrderStatus::InProgress => OgTicketStatus::InProgress,
                OrderStatus::Cancelled => OgTicketStatus::Approved,
                default => OgTicketStatus::Ordered, // Draft, Confirmed
            };
        }

        // 3. Quote-driven
        if ($activeQuote) {
            return match ($activeQuote->status) {
                QuoteStatus::Approved => OgTicketStatus::Approved,
                QuoteStatus::ManagerRejected, QuoteStatus::ResidentRejected => OgTicketStatus::Rejected,
                default => OgTicketStatus::Quoted, // Draft, Sent, ManagerApproved
            };
        }

        // 4. Không có quote → giữ status hiện tại (received/assigned/surveying)
        return $ogTicket->status;
    }

    /**
     * Resolve ticket status rồi transition nếu khác status hiện tại.
     * Gọi sau mỗi thay đổi quote/order.
     */
    public function syncTicketStatusFromQuoteOrder(
        OgTicket $ogTicket,
        ?Quote $activeQuote,
        ?Order $activeOrder,
        ?string $note = null,
    ): void {
        $newStatus = $this->resolveTicketStatus($ogTicket, $activeQuote, $activeOrder);

        if ($newStatus === $ogTicket->status) {
            return;
        }

        $this->transition($ogTicket, $newStatus, $note);

        // Confirm pending cycle khi quote approved sau backtrack từ ordered/in_progress
        if ($activeQuote?->status === QuoteStatus::Approved && $activeOrder) {
            $activeSegment = $this->repository->findActiveSegment($ogTicket->id);
            if ($activeSegment) {
                $this->confirmPendingCycle($ogTicket->id, $activeSegment->cycle);
            }
        }
    }

    public function openFirst(OgTicket $ogTicket, ?int $assigneeId = null): void
    {
        $this->repository->create([
            'og_ticket_id' => $ogTicket->id,
            'status' => $ogTicket->status->value,
            'cycle' => 0,
            'started_at' => now(),
            'assignee_id' => $assigneeId,
        ]);
    }

    /**
     * Chuyển status OgTicket + ghi segment.
     * Đóng segment hiện tại (ended_at = now) + mở segment mới.
     * Tự xác định cycle (backtrack → cycle+1, forward → giữ cycle).
     * cycle=0: chưa phát sinh, cycle=1: phát sinh lần 1, cycle=2: phát sinh lần 2...
     */
    public function transition(
        OgTicket $ogTicket,
        OgTicketStatus $newStatus,
        ?string $note = null,
        ?int $assigneeId = null,
    ): void {
        $oldStatus = $ogTicket->status;

        // Skip nếu status không thay đổi (ví dụ tạo báo giá mới khi đã ở quoted)
        if ($oldStatus === $newStatus) {
            return;
        }

        $this->executeInTransaction(function () use ($ogTicket, $oldStatus, $newStatus, $note, $assigneeId): void {
            // 1. Đóng segment đang active (hoặc backfill cho OgTicket cũ chưa có segment)
            $activeSegment = $this->repository->findActiveSegment($ogTicket->id);
            $currentCycle = 0;

            if ($activeSegment) {
                $currentCycle = $activeSegment->cycle;
                $this->repository->closeSegment($activeSegment->id);
            } else {
                // OgTicket cũ chưa có segment → tạo segment cho status hiện tại rồi đóng
                $this->repository->create([
                    'og_ticket_id' => $ogTicket->id,
                    'status' => $oldStatus->value,
                    'cycle' => 0,
                    'started_at' => $ogTicket->created_at ?? now(),
                    'ended_at' => now(),
                ]);
            }

            // 2. Tính cycle + xác định cycle_confirmed
            [$cycle, $cycleConfirmed] = $this->determineCycle($oldStatus, $newStatus, $currentCycle);

            // 3. Mở segment mới
            $this->repository->create([
                'og_ticket_id' => $ogTicket->id,
                'status' => $newStatus->value,
                'cycle' => $cycle,
                'cycle_confirmed' => $cycleConfirmed,
                'started_at' => now(),
                'note' => $note,
                'assignee_id' => $assigneeId,
            ]);

            // 4. Update og_tickets.status + SLA completion deadline
            $updateData = ['status' => $newStatus->value];

            if ($newStatus === OgTicketStatus::Approved && ! $ogTicket->sla_completion_due_at) {
                $minutes = (int) $this->settingService->get('og_ticket', 'sla_completion_minutes', 1440);
                $updateData['sla_completion_due_at'] = now()->addMinutes($minutes);
            }

            if ($newStatus === OgTicketStatus::Completed) {
                $updateData['completed_at'] = now();
            } elseif ($oldStatus === OgTicketStatus::Completed) {
                $updateData['completed_at'] = null;
            }

            $this->ogTicketRepository->update($ogTicket->id, $updateData);
            $ogTicket->refresh();

            // 5. Sync status xuống ticket gốc (Platform/Ticket)
            $this->ticketExternalService->syncTicketStatus($ogTicket->ticket_id, $newStatus);
        });
    }

    /**
     * Confirm pending cycle segments khi quote được approved sau backtrack.
     */
    public function confirmPendingCycle(int $ogTicketId, int $cycle): void
    {
        $this->repository->confirmCycle($ogTicketId, $cycle);
    }

    /**
     * Xác định cycle + cycle_confirmed cho segment mới.
     *
     * @return array{0: int, 1: bool} [cycle, cycle_confirmed]
     */
    private function determineCycle(
        OgTicketStatus $oldStatus,
        OgTicketStatus $newStatus,
        int $currentCycle,
    ): array {
        // Cancelled, Rejected → luôn giữ cycle
        if ($newStatus === OgTicketStatus::Cancelled || $newStatus === OgTicketStatus::Rejected) {
            return [$currentCycle, true];
        }

        // Backtrack: newStatus.workflowIndex < oldStatus.workflowIndex → cycle+1
        if ($newStatus->workflowIndex() < $oldStatus->workflowIndex()) {
            // Từ ordered/in_progress về quoted → cycle pending (chờ quote approved)
            $isFromOrderedOrLater = $oldStatus->workflowIndex() >= OgTicketStatus::Ordered->workflowIndex();
            $cycleConfirmed = ! $isFromOrderedOrLater;

            return [$currentCycle + 1, $cycleConfirmed];
        }

        return [$currentCycle, true];
    }
}
