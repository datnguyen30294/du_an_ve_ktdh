<?php

namespace App\Modules\PMC\OgTicket\Contracts;

use App\Modules\PMC\OgTicket\Models\OgTicketSurvey;

interface OgTicketSurveyServiceInterface
{
    public function getOrCreateForOgTicket(int $ogTicketId): OgTicketSurvey;

    /**
     * @param  array{note?: string|null, attachments?: array<int, \Illuminate\Http\UploadedFile>}  $data
     */
    public function upsert(int $ogTicketId, array $data): OgTicketSurvey;

    public function deleteAttachment(int $ogTicketId, int $attachmentId): OgTicketSurvey;

    public function getOrCreateByTicketCode(string $ticketCode): OgTicketSurvey;

    /**
     * @param  array{note?: string|null, attachments?: array<int, \Illuminate\Http\UploadedFile>}  $data
     */
    public function upsertByTicketCode(string $ticketCode, array $data): OgTicketSurvey;

    public function deleteAttachmentByTicketCode(string $ticketCode, int $attachmentId): OgTicketSurvey;
}
