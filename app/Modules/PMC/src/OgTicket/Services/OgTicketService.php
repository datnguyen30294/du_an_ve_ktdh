<?php

namespace App\Modules\PMC\OgTicket\Services;

use App\Common\Contracts\StorageServiceInterface;
use App\Common\Exceptions\BusinessException;
use App\Common\Services\BaseService;
use App\Modules\PMC\Account\Repositories\AccountRepository;
use App\Modules\PMC\Customer\Contracts\CustomerServiceInterface;
use App\Modules\PMC\OgTicket\Contracts\OgTicketLifecycleServiceInterface;
use App\Modules\PMC\OgTicket\Contracts\OgTicketServiceInterface;
use App\Modules\PMC\OgTicket\Enums\OgTicketPriority;
use App\Modules\PMC\OgTicket\Enums\OgTicketStatus;
use App\Modules\PMC\OgTicket\ExternalServices\TicketExternalServiceInterface;
use App\Modules\PMC\OgTicket\Models\OgTicket;
use App\Modules\PMC\OgTicket\Repositories\OgTicketRepository;
use App\Modules\PMC\Order\Contracts\OrderServiceInterface;
use App\Modules\PMC\Order\Enums\OrderStatus;
use App\Modules\PMC\Project\Repositories\ProjectRepository;
use App\Modules\PMC\Quote\Contracts\QuoteServiceInterface;
use App\Modules\PMC\Quote\Repositories\QuoteRepository;
use App\Modules\PMC\Setting\Contracts\SystemSettingServiceInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Symfony\Component\HttpFoundation\Response;

class OgTicketService extends BaseService implements OgTicketServiceInterface
{
    private const ATTACHMENTS_DIRECTORY = 'og-ticket-attachments';

    public function __construct(
        protected OgTicketRepository $repository,
        protected TicketExternalServiceInterface $ticketExternalService,
        protected StorageServiceInterface $storageService,
        protected AccountRepository $accountRepository,
        protected ProjectRepository $projectRepository,
        protected QuoteRepository $quoteRepository,
        protected QuoteServiceInterface $quoteService,
        protected OrderServiceInterface $orderService,
        protected OgTicketLifecycleServiceInterface $lifecycleService,
        protected SystemSettingServiceInterface $settingService,
        protected CustomerServiceInterface $customerService,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function getPool(array $filters): LengthAwarePaginator
    {
        return $this->ticketExternalService->getAvailableTickets($filters);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function claim(array $data): OgTicket
    {
        return $this->executeInTransaction(function () use ($data): OgTicket {
            $ticketId = (int) $data['ticket_id'];
            $tenantId = tenant('id') ?? '';

            // claimTicket đọc snapshot bên trong lock — đảm bảo data luôn nhất quán
            $ticketData = $this->ticketExternalService->claimTicket($ticketId, $tenantId);

            if ($ticketData === null) {
                throw new BusinessException(
                    message: 'Ticket không tồn tại.',
                    errorCode: 'TICKET_NOT_FOUND',
                    httpStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY,
                );
            }

            if ($ticketData === false) {
                throw new BusinessException(
                    message: 'Ticket đã được nhận bởi tổ chức khác hoặc không khả dụng.',
                    errorCode: 'TICKET_ALREADY_CLAIMED',
                    httpStatusCode: Response::HTTP_CONFLICT,
                );
            }

            try {
                $customer = $this->customerService->findOrCreateByPhone(
                    $ticketData['requester_phone'],
                    $ticketData['requester_name'],
                );

                /** @var OgTicket */
                $ogTicket = $this->repository->create([
                    'ticket_id' => $ticketId,
                    'customer_id' => $customer->id,
                    'requester_name' => $ticketData['requester_name'],
                    'requester_phone' => $ticketData['requester_phone'],
                    'apartment_name' => $ticketData['apartment_name'],
                    'project_id' => $ticketData['project_id'],
                    'subject' => $ticketData['subject'],
                    'description' => $ticketData['description'],
                    'address' => $ticketData['address'],
                    'latitude' => $ticketData['latitude'],
                    'longitude' => $ticketData['longitude'],
                    'channel' => $ticketData['channel'],
                    'status' => OgTicketStatus::Received->value,
                    'priority' => OgTicketPriority::Normal->value,
                    'received_at' => now(),
                    'sla_quote_due_at' => now()->addMinutes((int) $this->settingService->get('og_ticket', 'sla_quote_minutes', 60)),
                ]);

                $this->customerService->markContacted($customer);

                $this->lifecycleService->openFirst($ogTicket);

                return $ogTicket;
            } catch (\Throwable $e) {
                // Tạo OgTicket fail → release ticket lại pool
                $this->ticketExternalService->releaseTicket($ticketId);

                throw $e;
            }
        });
    }

    /**
     * Admin manual-create flow: create central Ticket (outside tenant transaction)
     * first, then tenant OgTicket + related data. On tenant failure, delete the
     * central Ticket to roll back.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): OgTicket
    {
        $tenantId = tenant('id') ?? '';

        // 1. Create central Ticket first (separate connection — outside tenant tx).
        $ticket = $this->ticketExternalService->createTicketForOrg([
            'requester_name' => $data['requester_name'],
            'requester_phone' => $data['requester_phone'],
            'subject' => $data['subject'],
            'description' => $data['description'] ?? null,
            'address' => $data['address'] ?? null,
            'apartment_name' => $data['apartment_name'] ?? null,
            'latitude' => $data['latitude'] ?? null,
            'longitude' => $data['longitude'] ?? null,
            'channel' => $data['channel'],
            'project_id' => $data['project_id'] ?? null,
        ], $tenantId);

        try {
            return $this->executeInTransaction(function () use ($ticket, $data): OgTicket {
                $customer = $this->customerService->findOrCreateByPhone(
                    $data['requester_phone'],
                    $data['requester_name'],
                );

                /** @var OgTicket $ogTicket */
                $ogTicket = $this->repository->create([
                    'ticket_id' => $ticket->id,
                    'customer_id' => $customer->id,
                    'requester_name' => $data['requester_name'],
                    'requester_phone' => $data['requester_phone'],
                    'apartment_name' => $data['apartment_name'] ?? null,
                    'project_id' => $data['project_id'] ?? null,
                    'subject' => $data['subject'],
                    'description' => $data['description'] ?? null,
                    'address' => $data['address'] ?? null,
                    'latitude' => $data['latitude'] ?? null,
                    'longitude' => $data['longitude'] ?? null,
                    'channel' => $data['channel'],
                    'status' => OgTicketStatus::Received->value,
                    'priority' => $data['priority'],
                    'received_at' => now(),
                    'received_by_id' => $data['received_by_id'] ?? null,
                    'internal_note' => $data['internal_note'] ?? null,
                    'sla_quote_due_at' => now()->addMinutes((int) $this->settingService->get('og_ticket', 'sla_quote_minutes', 60)),
                ]);

                $this->customerService->markContacted($customer);

                $this->lifecycleService->openFirst($ogTicket);

                $assigneeIds = $data['assigned_to_ids'] ?? [];
                if (! empty($assigneeIds)) {
                    $ogTicket->assignees()->sync($assigneeIds);
                    $this->lifecycleService->transition($ogTicket, OgTicketStatus::Assigned);
                }

                $categoryIds = $data['category_ids'] ?? [];
                if (! empty($categoryIds)) {
                    $ogTicket->categories()->sync($categoryIds);
                }

                $this->uploadAttachments($ogTicket, $data['attachments'] ?? []);

                return $this->findById($ogTicket->id);
            });
        } catch (\Throwable $e) {
            // Roll back the central Ticket so we don't leak orphan rows.
            $this->ticketExternalService->deleteTicket($ticket->id);

            throw $e;
        }
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function list(array $filters): LengthAwarePaginator
    {
        return $this->repository->list($filters);
    }

    public function findById(int $id): OgTicket
    {
        /** @var OgTicket */
        return $this->repository->findById($id, ['*'], [
            'receivedBy',
            'assignees',
            'project',
            'customer',
            'ticket.attachments',
            'attachments',
            'lifecycleSegments.assignee',
            'warrantyRequests.attachments',
            'categories',
            'activeQuote:id,og_ticket_id,is_active',
            'activeQuote.order:id,quote_id,total_amount',
            'activeQuote.order.receivable:id,order_id,status,amount,paid_amount',
            'activeQuote.order.receivable.reconciliations:id,receivable_id,status,amount',
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(int $id, array $data): OgTicket
    {
        return $this->executeInTransaction(function () use ($id, $data): OgTicket {
            /** @var OgTicket */
            $ogTicket = $this->repository->findById($id);

            if ($ogTicket->status === OgTicketStatus::Cancelled) {
                throw new BusinessException(
                    message: 'Không thể chỉnh sửa ticket đã huỷ.',
                    errorCode: 'TICKET_CANCELLED',
                    httpStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY,
                );
            }

            // Tách attachments, delete_attachment_ids, assigned_to_ids ra khỏi data update
            $files = $data['attachments'] ?? [];
            $deleteIds = $data['delete_attachment_ids'] ?? [];
            $assigneeIds = $data['assigned_to_ids'] ?? null;
            // requester_name/requester_phone là snapshot, không cho sửa qua update ticket
            // (customer_id giữ nguyên, muốn đổi thông tin → sửa bên module Customer).
            unset(
                $data['attachments'],
                $data['delete_attachment_ids'],
                $data['assigned_to_ids'],
                $data['status'],
                $data['requester_name'],
                $data['requester_phone'],
                $data['customer_id'],
            );

            $ogTicket->update($data);

            // Sync assignees
            if ($assigneeIds !== null) {
                $ogTicket->assignees()->sync($assigneeIds);

                // Auto-transition: received → assigned khi gán người thi công
                if ($ogTicket->status === OgTicketStatus::Received && ! empty($assigneeIds)) {
                    $this->lifecycleService->transition($ogTicket, OgTicketStatus::Assigned);
                }
            }

            // Xoá attachments
            $this->deleteAttachments($ogTicket, $deleteIds);

            // Upload attachments mới
            $this->uploadAttachments($ogTicket, $files);

            return $this->findById($id);
        });
    }

    /**
     * Manually transition og_ticket status (forward or backtrack via stepper).
     *
     * @param  array<string, mixed>  $data
     */
    public function manualTransition(int $id, array $data): OgTicket
    {
        return $this->executeInTransaction(function () use ($id, $data): OgTicket {
            /** @var OgTicket */
            $ogTicket = $this->repository->findById($id);
            $targetStatus = OgTicketStatus::from($data['target_status']);
            $note = $data['note'] ?? null;

            // Không transition từ trạng thái kết thúc
            if (\in_array($ogTicket->status, [OgTicketStatus::Completed, OgTicketStatus::Cancelled], true)) {
                throw new BusinessException(
                    message: 'Không thể chuyển trạng thái ticket đã hoàn thành hoặc đã huỷ.',
                    errorCode: 'TICKET_TERMINAL_STATUS',
                    httpStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY,
                );
            }

            // Không transition sang chính mình
            if ($ogTicket->status === $targetStatus) {
                throw new BusinessException(
                    message: 'Ticket đã ở trạng thái này.',
                    errorCode: 'TICKET_SAME_STATUS',
                    httpStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY,
                );
            }

            $currentIdx = $ogTicket->status->workflowIndex();
            $targetIdx = $targetStatus->workflowIndex();
            $isForward = $targetIdx > $currentIdx;
            $isBacktrack = $targetIdx < $currentIdx;

            // Manual transition cho phép:
            // - Forward: assigned → surveying
            // - Backward từ ordered/in_progress: chỉ về quoted (giữ order)
            // - Backward từ quoted trở xuống: về assigned hoặc surveying (cancel quote + order)
            $isFromOrderedOrLater = \in_array($ogTicket->status, [
                OgTicketStatus::Ordered,
                OgTicketStatus::InProgress,
            ], true);

            if ($isForward) {
                if ($ogTicket->status !== OgTicketStatus::Assigned || $targetStatus !== OgTicketStatus::Surveying) {
                    throw new BusinessException(
                        message: "Không thể chuyển từ \"{$ogTicket->status->label()}\" sang \"{$targetStatus->label()}\".",
                        errorCode: 'TICKET_INVALID_TRANSITION',
                        httpStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY,
                    );
                }
            } elseif ($isBacktrack) {
                if ($isFromOrderedOrLater) {
                    // Từ ordered/in_progress: chỉ cho về quoted
                    if ($targetStatus !== OgTicketStatus::Quoted) {
                        throw new BusinessException(
                            message: "Từ \"{$ogTicket->status->label()}\" chỉ có thể quay lại \"Báo giá\".",
                            errorCode: 'TICKET_INVALID_TRANSITION',
                            httpStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY,
                        );
                    }
                    // Không cancel order — user sẽ tạo quote mới với replace_active
                } else {
                    // Từ quoted trở xuống: chỉ về assigned hoặc surveying
                    $allowedTargets = [OgTicketStatus::Assigned, OgTicketStatus::Surveying];
                    if (! \in_array($targetStatus, $allowedTargets, true)) {
                        throw new BusinessException(
                            message: "Không thể chuyển thủ công sang \"{$targetStatus->label()}\".",
                            errorCode: 'TICKET_INVALID_TRANSITION',
                            httpStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY,
                        );
                    }
                    // Cancel quote + order
                    $this->quoteService->cancelByOgTicket($id);
                }
            } else {
                throw new BusinessException(
                    message: "Không thể chuyển thủ công sang \"{$targetStatus->label()}\".",
                    errorCode: 'TICKET_INVALID_TRANSITION',
                    httpStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY,
                );
            }

            $this->lifecycleService->transition($ogTicket, $targetStatus, $note);

            return $this->findById($id);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function release(int $id, array $data): OgTicket
    {
        return $this->executeInTransaction(function () use ($id, $data): OgTicket {
            /** @var OgTicket */
            $ogTicket = $this->repository->findById($id);

            $this->ensureCanCancel($ogTicket);

            $note = ! empty($data['note']) ? $data['note'] : null;

            if ($note) {
                $ogTicket->update(['internal_note' => $note]);
            }

            $this->lifecycleService->transition($ogTicket, OgTicketStatus::Cancelled, $note);
            $this->quoteService->cancelByOgTicket($id);
            $this->ticketExternalService->releaseTicket($ogTicket->ticket_id);

            return $ogTicket->refresh();
        });
    }

    public function delete(int $id): void
    {
        $this->executeInTransaction(function () use ($id): void {
            /** @var OgTicket */
            $ogTicket = $this->repository->findById($id);

            $this->ensureCanCancel($ogTicket);

            $this->lifecycleService->transition($ogTicket, OgTicketStatus::Cancelled);
            $this->quoteService->cancelByOgTicket($id);
            $this->ticketExternalService->releaseTicket($ogTicket->ticket_id);
        });
    }

    /**
     * @return array{can_delete: bool, message: string}
     */
    public function checkDelete(int $id): array
    {
        /** @var OgTicket */
        $ogTicket = $this->repository->findById($id);

        try {
            $this->ensureCanCancel($ogTicket);
        } catch (BusinessException $e) {
            return ['can_delete' => false, 'message' => $e->getMessage()];
        }

        return ['can_delete' => true, 'message' => 'Có thể huỷ ticket này.'];
    }

    /**
     * Validate that an OgTicket can be cancelled.
     * Blocks if already cancelled or has a completed order.
     */
    private function ensureCanCancel(OgTicket $ogTicket): void
    {
        if ($ogTicket->status === OgTicketStatus::Cancelled) {
            throw new BusinessException(
                message: 'Ticket đã bị hủy.',
                errorCode: 'ALREADY_CANCELLED',
                httpStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        $activeQuote = $this->quoteRepository->findActiveByOgTicket($ogTicket->id);
        if ($activeQuote?->order && $activeQuote->order->status === OrderStatus::Completed) {
            throw new BusinessException(
                message: 'Không thể huỷ ticket khi đơn hàng đã hoàn thành.',
                errorCode: 'ORDER_COMPLETED',
                httpStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }
    }

    /**
     * Upload attachments for an OgTicket.
     *
     * @param  array<int, UploadedFile>  $files
     */
    private function uploadAttachments(OgTicket $ogTicket, array $files): void
    {
        $uploadedNames = [];

        foreach ($files as $file) {
            if (! $file instanceof UploadedFile) {
                continue;
            }

            $path = $this->storageService->upload($file, self::ATTACHMENTS_DIRECTORY);

            $ogTicket->attachments()->create([
                'file_path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'size_bytes' => $file->getSize(),
            ]);

            $uploadedNames[] = $file->getClientOriginalName();
        }

        if (! empty($uploadedNames)) {
            $this->auditAttachmentChange($ogTicket, 'Thêm tệp đính kèm', implode(', ', $uploadedNames));
        }
    }

    /**
     * Delete attachments by IDs (only those belonging to this OgTicket).
     *
     * @param  array<int, int>  $ids
     */
    private function deleteAttachments(OgTicket $ogTicket, array $ids): void
    {
        if (empty($ids)) {
            return;
        }

        $attachments = $ogTicket->attachments()->whereIn('id', $ids)->get();
        $deletedNames = $attachments->pluck('original_name')->all();

        foreach ($attachments as $attachment) {
            $this->storageService->delete($attachment->file_path);
            $attachment->delete();
        }

        if (! empty($deletedNames)) {
            $this->auditAttachmentChange($ogTicket, implode(', ', $deletedNames), 'Xoá tệp đính kèm');
        }
    }

    private function auditAttachmentChange(OgTicket $ogTicket, string $oldValue, string $newValue): void
    {
        $ogTicket->auditCustomOld = ['attachments' => $oldValue];
        $ogTicket->auditCustomNew = ['attachments' => $newValue];
        $ogTicket->auditEvent = 'updated';
        $ogTicket->isCustomEvent = true;

        /** @var \OwenIt\Auditing\Contracts\Auditor $auditor */
        $auditor = app(\OwenIt\Auditing\Contracts\Auditor::class);
        $auditor->execute($ogTicket);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getAudits(int $id): array
    {
        /** @var OgTicket */
        $ogTicket = $this->repository->findById($id);

        $audits = $ogTicket->audits()
            ->with('user')
            ->latest()
            ->get();

        // Collect all foreign key IDs to resolve in batch
        $accountIds = collect();
        $projectIds = collect();

        foreach ($audits as $audit) {
            foreach (['old_values', 'new_values'] as $prop) {
                $values = $audit->{$prop} ?? [];
                if (isset($values['received_by_id'])) {
                    $accountIds->push($values['received_by_id']);
                }
                if (isset($values['project_id'])) {
                    $projectIds->push($values['project_id']);
                }
            }
        }

        $accountNames = $this->accountRepository->pluckNamesByIds($accountIds->filter()->unique());
        $projectNames = $this->projectRepository->pluckNamesByIds($projectIds->filter()->unique());

        return $audits->map(fn ($audit) => [
            'id' => $audit->id,
            'event' => $audit->event,
            'old_values' => $this->resolveAuditValues($audit->old_values, $accountNames, $projectNames),
            'new_values' => $this->resolveAuditValues($audit->new_values, $accountNames, $projectNames),
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
     * @param  \Illuminate\Support\Collection<int, string>  $accountNames
     * @param  \Illuminate\Support\Collection<int, string>  $projectNames
     * @return array<string, mixed>|null
     */
    private function resolveAuditValues(?array $values, $accountNames, $projectNames): ?array
    {
        if (! $values) {
            return null;
        }

        $resolved = $values;

        // Resolve received_by_id → name
        if (isset($resolved['received_by_id'])) {
            $resolved['received_by_id'] = $accountNames->get($resolved['received_by_id'], $resolved['received_by_id']);
        }

        // Resolve project_id → name
        if (isset($resolved['project_id'])) {
            $resolved['project_id'] = $projectNames->get($resolved['project_id'], $resolved['project_id']);
        }

        // Resolve status enum → label
        if (isset($resolved['status'])) {
            $status = OgTicketStatus::tryFrom($resolved['status']);
            $resolved['status'] = $status ? $status->label() : $resolved['status'];
        }

        // Resolve priority enum → label
        if (isset($resolved['priority'])) {
            $priority = OgTicketPriority::tryFrom($resolved['priority']);
            $resolved['priority'] = $priority ? $priority->label() : $resolved['priority'];
        }

        // Resolve datetime fields → ISO 8601 (frontend handles timezone display)
        $datetimeFields = ['received_at', 'sla_quote_due_at', 'sla_completion_due_at'];
        foreach ($datetimeFields as $field) {
            if (isset($resolved[$field]) && $resolved[$field]) {
                try {
                    $resolved[$field] = \Carbon\Carbon::parse($resolved[$field])->toIso8601String();
                } catch (\Throwable) {
                    // Keep raw value if parsing fails
                }
            }
        }

        return $resolved;
    }

    /**
     * @param  array<int, int>  $categoryIds
     */
    public function syncCategories(int $id, array $categoryIds): OgTicket
    {
        /** @var OgTicket $ogTicket */
        $ogTicket = $this->repository->findById($id);
        $ogTicket->categories()->sync($categoryIds);

        return $this->findById($id);
    }
}
