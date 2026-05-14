<?php

namespace App\Modules\Platform\Ticket\ExternalServices;

use App\Common\Exceptions\BusinessException;
use App\Modules\Platform\Tenant\Models\Organization;
use App\Modules\Platform\Ticket\Models\Ticket;
use App\Modules\PMC\AcceptanceReport\Models\AcceptanceReport;
use App\Modules\PMC\Catalog\Models\CatalogItem;
use App\Modules\PMC\OgTicket\Contracts\OgTicketWarrantyRequestServiceInterface;
use App\Modules\PMC\OgTicket\Enums\OgTicketPriority;
use App\Modules\PMC\OgTicket\Enums\OgTicketStatus;
use App\Modules\PMC\OgTicket\Models\OgTicket;
use App\Modules\PMC\OgTicketCategory\Repositories\OgTicketCategoryRepository;
use App\Modules\PMC\Order\Enums\OrderStatus;
use App\Modules\PMC\Quote\Contracts\QuoteServiceInterface;
use App\Modules\PMC\Quote\Enums\QuoteStatus;
use App\Modules\PMC\Quote\Models\Quote;
use App\Modules\PMC\Setting\Contracts\SystemSettingServiceInterface;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class OgTicketExternalService implements OgTicketExternalServiceInterface
{
    /**
     * Hard-coded warranty period in months. Applies from the moment an order is completed.
     */
    public const WARRANTY_PERIOD_MONTHS = 12;

    public function createFromTicket(Ticket $ticket): void
    {
        $tenant = Organization::find($ticket->claimed_by_org_id);

        if (! $tenant) {
            return;
        }

        $tenant->run(function () use ($ticket): void {
            $settingService = app(SystemSettingServiceInterface::class);
            $slaQuoteMinutes = (int) $settingService->get('og_ticket', 'sla_quote_minutes', 60);

            $customerService = app(\App\Modules\PMC\Customer\Contracts\CustomerServiceInterface::class);
            $customer = $customerService->findOrCreateByPhone(
                (string) $ticket->requester_phone,
                (string) $ticket->requester_name,
            );

            $ogTicket = OgTicket::create([
                'ticket_id' => $ticket->id,
                'customer_id' => $customer->id,
                'requester_name' => $ticket->requester_name,
                'requester_phone' => $ticket->requester_phone,
                'subject' => $ticket->subject,
                'description' => $ticket->description,
                'address' => $ticket->address,
                'latitude' => $ticket->latitude,
                'longitude' => $ticket->longitude,
                'channel' => $ticket->channel->value,
                'project_id' => $ticket->project_id,
                'status' => OgTicketStatus::Received->value,
                'priority' => OgTicketPriority::Normal->value,
                'received_at' => now(),
                'sla_quote_due_at' => now()->addMinutes($slaQuoteMinutes),
            ]);

            $customerService->markContacted($customer);

            $this->attachCategoriesFromSubject($ogTicket, (string) $ticket->subject);
        });
    }

    /**
     * Parse subject by ', ', match catalog items (case-insensitive), resolve distinct
     * service_category names, firstOrCreate og_ticket_categories, sync pivot.
     * Unmatched names (or empty subject / matches without categories) → 'Khác'.
     */
    private function attachCategoriesFromSubject(OgTicket $ogTicket, string $subject): void
    {
        $names = collect(explode(', ', $subject))
            ->map(fn ($n) => trim($n))
            ->filter()
            ->unique()
            ->values();

        $hasUnmatched = false;
        $categoryNames = collect();

        if ($names->isEmpty()) {
            $hasUnmatched = true;
        } else {
            // Fetch all catalog item names then filter case-insensitively in PHP.
            // DB LOWER() is ASCII-only on SQLite (tests), so use mb_strtolower in PHP.
            $lowerNames = $names->map(fn ($n) => mb_strtolower($n))->values();

            $items = CatalogItem::query()
                ->with('serviceCategory')
                ->get(['id', 'name', 'service_category_id'])
                ->filter(fn (CatalogItem $item) => $lowerNames->contains(mb_strtolower((string) $item->name)));

            $matchedLower = $items->pluck('name')->map(fn ($n) => mb_strtolower((string) $n));
            $hasUnmatched = $lowerNames->diff($matchedLower)->isNotEmpty();

            $categoryNames = $items
                ->pluck('serviceCategory.name')
                ->filter()
                ->map(fn ($n) => trim((string) $n))
                ->filter()
                ->unique()
                ->values();
        }

        if ($hasUnmatched || $categoryNames->isEmpty()) {
            $categoryNames = $categoryNames->push('Khác');
        }

        /** @var OgTicketCategoryRepository $repository */
        $repository = app(OgTicketCategoryRepository::class);

        $ids = $categoryNames
            ->unique()
            ->map(fn ($name) => $repository->firstOrCreateByName((string) $name)->id)
            ->all();

        $ogTicket->categories()->sync($ids);
    }

    public function getProcessingInfoByTicketId(int $ticketId): ?array
    {
        $ticket = Ticket::find($ticketId);

        if (! $ticket || ! $ticket->claimed_by_org_id) {
            return null;
        }

        $tenant = Organization::find($ticket->claimed_by_org_id);

        if (! $tenant) {
            return null;
        }

        return $tenant->run(function () use ($ticketId): ?array {
            $ogTicket = OgTicket::query()
                ->with(['receivedBy', 'assignees'])
                ->where('ticket_id', $ticketId)
                ->where('status', '!=', OgTicketStatus::Cancelled->value)
                ->latest()
                ->first();

            if (! $ogTicket) {
                return null;
            }

            return [
                'status' => [
                    'value' => $ogTicket->status->value,
                    'label' => $ogTicket->status->label(),
                ],
                'priority' => [
                    'value' => $ogTicket->priority->value,
                    'label' => $ogTicket->priority->label(),
                ],
                'received_at' => $ogTicket->received_at?->toIso8601String(),
                'received_by' => $ogTicket->receivedBy
                    ? ['id' => $ogTicket->receivedBy->id, 'name' => $ogTicket->receivedBy->name]
                    : null,
                'assignees' => $ogTicket->assignees
                    ->map(fn ($a) => ['id' => $a->id, 'name' => $a->name])
                    ->values()
                    ->all(),
                'sla_due_at' => $ogTicket->sla_completion_due_at?->toIso8601String(),
            ];
        });
    }

    public function hasRecentStatusChange(int $ticketId, string $orgId, int $timeoutMinutes): bool
    {
        $tenant = Organization::find($orgId);

        if (! $tenant) {
            return false;
        }

        return $tenant->run(function () use ($ticketId, $timeoutMinutes): bool {
            $ogTicket = OgTicket::query()
                ->where('ticket_id', $ticketId)
                ->where('status', '!=', OgTicketStatus::Cancelled->value)
                ->first();

            if (! $ogTicket) {
                return false;
            }

            $lastChangeAt = $this->getLastStatusChangeAt($ogTicket);

            return $lastChangeAt->greaterThan(now()->subMinutes($timeoutMinutes));
        });
    }

    public function autoReleaseOgTicket(int $ticketId, string $orgId, int $timeoutMinutes): bool
    {
        $tenant = Organization::find($orgId);

        if (! $tenant) {
            return true;
        }

        $released = $tenant->run(function () use ($ticketId, $timeoutMinutes): bool {
            return DB::transaction(function () use ($ticketId, $timeoutMinutes): bool {
                $ogTicket = OgTicket::query()
                    ->where('ticket_id', $ticketId)
                    ->whereNotIn('status', [
                        OgTicketStatus::Completed->value,
                        OgTicketStatus::Cancelled->value,
                    ])
                    ->lockForUpdate()
                    ->first();

                if (! $ogTicket) {
                    return true;
                }

                $lastChangeAt = $this->getLastStatusChangeAt($ogTicket);

                if ($lastChangeAt->greaterThan(now()->subMinutes($timeoutMinutes))) {
                    return false;
                }

                $ogTicket->update([
                    'status' => OgTicketStatus::Cancelled->value,
                    'internal_note' => 'Tự động thu hồi do không cập nhật trạng thái trong '.$timeoutMinutes.' phút.',
                ]);

                return true;
            });
        });

        return $released;
    }

    public function getOrderInfoByTicketId(int $ticketId): ?array
    {
        $ticket = Ticket::find($ticketId);

        if (! $ticket || ! $ticket->claimed_by_org_id) {
            return null;
        }

        $tenant = Organization::find($ticket->claimed_by_org_id);

        if (! $tenant) {
            return null;
        }

        return $tenant->run(function () use ($ticketId): ?array {
            $ogTicket = OgTicket::query()
                ->where('ticket_id', $ticketId)
                ->where('status', '!=', OgTicketStatus::Cancelled->value)
                ->first();

            if (! $ogTicket) {
                return null;
            }

            $quote = Quote::query()
                ->with(['order.lines', 'lines'])
                ->where('og_ticket_id', $ogTicket->id)
                ->where('is_active', true)
                ->first();

            if (! $quote) {
                return null;
            }

            $order = $quote->order;

            if (! $order) {
                return null;
            }

            $lines = $order->lines->map(fn ($line) => [
                'name' => $line->name,
                'quantity' => $line->quantity,
                'unit' => $line->unit,
                'unit_price' => $line->unit_price,
                'line_amount' => $line->line_amount,
                'line_type' => [
                    'value' => $line->line_type->value,
                    'label' => $line->line_type->label(),
                ],
            ])->values()->all();

            return [
                'code' => $order->code,
                'status' => [
                    'value' => $order->status->value,
                    'label' => $order->status->label(),
                ],
                'total_amount' => $order->total_amount,
                'lines' => $lines,
            ];
        });
    }

    public function getQuoteInfoByTicketId(int $ticketId): ?array
    {
        $ticket = Ticket::find($ticketId);

        if (! $ticket || ! $ticket->claimed_by_org_id) {
            return null;
        }

        $tenant = Organization::find($ticket->claimed_by_org_id);

        if (! $tenant) {
            return null;
        }

        return $tenant->run(function () use ($ticketId): ?array {
            $ogTicket = OgTicket::query()
                ->where('ticket_id', $ticketId)
                ->where('status', '!=', OgTicketStatus::Cancelled->value)
                ->first();

            if (! $ogTicket) {
                return null;
            }

            $quote = Quote::query()
                ->with('lines')
                ->where('og_ticket_id', $ogTicket->id)
                ->where('is_active', true)
                ->first();

            if (! $quote) {
                return null;
            }

            // Only show to resident if status is relevant to them
            $visibleStatuses = [QuoteStatus::ManagerApproved, QuoteStatus::Approved, QuoteStatus::ResidentRejected];
            if (! \in_array($quote->status, $visibleStatuses, true)) {
                return null;
            }

            $lines = $quote->lines->map(fn ($line) => [
                'name' => $line->name,
                'quantity' => $line->quantity,
                'unit' => $line->unit,
                'unit_price' => $line->unit_price,
                'line_amount' => $line->line_amount,
                'line_type' => [
                    'value' => $line->line_type->value,
                    'label' => $line->line_type->label(),
                ],
            ])->values()->all();

            return [
                'code' => $quote->code,
                'status' => [
                    'value' => $quote->status->value,
                    'label' => $quote->status->label(),
                ],
                'total_amount' => $quote->total_amount,
                'lines' => $lines,
                'is_resident_actionable' => $quote->status === QuoteStatus::ManagerApproved,
                'manager_approved_at' => $quote->manager_approved_at?->toIso8601String(),
                'note' => $quote->note,
            ];
        });
    }

    public function decideQuoteByTicketId(int $ticketId, string $action, ?string $reason): void
    {
        $ticket = Ticket::find($ticketId);

        if (! $ticket || ! $ticket->claimed_by_org_id) {
            throw new BusinessException(
                message: 'Không tìm thấy thông tin yêu cầu.',
                errorCode: 'TICKET_NOT_FOUND',
                httpStatusCode: Response::HTTP_NOT_FOUND,
            );
        }

        $tenant = Organization::find($ticket->claimed_by_org_id);

        if (! $tenant) {
            throw new BusinessException(
                message: 'Không tìm thấy thông tin yêu cầu.',
                errorCode: 'TENANT_NOT_FOUND',
                httpStatusCode: Response::HTTP_NOT_FOUND,
            );
        }

        $tenant->run(function () use ($ticketId, $action, $reason): void {
            $ogTicket = OgTicket::query()
                ->where('ticket_id', $ticketId)
                ->where('status', '!=', OgTicketStatus::Cancelled->value)
                ->first();

            if (! $ogTicket) {
                throw new BusinessException(
                    message: 'Không tìm thấy yêu cầu trong hệ thống.',
                    errorCode: 'OG_TICKET_NOT_FOUND',
                    httpStatusCode: Response::HTTP_NOT_FOUND,
                );
            }

            $quote = Quote::query()
                ->where('og_ticket_id', $ogTicket->id)
                ->where('is_active', true)
                ->first();

            if (! $quote) {
                throw new BusinessException(
                    message: 'Không tìm thấy báo giá.',
                    errorCode: 'QUOTE_NOT_FOUND',
                    httpStatusCode: Response::HTTP_NOT_FOUND,
                );
            }

            if ($quote->status !== QuoteStatus::ManagerApproved) {
                throw new BusinessException(
                    message: 'Báo giá không còn chờ chấp thuận.',
                    errorCode: 'QUOTE_NOT_ACTIONABLE',
                    httpStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY,
                );
            }

            $targetStatus = $action === 'approve'
                ? QuoteStatus::Approved->value
                : QuoteStatus::ResidentRejected->value;

            /** @var QuoteServiceInterface $quoteService */
            $quoteService = app(QuoteServiceInterface::class);
            $quoteService->transition($quote->id, [
                'status' => $targetStatus,
                'note' => $reason,
            ]);
        });
    }

    public function syncRating(Ticket $ticket): void
    {
        if (! $ticket->claimed_by_org_id) {
            return;
        }

        $tenant = Organization::find($ticket->claimed_by_org_id);

        if (! $tenant) {
            return;
        }

        $tenant->run(function () use ($ticket): void {
            OgTicket::query()
                ->where('ticket_id', $ticket->id)
                ->active()
                ->update([
                    'resident_rating' => $ticket->resident_rating,
                    'resident_rating_comment' => $ticket->resident_rating_comment,
                    'resident_rated_at' => $ticket->resident_rated_at,
                ]);
        });
    }

    public function submitWarrantyRequest(int $ticketId, array $data, array $files): void
    {
        $ticket = Ticket::find($ticketId);

        if (! $ticket || ! $ticket->claimed_by_org_id) {
            throw new BusinessException(
                message: 'Không tìm thấy thông tin yêu cầu.',
                errorCode: 'TICKET_NOT_FOUND',
                httpStatusCode: Response::HTTP_NOT_FOUND,
            );
        }

        $tenant = Organization::find($ticket->claimed_by_org_id);

        if (! $tenant) {
            throw new BusinessException(
                message: 'Không tìm thấy thông tin yêu cầu.',
                errorCode: 'TENANT_NOT_FOUND',
                httpStatusCode: Response::HTTP_NOT_FOUND,
            );
        }

        $tenant->run(function () use ($ticketId, $data, $files): void {
            $ogTicket = OgTicket::query()
                ->where('ticket_id', $ticketId)
                ->where('status', '!=', OgTicketStatus::Cancelled->value)
                ->first();

            if (! $ogTicket) {
                throw new BusinessException(
                    message: 'Không tìm thấy yêu cầu trong hệ thống.',
                    errorCode: 'OG_TICKET_NOT_FOUND',
                    httpStatusCode: Response::HTTP_NOT_FOUND,
                );
            }

            $order = $this->findCompletedOrderForOgTicket($ogTicket->id);

            if (! $order) {
                throw new BusinessException(
                    message: 'Chưa có đơn hàng hoàn thành để yêu cầu bảo hành.',
                    errorCode: 'ORDER_NOT_COMPLETED',
                    httpStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY,
                );
            }

            if (! $this->isWithinWarrantyPeriod($order->completed_at)) {
                throw new BusinessException(
                    message: 'Đã hết thời hạn bảo hành '.self::WARRANTY_PERIOD_MONTHS.' tháng.',
                    errorCode: 'WARRANTY_EXPIRED',
                    httpStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY,
                );
            }

            /** @var OgTicketWarrantyRequestServiceInterface $warrantyService */
            $warrantyService = app(OgTicketWarrantyRequestServiceInterface::class);
            $warrantyService->create(
                ogTicketId: $ogTicket->id,
                requesterName: $ogTicket->requester_name,
                data: ['subject' => $data['subject'], 'description' => $data['description']],
                files: $files,
            );
        });
    }

    public function listWarrantyRequestsByTicketId(int $ticketId): array
    {
        $ticket = Ticket::find($ticketId);

        if (! $ticket || ! $ticket->claimed_by_org_id) {
            return [];
        }

        $tenant = Organization::find($ticket->claimed_by_org_id);

        if (! $tenant) {
            return [];
        }

        return $tenant->run(function () use ($ticketId): array {
            $ogTicket = OgTicket::query()
                ->where('ticket_id', $ticketId)
                ->where('status', '!=', OgTicketStatus::Cancelled->value)
                ->first();

            if (! $ogTicket) {
                return [];
            }

            /** @var OgTicketWarrantyRequestServiceInterface $warrantyService */
            $warrantyService = app(OgTicketWarrantyRequestServiceInterface::class);
            $items = $warrantyService->listByOgTicketId($ogTicket->id);

            return $items->map(fn ($warranty) => [
                'id' => $warranty->id,
                'subject' => $warranty->subject,
                'description' => $warranty->description,
                'requester_name' => $warranty->requester_name,
                'created_at' => $warranty->created_at?->toIso8601String(),
                'attachments' => $warranty->attachments->map(fn ($a) => [
                    'id' => $a->id,
                    'url' => $a->url,
                    'original_name' => $a->original_name,
                    'mime_type' => $a->mime_type,
                    'size_bytes' => (int) $a->size_bytes,
                ])->values()->all(),
            ])->values()->all();
        });
    }

    public function canRequestWarrantyByTicketId(int $ticketId): bool
    {
        $ticket = Ticket::find($ticketId);

        if (! $ticket || ! $ticket->claimed_by_org_id) {
            return false;
        }

        $tenant = Organization::find($ticket->claimed_by_org_id);

        if (! $tenant) {
            return false;
        }

        return $tenant->run(function () use ($ticketId): bool {
            $ogTicket = OgTicket::query()
                ->where('ticket_id', $ticketId)
                ->where('status', '!=', OgTicketStatus::Cancelled->value)
                ->first();

            if (! $ogTicket) {
                return false;
            }

            $order = $this->findCompletedOrderForOgTicket($ogTicket->id);

            if (! $order) {
                return false;
            }

            return $this->isWithinWarrantyPeriod($order->completed_at);
        });
    }

    public function getAcceptanceReportInfoByTicketId(int $ticketId): ?array
    {
        $ticket = Ticket::find($ticketId);

        if (! $ticket || ! $ticket->claimed_by_org_id) {
            return null;
        }

        $tenant = Organization::find($ticket->claimed_by_org_id);

        if (! $tenant) {
            return null;
        }

        $tenantSubdomain = (string) $ticket->claimed_by_org_id;

        return $tenant->run(function () use ($ticketId, $tenantSubdomain): ?array {
            $ogTicket = OgTicket::query()
                ->where('ticket_id', $ticketId)
                ->where('status', '!=', OgTicketStatus::Cancelled->value)
                ->first();

            if (! $ogTicket) {
                return null;
            }

            $quote = Quote::query()
                ->with('order')
                ->where('og_ticket_id', $ogTicket->id)
                ->where('is_active', true)
                ->first();

            $order = $quote?->order;

            if (! $order) {
                return null;
            }

            $visibleStatuses = [OrderStatus::Accepted, OrderStatus::Completed];

            if (! in_array($order->status, $visibleStatuses, true)) {
                return null;
            }

            /** @var AcceptanceReport|null $report */
            $report = AcceptanceReport::query()
                ->where('order_id', $order->id)
                ->first();

            if (! $report) {
                return null;
            }

            return [
                'share_token' => $report->share_token,
                // Relative path: resident is already on the correct tenant origin when
                // viewing the public ticket page, so the browser will resolve this
                // against the current FE host. Avoids depending on FRONTEND_URL which
                // may carry a different base subdomain in production.
                'public_url' => '/acceptance-report/'.$report->share_token,
                'is_confirmed' => $report->confirmed_at !== null,
                'confirmed_at' => $report->confirmed_at?->toIso8601String(),
                'confirmed_signature_name' => $report->confirmed_signature_name,
                'is_confirmable' => $report->confirmed_at === null,
                'has_signed_file' => $report->signed_file_path !== null,
                'signed_file_url' => $report->signed_file_url,
                'signed_file_original_name' => $report->signed_file_original_name,
                'signed_uploaded_at' => $report->signed_uploaded_at?->toIso8601String(),
            ];
        });
    }

    /**
     * Find the completed order linked to an og_ticket via its active quote.
     * Must run inside a tenant context.
     */
    private function findCompletedOrderForOgTicket(int $ogTicketId): ?\App\Modules\PMC\Order\Models\Order
    {
        $quote = Quote::query()
            ->with('order')
            ->where('og_ticket_id', $ogTicketId)
            ->where('is_active', true)
            ->first();

        $order = $quote?->order;

        if (! $order || $order->status !== OrderStatus::Completed) {
            return null;
        }

        return $order;
    }

    private function isWithinWarrantyPeriod(?Carbon $completedAt): bool
    {
        if (! $completedAt) {
            return false;
        }

        return $completedAt->copy()->addMonths(self::WARRANTY_PERIOD_MONTHS)->greaterThanOrEqualTo(now());
    }

    /**
     * Get the timestamp of the last status change from audit trail.
     * Falls back to OgTicket created_at if no status audit found.
     */
    private function getLastStatusChangeAt(OgTicket $ogTicket): Carbon
    {
        $lastAuditAt = $ogTicket->audits()
            ->where('new_values', 'like', '%"status"%')
            ->latest('created_at')
            ->value('created_at');

        return $lastAuditAt ? Carbon::parse($lastAuditAt) : $ogTicket->created_at;
    }
}
