<?php

namespace App\Modules\PMC\Report\OperatingProfit\Services;

use App\Common\Services\BaseService;
use App\Modules\PMC\ClosingPeriod\Models\ClosingPeriod;
use App\Modules\PMC\Report\OperatingProfit\Contracts\OperatingProfitReportServiceInterface;
use App\Modules\PMC\Report\OperatingProfit\Repositories\OperatingProfitReportRepository;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Operating company profit, decomposed by two streams:
 *   - Material markup  (unit_price − purchase_price) × quantity, on material lines
 *   - Commission share operating_company receives on closed snapshots
 *
 * Total operating profit = material_profit + commission_profit.
 */
class OperatingProfitReportService extends BaseService implements OperatingProfitReportServiceInterface
{
    public const MONTHLY_ENDPOINT_WINDOW = 6;

    public const SUMMARY_INSIGHT_WINDOW = 12;

    public function __construct(protected OperatingProfitReportRepository $repository) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function getSummary(array $filters): array
    {
        $periodIds = $this->repository->resolveFilteredPeriodIds($filters);
        $orderIds = $this->repository->getOrderIdsForPeriods($periodIds);

        $materialRevenue = $this->repository->getMaterialRevenue($orderIds);
        $materialCost = $this->repository->getMaterialCost($orderIds);
        $materialProfit = $materialRevenue - $materialCost;

        $commissionProfit = $this->repository->getOperatingCommission($periodIds);
        $totalProfit = $materialProfit + $commissionProfit;

        $monthly = $this->buildMonthlyData($filters, self::SUMMARY_INSIGHT_WINDOW);
        $monthsWithData = $monthly->filter(fn (array $row): bool => (float) $row['total_profit'] != 0.0)->values();

        $lastMonth = $monthsWithData->last();
        $prevMonth = $monthsWithData->count() >= 2
            ? $monthsWithData->slice(-2, 1)->first()
            : null;

        $momTotal = $this->percentDelta($lastMonth['total_profit'] ?? null, $prevMonth['total_profit'] ?? null);
        $momMaterial = $this->percentDelta($lastMonth['material_profit'] ?? null, $prevMonth['material_profit'] ?? null);
        $momCommission = $this->percentDelta($lastMonth['commission_profit'] ?? null, $prevMonth['commission_profit'] ?? null);

        $quarters = $this->groupByQuarter($monthly);
        $quartersWithData = $quarters->filter(fn (array $row): bool => (float) $row['total_profit'] != 0.0)->values();
        $lastQuarter = $quartersWithData->last();
        $prevQuarter = $quartersWithData->count() >= 2
            ? $quartersWithData->slice(-2, 1)->first()
            : null;
        $qoqTotal = $this->percentDelta($lastQuarter['total_profit'] ?? null, $prevQuarter['total_profit'] ?? null);

        $last6 = $monthsWithData->slice(-6);
        $avgProfit6m = $last6->isEmpty()
            ? 0.0
            : round((float) $last6->avg(fn (array $r): float => (float) $r['total_profit']), 2);

        $materialShare = $this->safeShare($materialProfit, $totalProfit);
        $commissionShare = $this->safeShare($commissionProfit, $totalProfit);

        $byProject = $this->getByProject($filters);

        $insights = $this->buildInsights(
            totalProfit: $totalProfit,
            materialProfit: $materialProfit,
            commissionProfit: $commissionProfit,
            momTotal: $momTotal,
            lastMonth: $lastMonth,
            prevMonth: $prevMonth,
            byProject: $byProject,
        );

        return [
            'period_label' => $this->repository->getPeriodLabel($filters),
            'material_revenue' => $this->formatAmount($materialRevenue),
            'material_cost' => $this->formatAmount($materialCost),
            'material_profit' => $this->formatAmount($materialProfit),
            'material_share_percent' => $materialShare,
            'commission_profit' => $this->formatAmount($commissionProfit),
            'commission_share_percent' => $commissionShare,
            'total_profit' => $this->formatAmount($totalProfit),
            'mom_total_percent' => $momTotal,
            'mom_material_percent' => $momMaterial,
            'mom_commission_percent' => $momCommission,
            'qoq_total_percent' => $qoqTotal,
            'avg_profit_6_months' => $avgProfit6m,
            'last_month_label' => $lastMonth['month'] ?? '',
            'prev_month_label' => $prevMonth['month'] ?? '',
            'insights' => $insights,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return list<array<string, mixed>>
     */
    public function getMonthly(array $filters): array
    {
        $rows = $this->buildMonthlyData($filters, self::MONTHLY_ENDPOINT_WINDOW);

        return $rows->map(fn (array $row): array => [
            'month' => (string) $row['month'],
            'year_month' => (string) $row['year_month'],
            'material_profit' => $this->formatAmount((float) $row['material_profit']),
            'commission_profit' => $this->formatAmount((float) $row['commission_profit']),
            'total_profit' => $this->formatAmount((float) $row['total_profit']),
        ])->values()->all();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return list<array<string, mixed>>
     */
    public function getByProject(array $filters): array
    {
        $periodIds = $this->repository->resolveFilteredPeriodIds($filters);
        if (empty($periodIds)) {
            return [];
        }

        $periods = $this->repository->getPeriodsWithProject($periodIds);
        $cpos = $this->repository->getClosingPeriodOrders($periodIds);

        /** @var Collection<int|string, Collection<int, ClosingPeriod>> $byProject */
        $byProject = $periods->groupBy('project_id');

        /** @var Collection<int|string, Collection<int, \App\Modules\PMC\ClosingPeriod\Models\ClosingPeriodOrder>> $cposByPeriod */
        $cposByPeriod = $cpos->groupBy('closing_period_id');

        $rows = [];
        $totalAcrossProjects = 0.0;
        foreach ($byProject as $projectId => $projPeriods) {
            $projPeriodIds = $projPeriods->pluck('id')->map(fn ($id): int => (int) $id)->values()->all();

            $projOrderIds = collect($projPeriodIds)
                ->flatMap(fn (int $pid): Collection => $cposByPeriod->get($pid, collect())->pluck('order_id'))
                ->unique()
                ->map(fn ($id): int => (int) $id)
                ->values()
                ->all();

            $materialRevenue = $this->repository->getMaterialRevenue($projOrderIds);
            $materialCost = $this->repository->getMaterialCost($projOrderIds);
            $materialProfit = $materialRevenue - $materialCost;

            $commissionProfit = $this->repository->getOperatingCommission($projPeriodIds);
            $totalProfit = $materialProfit + $commissionProfit;
            $totalAcrossProjects += $totalProfit;

            /** @var ClosingPeriod $sample */
            $sample = $projPeriods->first();
            $project = $sample->project;

            $rows[] = [
                'project_id' => (int) $projectId,
                'project_name' => $project?->name ?? '—',
                'material_profit' => $materialProfit,
                'commission_profit' => $commissionProfit,
                'total_profit' => $totalProfit,
            ];
        }

        $output = [];
        foreach ($rows as $row) {
            $share = $this->safeShare((float) $row['total_profit'], $totalAcrossProjects);
            $output[] = [
                'project_id' => (int) $row['project_id'],
                'project_name' => (string) $row['project_name'],
                'material_profit' => $this->formatAmount((float) $row['material_profit']),
                'commission_profit' => $this->formatAmount((float) $row['commission_profit']),
                'total_profit' => $this->formatAmount((float) $row['total_profit']),
                'share_percent' => $share,
            ];
        }

        usort($output, fn (array $a, array $b): int => (float) $b['total_profit'] <=> (float) $a['total_profit']);

        return $output;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, array<string, mixed>>
     */
    private function buildMonthlyData(array $filters, int $defaultMonthsBack): Collection
    {
        $months = $this->getMonthsInWindow($filters, $defaultMonthsBack);
        if (empty($months)) {
            return collect();
        }

        $periods = $this->loadPeriodsForMonths($filters, $months);
        $periodsByMonth = $periods->groupBy(fn (ClosingPeriod $p): string => $p->period_end->format('Y-m'));

        return collect($months)
            ->map(function (string $yearMonth) use ($periodsByMonth): array {
                /** @var Collection<int, ClosingPeriod> $monthPeriods */
                $monthPeriods = $periodsByMonth->get($yearMonth, collect());

                /** @var list<int> $monthPeriodIds */
                $monthPeriodIds = $monthPeriods->pluck('id')->map(fn ($id): int => (int) $id)->values()->all();
                $monthOrderIds = $this->repository->getOrderIdsForPeriods($monthPeriodIds);

                $materialRevenue = $this->repository->getMaterialRevenue($monthOrderIds);
                $materialCost = $this->repository->getMaterialCost($monthOrderIds);
                $materialProfit = $materialRevenue - $materialCost;
                $commissionProfit = $this->repository->getOperatingCommission($monthPeriodIds);
                $totalProfit = $materialProfit + $commissionProfit;

                [, $month] = explode('-', $yearMonth);

                return [
                    'month' => 'T'.(int) $month,
                    'year_month' => $yearMonth,
                    'material_profit' => $materialProfit,
                    'commission_profit' => $commissionProfit,
                    'total_profit' => $totalProfit,
                ];
            })
            ->values();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return list<string>
     */
    private function getMonthsInWindow(array $filters, int $defaultMonthsBack): array
    {
        if (! empty($filters['closing_period_id'])) {
            $period = $this->repository->findPeriodById((int) $filters['closing_period_id']);

            return $period ? [$period->period_end->format('Y-m')] : [];
        }

        if (! empty($filters['date_from']) || ! empty($filters['date_to'])) {
            $to = ! empty($filters['date_to']) ? Carbon::parse((string) $filters['date_to']) : Carbon::now();
            $from = ! empty($filters['date_from'])
                ? Carbon::parse((string) $filters['date_from'])
                : $to->copy()->subMonths($defaultMonthsBack - 1);
        } else {
            $to = Carbon::now();
            $from = Carbon::now()->subMonths($defaultMonthsBack - 1);
        }

        $cursor = $from->copy()->startOfMonth();
        $end = $to->copy()->startOfMonth();
        $months = [];
        while ($cursor <= $end) {
            $months[] = $cursor->format('Y-m');
            $cursor->addMonth();
        }

        return $months;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @param  list<string>  $months
     * @return Collection<int, ClosingPeriod>
     */
    private function loadPeriodsForMonths(array $filters, array $months): Collection
    {
        if (empty($months)) {
            return collect();
        }

        if (! empty($filters['closing_period_id'])) {
            $period = $this->repository->findPeriodById((int) $filters['closing_period_id']);

            return $period ? collect([$period]) : collect();
        }

        $first = Carbon::parse($months[0].'-01')->startOfMonth();
        $last = Carbon::parse(end($months).'-01')->endOfMonth();

        return $this->repository->getClosedPeriodsInDateRange(
            $first,
            $last,
            ! empty($filters['project_id']) ? (int) $filters['project_id'] : null,
        );
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $monthly
     * @return Collection<int, array<string, mixed>>
     */
    private function groupByQuarter(Collection $monthly): Collection
    {
        return $monthly
            ->groupBy(function (array $row): string {
                [$year, $month] = explode('-', (string) $row['year_month']);
                $quarter = (int) ceil(((int) $month) / 3);

                return "{$year}-Q{$quarter}";
            })
            ->map(function (Collection $group, string $key): array {
                return [
                    'quarter_key' => $key,
                    'total_profit' => (float) $group->sum(fn (array $r): float => (float) $r['total_profit']),
                ];
            })
            ->values();
    }

    /**
     * @param  array<string, mixed>|null  $lastMonth
     * @param  array<string, mixed>|null  $prevMonth
     * @param  list<array<string, mixed>>  $byProject
     * @return list<string>
     */
    private function buildInsights(
        float $totalProfit,
        float $materialProfit,
        float $commissionProfit,
        float $momTotal,
        ?array $lastMonth,
        ?array $prevMonth,
        array $byProject,
    ): array {
        $insights = [];

        if ($totalProfit > 0) {
            $main = $materialProfit >= $commissionProfit ? 'Vật tư' : 'Hoa hồng';
            $share = $materialProfit >= $commissionProfit
                ? $this->safeShare($materialProfit, $totalProfit)
                : $this->safeShare($commissionProfit, $totalProfit);
            $insights[] = sprintf('Nguồn lợi nhuận chính: %s (%.1f%% tổng LN).', $main, $share);
        } elseif ($totalProfit < 0) {
            $insights[] = 'Công ty vận hành đang lỗ trong kỳ — cần rà soát chi phí vật tư và cấu trúc hoa hồng.';
        }

        if ($lastMonth !== null && $prevMonth !== null) {
            $direction = $momTotal >= 0 ? 'tăng' : 'giảm';
            $insights[] = sprintf(
                'LN tháng %s %s %.1f%% so với tháng %s.',
                (string) $lastMonth['month'],
                $direction,
                abs($momTotal),
                (string) $prevMonth['month'],
            );
        }

        if (! empty($byProject)) {
            $top = $byProject[0];
            if ((float) $top['total_profit'] > 0) {
                $insights[] = sprintf(
                    'Dự án đóng góp LN cao nhất: %s (%.1f%%).',
                    (string) $top['project_name'],
                    (float) $top['share_percent'],
                );
            }
        }

        return array_slice($insights, 0, 3);
    }

    private function safeShare(float $value, float $total): float
    {
        if ($total == 0.0) {
            return 0.0;
        }

        return round($value / $total * 100, 1);
    }

    private function percentDelta(mixed $current, mixed $previous): float
    {
        if ($current === null || $previous === null) {
            return 0.0;
        }

        $prev = (float) $previous;
        if ($prev == 0.0) {
            return 0.0;
        }

        return round(((float) $current - $prev) / abs($prev) * 100, 1);
    }

    private function formatAmount(float $amount): string
    {
        return number_format($amount, 2, '.', '');
    }
}
