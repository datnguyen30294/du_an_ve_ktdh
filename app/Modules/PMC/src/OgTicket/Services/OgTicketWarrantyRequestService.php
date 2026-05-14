<?php

namespace App\Modules\PMC\OgTicket\Services;

use App\Common\Contracts\StorageServiceInterface;
use App\Common\Services\BaseService;
use App\Modules\PMC\OgTicket\Contracts\OgTicketWarrantyRequestServiceInterface;
use App\Modules\PMC\OgTicket\Models\OgTicketWarrantyRequest;
use App\Modules\PMC\OgTicket\Repositories\OgTicketWarrantyRequestRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;

class OgTicketWarrantyRequestService extends BaseService implements OgTicketWarrantyRequestServiceInterface
{
    private const ATTACHMENTS_DIRECTORY = 'og-ticket-warranty-attachments';

    public function __construct(
        protected OgTicketWarrantyRequestRepository $repository,
        protected StorageServiceInterface $storageService,
    ) {}

    /**
     * @param  array{subject: string, description: string}  $data
     * @param  array<int, UploadedFile>  $files
     */
    public function create(int $ogTicketId, string $requesterName, array $data, array $files): OgTicketWarrantyRequest
    {
        return $this->executeInTransaction(function () use ($ogTicketId, $requesterName, $data, $files): OgTicketWarrantyRequest {
            /** @var OgTicketWarrantyRequest $warranty */
            $warranty = $this->repository->create([
                'og_ticket_id' => $ogTicketId,
                'requester_name' => $requesterName,
                'subject' => $data['subject'],
                'description' => $data['description'],
            ]);

            $this->uploadAttachments($warranty, $files);

            return $warranty->load('attachments');
        });
    }

    /**
     * @return Collection<int, OgTicketWarrantyRequest>
     */
    public function listByOgTicketId(int $ogTicketId): Collection
    {
        return $this->repository->listByOgTicketId($ogTicketId);
    }

    /**
     * @param  array<int, UploadedFile>  $files
     */
    private function uploadAttachments(OgTicketWarrantyRequest $warranty, array $files): void
    {
        foreach ($files as $file) {
            if (! $file instanceof UploadedFile) {
                continue;
            }

            $path = $this->storageService->upload($file, self::ATTACHMENTS_DIRECTORY);

            $warranty->attachments()->create([
                'file_path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'size_bytes' => $file->getSize(),
            ]);
        }
    }
}
