<?php

namespace App\Modules\PMC\Report\RevenueProfit\Repositories;

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

class RevenueProfitReportRepository extends BaseRepository
{
    public function __construct(
        protected CommissionReportRepository $commissionRepository,
    ) {
        parent::__construct(new ClosingPeriodOrder);
    }

    /**
     * Resolve the list of closing_period IDs that pass the filters.
     * Delegates to CommissionReportRepository so the two reports always
     * see the same scope.
     *
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
     * @param  list<int>  $ids
     * @return Collection<int, ClosingPeriod>
     */
    public function findPeriodsByIds(array $ids): Collection
    {
        if (empty($ids)) {
            return collect();
        }

        return ClosingPeriod::query()->whereIn('id', $ids)->get();
    }

    /**
     * Load closed closing_periods whose period_end falls inside the given window,
     * optionally scoped to a single project.
     *
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
     * Sum of frozen_receivable_amount for the given periods, optionally
     * limited to a subset of order_ids.
     *
     * @param  list<int>  $periodIds
     * @param  list<int>|null  $orderIds
     */
    public function getRevenue(array $periodIds, ?array $orderIds = null): float
    {
        if (empty($periodIds)) {
            return 0.0;
        }

        $query = ClosingPeriodOrder::query()->whereIn('closing_period_id', $periodIds);

        if ($orderIds !== null) {
            if (empty($orderIds)) {
                return 0.0;
            }
            $query->whereIn('order_id', $orderIds);
        }

        return (float) $query->sum('frozen_receivable_amount');
    }

    /**
     * Commission flowing OUT of the operating company (BoD + Management + Platform).
     *
     * @param  list<int>  $periodIds
     * @param  list<int>|null  $orderIds
     */
    public function getExternalCommission(array $periodIds, ?array $orderIds = null): float
    {
        if (empty($periodIds)) {
            return 0.0;
        }

        $query = OrderCommissionSnapshot::query()
            ->whereIn('closing_period_id', $periodIds)
            ->whereIn('recipient_type', [
                SnapshotRecipientType::BoardOfDirectors->value,
                SnapshotRecipientType::Management->value,
                SnapshotRecipientType::Platform->value,
            ]);

        if ($orderIds !== null) {
            if (empty($orderIds)) {
                return 0.0;
            }
            $query->whereIn('order_id', $orderIds);
        }

        return (float) $query->sum('amount');
    }

    /**
     * Material supplier cost for the given orders.
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
     * Closing periods loaded with project relation, used for project & monthly grouping.
     *
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
            ->get(['id', 'closing_period_id', 'order_id', 'frozen_receivable_amount']);
    }

    /**
     * Line-level rows joined with catalog_items + service_categories for the given orders.
     * Used for the by-service-category breakdown.
     *
     * @param  list<int>  $orderIds
     * @return Collection<int, object>
     */
    public function getServiceCategoryLineRows(array $orderIds): Collection
    {
        if (empty($orderIds)) {
            return collect();
        }

        $rows = OrderLine::query()
            ->leftJoin('catalog_items', function ($join): void {
                $join->on('catalog_items.id', '=', 'order_lines.reference_id')
                    ->whereNull('catalog_items.deleted_at');
            })
            ->leftJoin('service_categories', function ($join): void {
                $join->on('service_categories.id', '=', 'catalog_items.service_category_id')
                    ->whereNull('service_categories.deleted_at');
            })
            ->whereIn('order_lines.order_id', $orderIds)
            ->select([
                'order_lines.id as line_id',
                'order_lines.order_id',
                'order_lines.line_type',
                'order_lines.quantity',
                'order_lines.unit_price',
                'order_lines.purchase_price',
                'service_categories.id as service_category_id',
                'service_categories.name as service_category_name',
            ])
            ->get();

        return $rows->map(function ($row): object {
            $lineType = $row->line_type instanceof QuoteLineType
                ? $row->line_type->value
                : (string) $row->line_type;

            return (object) [
                'line_id' => (int) $row->line_id,
                'order_id' => (int) $row->order_id,
                'line_type' => $lineType,
                'quantity' => (int) $row->quantity,
                'unit_price' => (float) $row->unit_price,
                'purchase_price' => $row->purchase_price !== null ? (float) $row->purchase_price : null,
                'service_category_id' => $row->service_category_id !== null ? (int) $row->service_category_id : null,
                'service_category_name' => $row->service_category_name !== null ? (string) $row->service_category_name : null,
            ];
        })->values();
    }
}
