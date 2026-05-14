<?php

namespace App\Modules\PMC\AcceptanceReport\Controllers;

use App\Common\Controllers\BaseController;
use App\Modules\PMC\AcceptanceReport\Contracts\AcceptanceReportServiceInterface;
use App\Modules\PMC\AcceptanceReport\Requests\UpdateAcceptanceReportRequest;
use App\Modules\PMC\AcceptanceReport\Requests\UploadSignedAcceptanceReportRequest;
use App\Modules\PMC\AcceptanceReport\Resources\AcceptanceReportResource;
use Illuminate\Http\JsonResponse;

/**
 * @tags Acceptance Report
 */
class AcceptanceReportController extends BaseController
{
    public function __construct(
        protected AcceptanceReportServiceInterface $service,
    ) {}

    /**
     * Get-or-create the acceptance report for an order.
     */
    public function show(int $orderId): JsonResponse
    {
        $report = $this->service->getOrCreateForOrder($orderId);

        return AcceptanceReportResource::make($report)->response();
    }

    /**
     * Update the acceptance report attached to an order.
     */
    public function update(UpdateAcceptanceReportRequest $request, int $orderId): JsonResponse
    {
        $report = $this->service->getOrCreateForOrder($orderId);
        $updated = $this->service->update($report->id, $request->validated());

        return AcceptanceReportResource::make($updated)->response();
    }

    /**
     * Re-render the acceptance report's content from the current template.
     * Overwrites `content_html`; preserves share token, customer fields and note.
     */
    public function regenerate(int $orderId): JsonResponse
    {
        $report = $this->service->getOrCreateForOrder($orderId);
        $updated = $this->service->regenerate($report->id);

        return AcceptanceReportResource::make($updated)->response();
    }

    /**
     * Delete the acceptance report attached to an order.
     */
    public function destroy(int $orderId): JsonResponse
    {
        $report = $this->service->findByOrderId($orderId);

        if ($report) {
            $this->service->delete($report->id);
        }

        return response()->json([
            'success' => true,
            'message' => 'Đã xoá biên bản nghiệm thu.',
        ]);
    }

    /**
     * Upload the scanned (signed) acceptance report file for an order.
     * Replaces any previously uploaded file.
     */
    public function uploadSigned(UploadSignedAcceptanceReportRequest $request, int $orderId): JsonResponse
    {
        /** @var \Illuminate\Http\UploadedFile $file */
        $file = $request->file('file');

        $report = $this->service->uploadSignedFile($orderId, $file);

        return AcceptanceReportResource::make($report)->response();
    }

    /**
     * Remove the uploaded signed acceptance report file for an order.
     */
    public function deleteSigned(int $orderId): JsonResponse
    {
        $report = $this->service->deleteSignedFile($orderId);

        return AcceptanceReportResource::make($report)->response();
    }
}
