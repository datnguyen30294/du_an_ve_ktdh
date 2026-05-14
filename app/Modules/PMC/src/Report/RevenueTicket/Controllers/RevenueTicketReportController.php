<?php

namespace App\Modules\PMC\Report\RevenueTicket\Controllers;

use App\Common\Controllers\BaseController;
use App\Modules\PMC\Report\RevenueTicket\Contracts\RevenueTicketReportServiceInterface;
use App\Modules\PMC\Report\RevenueTicket\Requests\RevenueTicketReportRequest;
use App\Modules\PMC\Report\RevenueTicket\Resources\RevenueTicketByCategoryResource;
use App\Modules\PMC\Report\RevenueTicket\Resources\RevenueTicketByStaffResource;
use App\Modules\PMC\Report\RevenueTicket\Resources\RevenueTicketDailyResource;
use App\Modules\PMC\Report\RevenueTicket\Resources\RevenueTicketDetailResource;
use App\Modules\PMC\Report\RevenueTicket\Resources\RevenueTicketSummaryResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/**
 * @tags Revenue Ticket Report
 */
class RevenueTicketReportController extends BaseController implements HasMiddleware
{
    public function __construct(
        protected RevenueTicketReportServiceInterface $service,
    ) {}

    /**
     * @return list<Middleware>
     */
    public static function middleware(): array
    {
        return [
            new Middleware('permission:report-revenue-ticket.view'),
        ];
    }

    /**
     * Summary KPIs for revenue-by-ticket.
     */
    public function summary(RevenueTicketReportRequest $request): RevenueTicketSummaryResource
    {
        return new RevenueTicketSummaryResource($this->service->getSummary($request->validated()));
    }

    /**
     * Revenue and ticket volume grouped by ticket category.
     */
    public function byCategory(RevenueTicketReportRequest $request): AnonymousResourceCollection
    {
        return RevenueTicketByCategoryResource::collection($this->service->getByCategory($request->validated()))
            ->additional(['success' => true]);
    }

    /**
     * Revenue and ticket volume grouped by report owner.
     */
    public function byStaff(RevenueTicketReportRequest $request): AnonymousResourceCollection
    {
        return RevenueTicketByStaffResource::collection($this->service->getByStaff($request->validated()))
            ->additional(['success' => true]);
    }

    /**
     * Revenue and ticket volume per day and project.
     */
    public function daily(RevenueTicketReportRequest $request): AnonymousResourceCollection
    {
        return RevenueTicketDailyResource::collection($this->service->getDaily($request->validated()))
            ->additional(['success' => true]);
    }

    /**
     * Aggregate details by date, project, category and staff.
     */
    public function details(RevenueTicketReportRequest $request): AnonymousResourceCollection
    {
        return RevenueTicketDetailResource::collection($this->service->getDetails($request->validated()))
            ->additional(['success' => true]);
    }
}
