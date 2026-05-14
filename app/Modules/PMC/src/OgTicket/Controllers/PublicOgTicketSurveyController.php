<?php

namespace App\Modules\PMC\OgTicket\Controllers;

use App\Common\Controllers\BaseController;
use App\Modules\PMC\OgTicket\Contracts\OgTicketSurveyServiceInterface;
use App\Modules\PMC\OgTicket\Requests\UpsertOgTicketSurveyRequest;
use App\Modules\PMC\OgTicket\Resources\OgTicketSurveyResource;
use Illuminate\Http\JsonResponse;

/**
 * @tags Public OG Ticket Survey
 */
class PublicOgTicketSurveyController extends BaseController
{
    public function __construct(
        protected OgTicketSurveyServiceInterface $service,
    ) {}

    /**
     * Get the survey for a ticket by its public code.
     */
    public function show(string $code): JsonResponse
    {
        $survey = $this->service->getOrCreateByTicketCode($code);
        $survey->load(['attachments', 'surveyor']);

        return OgTicketSurveyResource::make($survey)->response();
    }

    /**
     * Upsert (note + append attachments) for a ticket by code.
     */
    public function upsert(UpsertOgTicketSurveyRequest $request, string $code): JsonResponse
    {
        $data = $request->validated();
        $data['attachments'] = $request->file('attachments') ?? [];

        $survey = $this->service->upsertByTicketCode($code, $data);
        $survey->load(['attachments', 'surveyor']);

        return OgTicketSurveyResource::make($survey)->response();
    }

    /**
     * Delete a single attachment from the survey.
     */
    public function deleteAttachment(string $code, int $attachmentId): JsonResponse
    {
        $survey = $this->service->deleteAttachmentByTicketCode($code, $attachmentId);
        $survey->load(['attachments', 'surveyor']);

        return OgTicketSurveyResource::make($survey)->response();
    }
}
