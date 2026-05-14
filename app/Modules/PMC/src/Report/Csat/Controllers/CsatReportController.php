<?php

namespace App\Modules\PMC\Report\Csat\Controllers;

use App\Common\Controllers\BaseController;
use App\Modules\PMC\Report\Csat\Contracts\CsatReportServiceInterface;
use App\Modules\PMC\Report\Csat\Requests\CsatReportRequest;
use App\Modules\PMC\Report\Csat\Resources\CsatByProjectResource;
use App\Modules\PMC\Report\Csat\Resources\CsatSummaryResource;
use App\Modules\PMC\Report\Csat\Resources\CsatTrendResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/**
 * @tags CSAT Report
 */
class CsatReportController extends BaseController implements HasMiddleware
{
    public function __construct(
        protected CsatReportServiceInterface $service,
    ) {}

    /**
     * @return list<Middleware>
     */
    public static function middleware(): array
    {
        return [
            new Middleware('permission:report-csat.view'),
        ];
    }

    /**
     * Get CSAT summary KPIs.
     */
    public function summary(CsatReportRequest $request): CsatSummaryResource
    {
        return new CsatSummaryResource($this->service->getSummary($request->validated()));
    }

    /**
     * Get CSAT average score trend by month.
     */
    public function trend(CsatReportRequest $request): AnonymousResourceCollection
    {
        return CsatTrendResource::collection($this->service->getTrend($request->validated()))
            ->additional(['success' => true]);
    }

    /**
     * Get CSAT metrics grouped by project.
     */
    public function byProject(CsatReportRequest $request): AnonymousResourceCollection
    {
        return CsatByProjectResource::collection($this->service->getByProject($request->validated()))
            ->additional(['success' => true]);
    }
}
