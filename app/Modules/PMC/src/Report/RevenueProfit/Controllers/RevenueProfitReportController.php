<?php

namespace App\Modules\PMC\Report\RevenueProfit\Controllers;

use App\Common\Controllers\BaseController;
use App\Modules\PMC\Report\RevenueProfit\Contracts\RevenueProfitReportServiceInterface;
use App\Modules\PMC\Report\RevenueProfit\Requests\RevenueProfitReportRequest;
use App\Modules\PMC\Report\RevenueProfit\Resources\RevenueProfitByProjectResource;
use App\Modules\PMC\Report\RevenueProfit\Resources\RevenueProfitByServiceCategoryResource;
use App\Modules\PMC\Report\RevenueProfit\Resources\RevenueProfitMonthlyResource;
use App\Modules\PMC\Report\RevenueProfit\Resources\RevenueProfitSummaryResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/**
 * @tags Revenue Profit Report
 */
class RevenueProfitReportController extends BaseController implements HasMiddleware
{
    public function __construct(
        protected RevenueProfitReportServiceInterface $service,
    ) {}

    /**
     * Revenue & profit report reuses commission scope because its figures are
     * derived from the same closing-period snapshots as the commission report
     * (frozen_receivable_amount + commission snapshots). Users granted
     * commission.view already have the right to see the underlying totals.
     *
     * @return list<Middleware>
     */
    public static function middleware(): array
    {
        return [
            new Middleware('permission:report-revenue-profit.view'),
        ];
    }

    /**
     * Summary KPIs: revenue, KM, costs, gross profit, MoM/QoQ deltas, insights.
     */
    public function summary(RevenueProfitReportRequest $request): RevenueProfitSummaryResource
    {
        return new RevenueProfitSummaryResource($this->service->getSummary($request->validated()));
    }

    /**
     * Monthly trend: 6 most recent months (or filter range).
     */
    public function monthly(RevenueProfitReportRequest $request): AnonymousResourceCollection
    {
        return RevenueProfitMonthlyResource::collection($this->service->getMonthly($request->validated()))
            ->additional(['success' => true]);
    }

    /**
     * Contribution and margin per project, sorted by revenue desc.
     */
    public function byProject(RevenueProfitReportRequest $request): AnonymousResourceCollection
    {
        return RevenueProfitByProjectResource::collection($this->service->getByProject($request->validated()))
            ->additional(['success' => true]);
    }

    /**
     * Profit composition by service category for the donut chart.
     */
    public function byServiceCategory(RevenueProfitReportRequest $request): AnonymousResourceCollection
    {
        return RevenueProfitByServiceCategoryResource::collection($this->service->getByServiceCategory($request->validated()))
            ->additional(['success' => true]);
    }
}
