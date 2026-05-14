<?php

namespace App\Modules\PMC\OgTicket\Services;

use App\Common\Contracts\StorageServiceInterface;
use App\Common\Exceptions\BusinessException;
use App\Common\Models\TenantAttachment;
use App\Common\Services\BaseService;
use App\Modules\PMC\OgTicket\Contracts\OgTicketSurveyServiceInterface;
use App\Modules\PMC\OgTicket\Models\OgTicketSurvey;
use App\Modules\PMC\OgTicket\Repositories\OgTicketRepository;
use App\Modules\PMC\OgTicket\Repositories\OgTicketSurveyRepository;
use App\Modules\Platform\Ticket\Repositories\TicketRepository;
use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpFoundation\Response;

class OgTicketSurveyService extends BaseService implements OgTicketSurveyServiceInterface
{
    public const ATTACHMENTS_DIRECTORY = 'og-ticket-surveys';

    public function __construct(
        private OgTicketSurveyRepository $repository,
        private OgTicketRepository $ogTicketRepository,
        private TicketRepository $ticketRepository,
        private StorageServiceInterface $storageService,
    ) {}

    public function getOrCreateForOgTicket(int $ogTicketId): OgTicketSurvey
    {
        $existing = $this->repository->findByOgTicketId($ogTicketId);

        if ($existing) {
            return $existing;
        }

        $this->assertOgTicketExists($ogTicketId);

        /** @var OgTicketSurvey $survey */
        $survey = $this->repository->create([
            'og_ticket_id' => $ogTicketId,
        ]);

        return $this->repository->findByOgTicketId($ogTicketId) ?? $survey;
    }

    public function upsert(int $ogTicketId, array $data): OgTicketSurvey
    {
        $this->assertOgTicketExists($ogTicketId);

        return $this->executeInTransaction(function () use ($ogTicketId, $data): OgTicketSurvey {
            $survey = $this->repository->findByOgTicketId($ogTicketId)
                ?? $this->repository->create(['og_ticket_id' => $ogTicketId]);

            $payload = [
                'note' => $data['note'] ?? null,
                'surveyed_by' => auth()->id(),
                'surveyed_at' => now(),
            ];

            $this->repository->update($survey->id, $payload);

            $files = $data['attachments'] ?? [];
            foreach ($files as $file) {
                if (! $file instanceof UploadedFile) {
                    continue;
                }

                $path = $this->storageService->upload($file, self::ATTACHMENTS_DIRECTORY);

                $survey->attachments()->create([
                    'file_path' => $path,
                    'original_name' => $file->getClientOriginalName(),
                    'mime_type' => $file->getClientMimeType(),
                    'size_bytes' => $file->getSize(),
                ]);
            }

            /** @var OgTicketSurvey */
            return $this->repository->findByOgTicketId($ogTicketId);
        });
    }

    public function deleteAttachment(int $ogTicketId, int $attachmentId): OgTicketSurvey
    {
        $survey = $this->repository->findByOgTicketId($ogTicketId);

        if (! $survey) {
            throw new BusinessException(
                message: 'Không tìm thấy khảo sát.',
                errorCode: 'OG_TICKET_SURVEY_NOT_FOUND',
                httpStatusCode: Response::HTTP_NOT_FOUND,
            );
        }

        /** @var TenantAttachment|null $attachment */
        $attachment = $survey->attachments()->whereKey($attachmentId)->first();

        if (! $attachment) {
            throw new BusinessException(
                message: 'Không tìm thấy tệp đính kèm.',
                errorCode: 'OG_TICKET_SURVEY_ATTACHMENT_NOT_FOUND',
                httpStatusCode: Response::HTTP_NOT_FOUND,
            );
        }

        if ($attachment->file_path) {
            $this->storageService->delete($attachment->file_path);
        }

        $attachment->delete();

        /** @var OgTicketSurvey */
        return $this->repository->findByOgTicketId($ogTicketId);
    }

    public function getOrCreateByTicketCode(string $ticketCode): OgTicketSurvey
    {
        return $this->getOrCreateForOgTicket($this->resolveOgTicketIdByCode($ticketCode));
    }

    public function upsertByTicketCode(string $ticketCode, array $data): OgTicketSurvey
    {
        return $this->upsert($this->resolveOgTicketIdByCode($ticketCode), $data);
    }

    public function deleteAttachmentByTicketCode(string $ticketCode, int $attachmentId): OgTicketSurvey
    {
        return $this->deleteAttachment($this->resolveOgTicketIdByCode($ticketCode), $attachmentId);
    }

    private function assertOgTicketExists(int $ogTicketId): void
    {
        $exists = $this->ogTicketRepository->findById($ogTicketId);

        if (! $exists) {
            throw new BusinessException(
                message: 'Không tìm thấy OG Ticket.',
                errorCode: 'OG_TICKET_NOT_FOUND',
                httpStatusCode: Response::HTTP_NOT_FOUND,
            );
        }
    }

    private function resolveOgTicketIdByCode(string $ticketCode): int
    {
        $ticket = $this->ticketRepository->findByCode($ticketCode);

        if (! $ticket) {
            throw new BusinessException(
                message: 'Không tìm thấy yêu cầu.',
                errorCode: 'TICKET_NOT_FOUND',
                httpStatusCode: Response::HTTP_NOT_FOUND,
            );
        }

        $ogTicket = $this->ogTicketRepository->findLatestByTicketId($ticket->id);

        if (! $ogTicket) {
            throw new BusinessException(
                message: 'Yêu cầu chưa được tiếp nhận xử lý.',
                errorCode: 'OG_TICKET_NOT_FOUND',
                httpStatusCode: Response::HTTP_NOT_FOUND,
            );
        }

        return $ogTicket->id;
    }
}
