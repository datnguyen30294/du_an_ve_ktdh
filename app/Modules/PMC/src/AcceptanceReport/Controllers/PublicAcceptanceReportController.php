<?php

namespace App\Modules\PMC\AcceptanceReport\Controllers;

use App\Common\Controllers\BaseController;
use App\Common\Exceptions\BusinessException;
use App\Modules\PMC\AcceptanceReport\Contracts\AcceptanceReportServiceInterface;
use App\Modules\PMC\AcceptanceReport\Requests\ConfirmAcceptanceReportRequest;
use App\Modules\PMC\AcceptanceReport\Requests\UpdateAcceptanceReportRequest;
use App\Modules\PMC\AcceptanceReport\Resources\AcceptanceReportResource;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Public (no-auth) access to acceptance reports via share token.
 *
 * @tags Acceptance Report (Public)
 */
class PublicAcceptanceReportController extends BaseController
{
    public function __construct(
        protected AcceptanceReportServiceInterface $service,
    ) {}

    /**
     * View the report by share token.
     */
    public function show(string $token): JsonResponse
    {
        $report = $this->service->findByToken($token);

        if (! $report) {
            throw new BusinessException(
                message: 'Không tìm thấy biên bản.',
                errorCode: 'ACCEPTANCE_REPORT_NOT_FOUND',
                httpStatusCode: Response::HTTP_NOT_FOUND,
            );
        }

        return AcceptanceReportResource::make($report)->response();
    }

    /**
     * Update content / customer info via share token.
     */
    public function update(UpdateAcceptanceReportRequest $request, string $token): JsonResponse
    {
        $updated = $this->service->updateByToken($token, $request->validated());

        return AcceptanceReportResource::make($updated)->response();
    }

    /**
     * Confirm the acceptance report by resident via share token.
     */
    public function confirm(ConfirmAcceptanceReportRequest $request, string $token): JsonResponse
    {
        $confirmed = $this->service->confirmByToken($token, $request->validated());

        return AcceptanceReportResource::make($confirmed)->response();
    }
}
