<?php

namespace App\Modules\PMC\Report\OperatingProfit\Controllers;

use App\Common\Controllers\BaseController;
use App\Modules\PMC\Report\OperatingProfit\Contracts\OperatingProfitReportServiceInterface;
use App\Modules\PMC\Report\OperatingProfit\Requests\OperatingProfitReportRequest;
use App\Modules\PMC\Report\OperatingProfit\Resources\OperatingProfitByProjectResource;
use App\Modules\PMC\Report\OperatingProfit\Resources\OperatingProfitMonthlyResource;
use App\Modules\PMC\Report\OperatingProfit\Resources\OperatingProfitSummaryResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/**
 * @tags Operating Company Profit Report
 */
class OperatingProfitReportController extends BaseController implements HasMiddleware
{
    public function __construct(
        protected OperatingProfitReportServiceInterface $service,
    ) {}

    /**
     * Reuses commission.view because the figures come from the same closing-period
     * snapshots (material lines of orders in the period + operating_company commission share).
     *
     * @return list<Middleware>
     */
    public static function middleware(): array
    {
        return [
            new Middleware('permission:report-operating-profit.view'),
        ];
    }

    /**
     * Summary KPIs: material profit, commission profit, total, shares, MoM/QoQ, insights.
     */
    public function summary(OperatingProfitReportRequest $request): OperatingProfitSummaryResource
    {
        return new OperatingProfitSummaryResource($this->service->getSummary($request->validated()));
    }

    /**
     * Monthly trend: 6 most recent months (or filter range) — stacked material vs commission.
     */
    public function monthly(OperatingProfitReportRequest $request): AnonymousResourceCollection
    {
        return OperatingProfitMonthlyResource::collection($this->service->getMonthly($request->validated()))
            ->additional(['success' => true]);
    }

    /**
     * Profit per project (material + commission), sorted by total desc.
     */
    public function byProject(OperatingProfitReportRequest $request): AnonymousResourceCollection
    {
        return OperatingProfitByProjectResource::collection($this->service->getByProject($request->validated()))
            ->additional(['success' => true]);
    }
}
