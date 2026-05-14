<?php

namespace App\Modules\PMC\OgTicket\Contracts;

use App\Modules\PMC\OgTicket\Enums\OgTicketStatus;
use App\Modules\PMC\OgTicket\Models\OgTicket;
use App\Modules\PMC\Order\Models\Order;
use App\Modules\PMC\Quote\Models\Quote;

interface OgTicketLifecycleServiceInterface
{
    /**
     * Tính ticket status dựa trên trạng thái quote + order.
     */
    public function resolveTicketStatus(
        OgTicket $ogTicket,
        ?Quote $activeQuote,
        ?Order $activeOrder,
    ): OgTicketStatus;

    /**
     * Resolve ticket status rồi transition nếu khác status hiện tại.
     */
    public function syncTicketStatusFromQuoteOrder(
        OgTicket $ogTicket,
        ?Quote $activeQuote,
        ?Order $activeOrder,
        ?string $note = null,
    ): void;

    /**
     * Mở segment đầu tiên khi tạo OgTicket (claim).
     */
    public function openFirst(OgTicket $ogTicket, ?int $assigneeId = null): void;

    /**
     * Chuyển status OgTicket + ghi segment.
     */
    public function transition(
        OgTicket $ogTicket,
        OgTicketStatus $newStatus,
        ?string $note = null,
        ?int $assigneeId = null,
    ): void;

    /**
     * Confirm pending cycle segments khi quote được approved sau backtrack.
     */
    public function confirmPendingCycle(int $ogTicketId, int $cycle): void;
}
