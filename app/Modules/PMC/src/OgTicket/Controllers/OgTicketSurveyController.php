<?php

namespace App\Modules\PMC\OgTicket\Controllers;

use App\Common\Controllers\BaseController;
use App\Modules\PMC\OgTicket\Contracts\OgTicketSurveyServiceInterface;
use App\Modules\PMC\OgTicket\Requests\UpsertOgTicketSurveyRequest;
use App\Modules\PMC\OgTicket\Resources\OgTicketSurveyResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/**
 * @tags OG Ticket Survey
 */
class OgTicketSurveyController extends BaseController implements HasMiddleware
{
    public function __construct(
        protected OgTicketSurveyServiceInterface $service,
    ) {}

    /**
     * @return list<Middleware>
     */
    public static function middleware(): array
    {
        return [
            new Middleware('permission:og-tickets.view', only: ['show']),
            new Middleware('permission:og-tickets.update', only: ['upsert', 'deleteAttachment']),
        ];
    }

    /**
     * Get the survey for an OG Ticket (auto-create empty record if missing).
     */
    public function show(int $ogTicketId): JsonResponse
    {
        $survey = $this->service->getOrCreateForOgTicket($ogTicketId);
        $survey->load(['attachments', 'surveyor']);

        return OgTicketSurveyResource::make($survey)->response();
    }

    /**
     * Update note and append new attachments. Existing attachments are kept.
     */
    public function upsert(UpsertOgTicketSurveyRequest $request, int $ogTicketId): JsonResponse
    {
        $data = $request->validated();
        $data['attachments'] = $request->file('attachments') ?? [];

        $survey = $this->service->upsert($ogTicketId, $data);
        $survey->load(['attachments', 'surveyor']);

        return OgTicketSurveyResource::make($survey)->response();
    }

    /**
     * Delete a single attachment from the survey.
     */
    public function deleteAttachment(int $ogTicketId, int $attachmentId): JsonResponse
    {
        $survey = $this->service->deleteAttachment($ogTicketId, $attachmentId);
        $survey->load(['attachments', 'surveyor']);

        return OgTicketSurveyResource::make($survey)->response();
    }
}
