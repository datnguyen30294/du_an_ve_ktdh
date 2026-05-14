<?php

namespace App\Modules\PMC\Report\Sla\Controllers;

use App\Common\Controllers\BaseController;
use App\Modules\PMC\Report\Sla\Contracts\SlaReportServiceInterface;
use App\Modules\PMC\Report\Sla\Requests\SlaReportRequest;
use App\Modules\PMC\Report\Sla\Resources\SlaByProjectResource;
use App\Modules\PMC\Report\Sla\Resources\SlaByStaffResource;
use App\Modules\PMC\Report\Sla\Resources\SlaByTicketResource;
use App\Modules\PMC\Report\Sla\Resources\SlaSummaryResource;
use App\Modules\PMC\Report\Sla\Resources\SlaTrendResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/**
 * @tags SLA Report
 */
class SlaReportController extends BaseController implements HasMiddleware
{
    public function __construct(
        protected SlaReportServiceInterface $service,
    ) {}

    /**
     * @return list<Middleware>
     */
    public static function middleware(): array
    {
        return [
            new Middleware('permission:report-sla.view'),
        ];
    }

    /**
     * Get SLA summary KPIs.
     */
    public function summary(SlaReportRequest $request): SlaSummaryResource
    {
        return new SlaSummaryResource($this->service->getSummary($request->validated()));
    }

    /**
     * Get SLA on-time rate trend by month.
     */
    public function trend(SlaReportRequest $request): AnonymousResourceCollection
    {
        return SlaTrendResource::collection($this->service->getTrend($request->validated()))
            ->additional(['success' => true]);
    }

    /**
     * Get SLA metrics grouped by project.
     */
    public function byProject(SlaReportRequest $request): AnonymousResourceCollection
    {
        return SlaByProjectResource::collection($this->service->getByProject($request->validated()))
            ->additional(['success' => true]);
    }

    /**
     * Get SLA metrics grouped by staff and project.
     */
    public function byStaff(SlaReportRequest $request): AnonymousResourceCollection
    {
        return SlaByStaffResource::collection($this->service->getByStaff($request->validated()))
            ->additional(['success' => true]);
    }

    /**
     * Get SLA detail per ticket phase (paginated).
     */
    public function byTicket(SlaReportRequest $request): AnonymousResourceCollection
    {
        return SlaByTicketResource::collection($this->service->getByTicket($request->validated()))
            ->additional(['success' => true]);
    }
}
