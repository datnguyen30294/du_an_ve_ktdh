<?php

namespace App\Modules\PMC\Report\CashFlow\Controllers;

use App\Common\Controllers\BaseController;
use App\Modules\PMC\Report\CashFlow\Contracts\CashFlowReportServiceInterface;
use App\Modules\PMC\Report\CashFlow\Requests\CashFlowReportRequest;
use App\Modules\PMC\Report\CashFlow\Resources\CashFlowDailyResource;
use App\Modules\PMC\Report\CashFlow\Resources\CashFlowSummaryResource;
use App\Modules\PMC\Report\CashFlow\Resources\CashFlowTransactionResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/**
 * @tags Cash Flow Report
 */
class CashFlowReportController extends BaseController implements HasMiddleware
{
    public function __construct(
        protected CashFlowReportServiceInterface $service,
    ) {}

    /**
     * @return list<Middleware>
     */
    public static function middleware(): array
    {
        return [
            new Middleware('permission:report-cashflow.view'),
        ];
    }

    /**
     * Get cash flow summary KPIs and per-category breakdown.
     */
    public function summary(CashFlowReportRequest $request): CashFlowSummaryResource
    {
        return new CashFlowSummaryResource($this->service->getSummary($request->validated()));
    }

    /**
     * Get cash flow aggregated by day.
     */
    public function daily(CashFlowReportRequest $request): AnonymousResourceCollection
    {
        return CashFlowDailyResource::collection($this->service->getDaily($request->validated()))
            ->additional(['success' => true]);
    }

    /**
     * Get paginated cash flow transaction list.
     */
    public function transactions(CashFlowReportRequest $request): AnonymousResourceCollection
    {
        return CashFlowTransactionResource::collection($this->service->getTransactions($request->validated()))
            ->additional(['success' => true]);
    }
}
