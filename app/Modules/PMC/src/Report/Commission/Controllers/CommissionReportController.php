<?php

namespace App\Modules\PMC\Report\Commission\Controllers;

use App\Common\Controllers\BaseController;
use App\Modules\PMC\Report\Commission\Contracts\CommissionReportServiceInterface;
use App\Modules\PMC\Report\Commission\Requests\CommissionReportRequest;
use App\Modules\PMC\Report\Commission\Resources\CommissionByStaffResource;
use App\Modules\PMC\Report\Commission\Resources\CommissionSummaryResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/**
 * @tags Commission Report
 */
class CommissionReportController extends BaseController implements HasMiddleware
{
    public function __construct(
        protected CommissionReportServiceInterface $service,
    ) {}

    /**
     * @return list<Middleware>
     */
    public static function middleware(): array
    {
        return [
            new Middleware('permission:report-commission.view'),
        ];
    }

    /**
     * Summary KPIs: party totals, estimated gross profit, platform rules.
     */
    public function summary(CommissionReportRequest $request): CommissionSummaryResource
    {
        return new CommissionSummaryResource($this->service->getSummary($request->validated()));
    }

    /**
     * Proportional attribution per staff × project.
     */
    public function byStaff(CommissionReportRequest $request): AnonymousResourceCollection
    {
        return CommissionByStaffResource::collection($this->service->getByStaff($request->validated()))
            ->additional(['success' => true]);
    }
}
