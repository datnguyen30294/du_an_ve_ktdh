<?php

namespace App\Modules\PMC\Report\Commission\Repositories;

use App\Common\Repositories\BaseRepository;
use App\Modules\PMC\ClosingPeriod\Enums\ClosingPeriodStatus;
use App\Modules\PMC\ClosingPeriod\Enums\SnapshotRecipientType;
use App\Modules\PMC\ClosingPeriod\Models\ClosingPeriod;
use App\Modules\PMC\ClosingPeriod\Models\ClosingPeriodOrder;
use App\Modules\PMC\ClosingPeriod\Models\OrderCommissionSnapshot;
use App\Modules\PMC\Order\Models\OrderLine;
use App\Modules\PMC\Quote\Enums\QuoteLineType;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class CommissionReportRepository extends BaseRepository
{
    public const DEFAULT_PERIOD_DAYS = 30;

    public function __construct()
    {
        parent::__construct(new OrderCommissionSnapshot);
    }

    /**
     * Resolve the list of closing_period IDs that pass the filters.
     *
     * Priority:
     *   1. closing_period_id → single period (any status, for preview).
     *   2. date_from/date_to + optional project_id → overlapping closed periods.
     *   3. Default → last 30 days, closed periods only.
     *
     * @param  array<string, mixed>  $filters
     * @return list<int>
     */
    public function resolveFilteredPeriodIds(array $filters): array
    {
        if (! empty($filters['closing_period_id'])) {
            return [(int) $filters['closing_period_id']];
        }

        [$dateFrom, $dateTo] = $this->resolveDateRange($filters);

        $query = ClosingPeriod::query()
            ->where('status', ClosingPeriodStatus::Closed->value)
            ->where('period_end', '>=', $dateFrom->toDateString())
            ->where('period_start', '<=', $dateTo->toDateString());

        if (! empty($filters['project_id'])) {
            $query->where('project_id', (int) $filters['project_id']);
        }

        return $query->pluck('id')->map(fn ($id): int => (int) $id)->values()->all();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{0: Carbon, 1: Carbon}
     */
    private function resolveDateRange(array $filters): array
    {
        $dateTo = ! empty($filters['date_to'])
            ? Carbon::parse((string) $filters['date_to'])->endOfDay()
            : Carbon::now()->endOfDay();
        $dateFrom = ! empty($filters['date_from'])
            ? Carbon::parse((string) $filters['date_from'])->startOfDay()
            : $dateTo->copy()->subDays(self::DEFAULT_PERIOD_DAYS - 1)->startOfDay();

        return [$dateFrom, $dateTo];
    }

    /**
     * Describe the active filter window for display.
     *
     * @param  array<string, mixed>  $filters
     */
    public function getPeriodLabel(array $filters): string
    {
        if (! empty($filters['closing_period_id'])) {
            /** @var ClosingPeriod|null $period */
            $period = ClosingPeriod::query()->find((int) $filters['closing_period_id']);

            return $period ? "Kỳ: {$period->name}" : 'Kỳ không tồn tại';
        }

        if (empty($filters['date_from']) && empty($filters['date_to'])) {
            return self::DEFAULT_PERIOD_DAYS.' ngày gần nhất';
        }

        [$dateFrom, $dateTo] = $this->resolveDateRange($filters);

        return $dateFrom->format('d/m/Y').' - '.$dateTo->format('d/m/Y');
    }

    /**
     * Sum of amounts grouped by recipient_type for the given periods.
     *
     * Only top-level recipient types (platform/operating_company/board_of_directors/management)
     * are returned. Management = BQL total before sub-distribution to department/staff.
     *
     * @param  list<int>  $periodIds
     * @return array<string, string> map of recipient_type => decimal string
     */
    public function getPartyTotals(array $periodIds): array
    {
        $result = [
            SnapshotRecipientType::OperatingCompany->value => '0.00',
            SnapshotRecipientType::BoardOfDirectors->value => '0.00',
            SnapshotRecipientType::Management->value => '0.00',
            SnapshotRecipientType::Platform->value => '0.00',
        ];

        if (empty($periodIds)) {
            return $result;
        }

        $rows = OrderCommissionSnapshot::query()
            ->whereIn('closing_period_id', $periodIds)
            ->whereIn('recipient_type', array_keys($result))
            ->selectRaw('recipient_type, SUM(amount) as total_amount')
            ->groupBy('recipient_type')
            ->get();

        foreach ($rows as $row) {
            $type = $row->recipient_type instanceof SnapshotRecipientType
                ? $row->recipient_type->value
                : (string) $row->recipient_type;
            $result[$type] = number_format((float) $row->total_amount, 2, '.', '');
        }

        return $result;
    }

    /**
     * Estimated gross profit for the operating company.
     *
     *   gross_profit = SUM(frozen_receivable_amount)
     *                − SUM(commission to BQT + BQL + Platform)   // top-level, EXCLUDING operating_company
     *                − SUM(purchase_price × quantity on material lines)  // supplier cost
     *
     * Rationale:
     *  - Revenue is the frozen receivable amount captured when the order was added to the period.
     *  - We subtract only the commission that flows OUT of the operating company
     *    (to board of directors, board of management, and platform). Operating company's own
     *    commission share stays with the operating company and must NOT be deducted.
     *  - Material cost is read live from order_lines. This is safe because once an order is in a
     *    closed period it is financially locked (`Order::isFinanciallyLocked()`), so purchase_price
     *    and quantity cannot change — the live value is effectively a snapshot.
     *
     * @param  list<int>  $periodIds
     */
    public function getEstimatedGrossProfit(array $periodIds): string
    {
        if (empty($periodIds)) {
            return '0.00';
        }

        $revenue = (float) ClosingPeriodOrder::query()
            ->whereIn('closing_period_id', $periodIds)
            ->sum('frozen_receivable_amount');

        $externalCommission = (float) OrderCommissionSnapshot::query()
            ->whereIn('closing_period_id', $periodIds)
            ->whereIn('recipient_type', [
                SnapshotRecipientType::BoardOfDirectors->value,
                SnapshotRecipientType::Management->value,
                SnapshotRecipientType::Platform->value,
            ])
            ->sum('amount');

        $materialCost = $this->getMaterialCost($periodIds);

        return number_format($revenue - $externalCommission - $materialCost, 2, '.', '');
    }

    /**
     * Material supplier cost for orders in the given periods.
     *
     * @param  list<int>  $periodIds
     */
    private function getMaterialCost(array $periodIds): float
    {
        $orderIds = ClosingPeriodOrder::query()
            ->whereIn('closing_period_id', $periodIds)
            ->pluck('order_id')
            ->map(fn ($id): int => (int) $id)
            ->all();

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
     * Staff snapshots joined with account + department + project for the given periods.
     *
     * @param  list<int>  $periodIds
     * @return Collection<int, object> rows with: order_id, account_id, amount,
     *                                 staff_name, staff_department, project_id, project_name
     */
    public function getStaffSnapshots(array $periodIds): Collection
    {
        if (empty($periodIds)) {
            return collect();
        }

        /** @var Collection<int, OrderCommissionSnapshot> $snapshots */
        $snapshots = OrderCommissionSnapshot::query()
            ->with([
                'account:id,name',
                'account.departments:id,name',
                'closingPeriod:id,project_id',
                'closingPeriod.project:id,name',
            ])
            ->whereIn('closing_period_id', $periodIds)
            ->where('recipient_type', SnapshotRecipientType::Staff->value)
            ->get(['id', 'closing_period_id', 'order_id', 'account_id', 'amount']);

        return $snapshots->map(function (OrderCommissionSnapshot $snapshot): object {
            $project = $snapshot->closingPeriod?->project;

            return (object) [
                'order_id' => (int) $snapshot->order_id,
                'account_id' => $snapshot->account_id !== null ? (int) $snapshot->account_id : null,
                'amount' => (string) $snapshot->amount,
                'staff_name' => $snapshot->account?->name ?? '—',
                'staff_department' => $snapshot->account && $snapshot->account->relationLoaded('departments')
                    ? $snapshot->account->departments->pluck('name')->filter()->implode(', ') ?: null
                    : null,
                'project_id' => $project?->id !== null ? (int) $project->id : null,
                'project_name' => $project?->name ?? '—',
            ];
        })->values();
    }

    /**
     * Top-level snapshots (platform / operating_company / board_of_directors) for the given orders.
     *
     * @param  list<int>  $orderIds
     * @param  list<int>  $periodIds
     * @return Collection<int, object> rows with: order_id, recipient_type, amount
     */
    public function getTopLevelSnapshotsForOrders(array $orderIds, array $periodIds): Collection
    {
        if (empty($orderIds) || empty($periodIds)) {
            return collect();
        }

        return OrderCommissionSnapshot::query()
            ->whereIn('order_id', $orderIds)
            ->whereIn('closing_period_id', $periodIds)
            ->whereIn('recipient_type', [
                SnapshotRecipientType::Platform->value,
                SnapshotRecipientType::OperatingCompany->value,
                SnapshotRecipientType::BoardOfDirectors->value,
            ])
            ->get(['order_id', 'recipient_type', 'amount'])
            ->map(function (OrderCommissionSnapshot $snapshot): object {
                $type = $snapshot->recipient_type instanceof SnapshotRecipientType
                    ? $snapshot->recipient_type->value
                    : (string) $snapshot->recipient_type;

                return (object) [
                    'order_id' => (int) $snapshot->order_id,
                    'recipient_type' => $type,
                    'amount' => (string) $snapshot->amount,
                ];
            })
            ->values();
    }
}
