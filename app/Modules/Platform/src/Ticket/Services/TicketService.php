<?php

namespace App\Modules\Platform\Ticket\Services;

use App\Common\Contracts\StorageServiceInterface;
use App\Common\Exceptions\BusinessException;
use App\Common\Services\BaseService;
use App\Events\TicketReceivedByOrganization;
use App\Modules\Platform\Customer\Repositories\CustomerRepository;
use App\Modules\Platform\Ticket\Contracts\TicketServiceInterface;
use App\Modules\Platform\Ticket\Enums\TicketChannel;
use App\Modules\Platform\Ticket\Enums\TicketStatus;
use App\Modules\Platform\Ticket\ExternalServices\OgTicketExternalServiceInterface;
use App\Modules\Platform\Ticket\ExternalServices\OrganizationExternalServiceInterface;
use App\Modules\Platform\Ticket\Models\Ticket;
use App\Modules\Platform\Ticket\Repositories\TicketRepository;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Symfony\Component\HttpFoundation\Response;

class TicketService extends BaseService implements TicketServiceInterface
{
    private const ATTACHMENTS_DIRECTORY = 'ticket-attachments';

    public function __construct(
        protected TicketRepository $repository,
        protected CustomerRepository $customerRepository,
        protected OrganizationExternalServiceInterface $organizationExternalService,
        protected OgTicketExternalServiceInterface $ogTicketExternalService,
        protected StorageServiceInterface $storageService,
    ) {}

    public function list(array $filters): LengthAwarePaginator
    {
        return $this->repository->list($filters);
    }

    public function findById(int $id): Ticket
    {
        /** @var Ticket */
        $ticket = $this->repository->findById($id, ['*'], ['attachments', 'customer']);

        $pmcProcessing = $this->ogTicketExternalService->getProcessingInfoByTicketId($ticket->id);
        $ticket->setAttribute('pmc_processing', $pmcProcessing);

        return $ticket;
    }

    public function submit(array $data): Ticket
    {
        return $this->executeInTransaction(function () use ($data): Ticket {
            $orgId = $data['claimed_by_org_id'] ?? null;
            if ($orgId) {
                $this->validateOrganization($orgId);
            }

            $code = $this->repository->generateCode((int) date('Y'));

            $customer = $this->resolveCustomer($data);

            $ticketData = [
                'code' => $code,
                'customer_id' => $customer->id,
                'requester_name' => $data['requester_name'],
                'requester_phone' => $data['requester_phone'],
                'subject' => $data['subject'],
                'description' => $data['description'] ?? null,
                'address' => $data['address'] ?? null,
                'latitude' => $data['latitude'] ?? null,
                'longitude' => $data['longitude'] ?? null,
                'project_id' => $data['project_id'] ?? null,
                'channel' => $data['channel'] ?? TicketChannel::Website->value,
                'status' => TicketStatus::Pending->value,
                'claimed_by_org_id' => null,
                'claimed_at' => null,
            ];

            if ($orgId) {
                $ticketData['claimed_by_org_id'] = $orgId;
                $ticketData['claimed_at'] = now();
                $ticketData['status'] = TicketStatus::Received->value;
                $ticketData['is_from_pool'] = false;
            }

            /** @var Ticket */
            $ticket = $this->repository->create($ticketData);

            $this->uploadAttachments($ticket, $data['attachments'] ?? []);

            if ($orgId) {
                $this->ogTicketExternalService->createFromTicket($ticket);
            }

            $ticket->load(['attachments', 'customer']);

            if ($orgId) {
                $orgName = $this->resolveOrganizationName($orgId);
                TicketReceivedByOrganization::dispatch($customer->id, [
                    'ticket_code' => $ticket->code,
                    'ticket_subject' => $ticket->subject,
                    'organization_name' => $orgName,
                    'customer_name' => $customer->name,
                    'tenant_subdomain' => $orgId,
                ]);
            }

            return $ticket;
        });
    }

    /**
     * Resolve organization display name for notifications. Returns null on
     * any failure so the ticket submission flow never breaks because of a
     * secondary lookup.
     */
    private function resolveOrganizationName(string $orgId): ?string
    {
        try {
            $organization = $this->organizationExternalService->getOrganizationById($orgId);

            return $organization['name'] ?? null;
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }

    public function lookup(?string $orgId, ?int $projectId): array
    {
        if (! $orgId) {
            return ['org_name' => null, 'project_name' => null];
        }

        return $this->organizationExternalService->lookupOrgAndProject($orgId, $projectId);
    }

    /**
     * Upload attachments and create records.
     *
     * @param  array<int, UploadedFile>  $files
     */
    private function uploadAttachments(Ticket $ticket, array $files): void
    {
        foreach ($files as $file) {
            if (! $file instanceof UploadedFile) {
                continue;
            }

            $path = $this->storageService->upload($file, self::ATTACHMENTS_DIRECTORY);

            $ticket->attachments()->create([
                'file_path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'size_bytes' => $file->getSize(),
            ]);
        }
    }

    public function autoReleaseStaleTickets(): array
    {
        $timeoutMinutes = Ticket::STALE_TIMEOUT_MINUTES;

        $claimedTickets = $this->repository->findStaleClaimedTickets($timeoutMinutes);

        $released = 0;

        foreach ($claimedTickets as $ticket) {
            try {
                $wasReleased = $this->ogTicketExternalService->autoReleaseOgTicket(
                    $ticket->id,
                    $ticket->claimed_by_org_id,
                    $timeoutMinutes,
                );

                if ($wasReleased) {
                    $ticket->update([
                        'status' => TicketStatus::Pending->value,
                        'claimed_by_org_id' => null,
                        'claimed_at' => null,
                    ]);
                    $released++;
                }
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return ['checked' => $claimedTickets->count(), 'released' => $released];
    }

    public function getPublicTicketInfo(string $code): Ticket
    {
        $ticket = $this->repository->findByCode($code);

        if (! $ticket) {
            throw new BusinessException(
                message: 'Không tìm thấy ticket.',
                errorCode: 'TICKET_NOT_FOUND',
                httpStatusCode: Response::HTTP_NOT_FOUND,
            );
        }

        $ticket->load('customer');
        $ticket->setAttribute('is_ratable', $ticket->status === TicketStatus::Completed && $ticket->resident_rating === null);
        $ticket->setAttribute('order_info', $this->ogTicketExternalService->getOrderInfoByTicketId($ticket->id));
        $ticket->setAttribute('quote_info', $this->ogTicketExternalService->getQuoteInfoByTicketId($ticket->id));
        $ticket->setAttribute('warranty_requests', $this->ogTicketExternalService->listWarrantyRequestsByTicketId($ticket->id));
        $ticket->setAttribute('can_request_warranty', $this->ogTicketExternalService->canRequestWarrantyByTicketId($ticket->id));
        $ticket->setAttribute('acceptance_report_info', $this->ogTicketExternalService->getAcceptanceReportInfoByTicketId($ticket->id));

        return $ticket;
    }

    public function submitWarrantyRequest(string $code, array $data, array $files): void
    {
        $ticket = $this->repository->findByCode($code);

        if (! $ticket) {
            throw new BusinessException(
                message: 'Không tìm thấy ticket.',
                errorCode: 'TICKET_NOT_FOUND',
                httpStatusCode: Response::HTTP_NOT_FOUND,
            );
        }

        $this->ogTicketExternalService->submitWarrantyRequest($ticket->id, $data, $files);
    }

    public function submitRating(string $code, array $data): void
    {
        $ticket = $this->repository->findByCode($code);

        if (! $ticket) {
            throw new BusinessException(
                message: 'Không tìm thấy ticket.',
                errorCode: 'TICKET_NOT_FOUND',
                httpStatusCode: Response::HTTP_NOT_FOUND,
            );
        }

        if ($ticket->status !== TicketStatus::Completed) {
            throw new BusinessException(
                message: 'Ticket chưa hoàn thành, chưa thể đánh giá.',
                errorCode: 'TICKET_NOT_COMPLETED',
                httpStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        if ($ticket->resident_rating !== null) {
            throw new BusinessException(
                message: 'Ticket đã được đánh giá.',
                errorCode: 'TICKET_ALREADY_RATED',
                httpStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        $ticket->update([
            'resident_rating' => $data['resident_rating'],
            'resident_rating_comment' => $data['resident_rating_comment'] ?? null,
            'resident_rated_at' => now(),
        ]);

        $this->ogTicketExternalService->syncRating($ticket->fresh());
    }

    public function submitQuoteDecision(string $code, string $action, ?string $reason): void
    {
        $ticket = $this->repository->findByCode($code);

        if (! $ticket) {
            throw new BusinessException(
                message: 'Không tìm thấy ticket.',
                errorCode: 'TICKET_NOT_FOUND',
                httpStatusCode: Response::HTTP_NOT_FOUND,
            );
        }

        $this->ogTicketExternalService->decideQuoteByTicketId($ticket->id, $action, $reason);
    }

    /**
     * Find or create customer by phone. Update name/email/address if changed.
     * Email is only overwritten when a non-empty value is provided so we
     * never erase a previously supplied email by accident.
     *
     * @param  array<string, mixed>  $data
     */
    private function resolveCustomer(array $data): \App\Modules\Platform\Customer\Models\Customer
    {
        $customer = $this->customerRepository->findByPhone($data['requester_phone']);
        $email = ! empty($data['requester_email']) ? $data['requester_email'] : null;

        if ($customer) {
            $customer->update([
                'name' => $data['requester_name'],
                'email' => $email ?? $customer->email,
                'address' => $data['address'] ?? $customer->address,
            ]);

            return $customer;
        }

        /** @var \App\Modules\Platform\Customer\Models\Customer */
        return $this->customerRepository->create([
            'name' => $data['requester_name'],
            'phone' => $data['requester_phone'],
            'email' => $email,
            'address' => $data['address'] ?? null,
        ]);
    }

    /**
     * Validate organization exists via ExternalService.
     */
    private function validateOrganization(string $orgId): void
    {
        $organization = $this->organizationExternalService->getOrganizationById($orgId);
        if (! $organization) {
            throw new BusinessException(
                message: 'Tổ chức không tồn tại.',
                errorCode: 'ORGANIZATION_NOT_FOUND',
                httpStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }
    }
}
