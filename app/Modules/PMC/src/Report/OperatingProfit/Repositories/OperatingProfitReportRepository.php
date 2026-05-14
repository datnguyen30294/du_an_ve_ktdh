<?php

namespace App\Modules\PMC\Report\OperatingProfit\Repositories;

use App\Common\Repositories\BaseRepository;
use App\Modules\PMC\ClosingPeriod\Enums\ClosingPeriodStatus;
use App\Modules\PMC\ClosingPeriod\Enums\SnapshotRecipientType;
use App\Modules\PMC\ClosingPeriod\Models\ClosingPeriod;
use App\Modules\PMC\ClosingPeriod\Models\ClosingPeriodOrder;
use App\Modules\PMC\ClosingPeriod\Models\OrderCommissionSnapshot;
use App\Modules\PMC\Order\Models\OrderLine;
use App\Modules\PMC\Quote\Enums\QuoteLineType;
use App\Modules\PMC\Report\Commission\Repositories\CommissionReportRepository;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class OperatingProfitReportRepository extends BaseRepository
{
    public function __construct(
        protected CommissionReportRepository $commissionRepository,
    ) {
        parent::__construct(new ClosingPeriodOrder);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return list<int>
     */
    public function resolveFilteredPeriodIds(array $filters): array
    {
        return $this->commissionRepository->resolveFilteredPeriodIds($filters);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function getPeriodLabel(array $filters): string
    {
        return $this->commissionRepository->getPeriodLabel($filters);
    }

    public function findPeriodById(int $id): ?ClosingPeriod
    {
        return ClosingPeriod::query()->find($id);
    }

    /**
     * @return Collection<int, ClosingPeriod>
     */
    public function getClosedPeriodsInDateRange(Carbon $from, Carbon $to, ?int $projectId = null): Collection
    {
        $query = ClosingPeriod::query()
            ->where('status', ClosingPeriodStatus::Closed->value)
            ->whereDate('period_end', '>=', $from->toDateString())
            ->whereDate('period_end', '<=', $to->toDateString());

        if ($projectId !== null) {
            $query->where('project_id', $projectId);
        }

        return $query->get();
    }

    /**
     * @param  list<int>  $periodIds
     * @return list<int>
     */
    public function getOrderIdsForPeriods(array $periodIds): array
    {
        if (empty($periodIds)) {
            return [];
        }

        return ClosingPeriodOrder::query()
            ->whereIn('closing_period_id', $periodIds)
            ->pluck('order_id')
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Commission amount flowing TO the operating company for the given periods.
     *
     * @param  list<int>  $periodIds
     */
    public function getOperatingCommission(array $periodIds): float
    {
        if (empty($periodIds)) {
            return 0.0;
        }

        return (float) OrderCommissionSnapshot::query()
            ->whereIn('closing_period_id', $periodIds)
            ->where('recipient_type', SnapshotRecipientType::OperatingCompany->value)
            ->sum('amount');
    }

    /**
     * Revenue booked from material lines (unit_price × quantity) for the given orders.
     *
     * @param  list<int>  $orderIds
     */
    public function getMaterialRevenue(array $orderIds): float
    {
        if (empty($orderIds)) {
            return 0.0;
        }

        return (float) OrderLine::query()
            ->whereIn('order_id', $orderIds)
            ->where('line_type', QuoteLineType::Material->value)
            ->selectRaw('COALESCE(SUM(unit_price * quantity), 0) as total')
            ->value('total');
    }

    /**
     * Supplier cost on material lines (purchase_price × quantity) for the given orders.
     *
     * @param  list<int>  $orderIds
     */
    public function getMaterialCost(array $orderIds): float
    {
        if (empty($orderIds)) {
            return 0.0;
        }

        return (float) OrderLine::query()
            ->whereIn('order_id', $orderIds)
            ->where('line_type', QuoteLineType::Material->value)
            ->whereNotNull('purchase_price')
            ->selectRaw('COALESCE(SUM(purchase_price * quantity), 0) as total')
            ->value('total');
    }

    /**
     * @param  list<int>  $periodIds
     * @return Collection<int, ClosingPeriod>
     */
    public function getPeriodsWithProject(array $periodIds): Collection
    {
        if (empty($periodIds)) {
            return collect();
        }

        return ClosingPeriod::query()
            ->with('project:id,name')
            ->whereIn('id', $periodIds)
            ->get();
    }

    /**
     * @param  list<int>  $periodIds
     * @return Collection<int, ClosingPeriodOrder>
     */
    public function getClosingPeriodOrders(array $periodIds): Collection
    {
        if (empty($periodIds)) {
            return collect();
        }

        return ClosingPeriodOrder::query()
            ->whereIn('closing_period_id', $periodIds)
            ->get(['id', 'closing_period_id', 'order_id']);
    }
}
