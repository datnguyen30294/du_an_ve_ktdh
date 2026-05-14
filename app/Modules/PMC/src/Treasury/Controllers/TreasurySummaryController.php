<?php

namespace App\Modules\PMC\Treasury\Controllers;

use App\Common\Controllers\BaseController;
use App\Modules\PMC\Treasury\Contracts\TreasuryServiceInterface;
use App\Modules\PMC\Treasury\Requests\TreasurySummaryRequest;
use App\Modules\PMC\Treasury\Resources\TreasuryKpiResource;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/**
 * @tags Treasury
 */
class TreasurySummaryController extends BaseController implements HasMiddleware
{
    public function __construct(
        protected TreasuryServiceInterface $service,
    ) {}

    /**
     * @return list<Middleware>
     */
    public static function middleware(): array
    {
        return [
            new Middleware('permission:treasury.view'),
        ];
    }

    /**
     * Return Treasury KPI summary (balance, inflow, outflow, per-category breakdowns).
     */
    public function index(TreasurySummaryRequest $request): TreasuryKpiResource
    {
        return new TreasuryKpiResource($this->service->getSummary($request->validated()));
    }
}
