<?php

namespace App\Modules\PMC\Report\Overview\Controllers;

use App\Common\Controllers\BaseController;
use App\Modules\PMC\Report\Overview\Contracts\OverviewReportServiceInterface;
use App\Modules\PMC\Report\Overview\Requests\OverviewReportRequest;
use App\Modules\PMC\Report\Overview\Resources\OverviewSummaryResource;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/**
 * @tags Overview Report
 */
class OverviewReportController extends BaseController implements HasMiddleware
{
    public function __construct(
        protected OverviewReportServiceInterface $service,
    ) {}

    /**
     * @return list<Middleware>
     */
    public static function middleware(): array
    {
        return [
            new Middleware('permission:report-overview.view'),
        ];
    }

    /**
     * Aggregated KPI: SLA, Revenue & Profit, CSAT, Commission allocation.
     */
    public function summary(OverviewReportRequest $request): OverviewSummaryResource
    {
        return new OverviewSummaryResource($this->service->getSummary($request->validated()));
    }
}
