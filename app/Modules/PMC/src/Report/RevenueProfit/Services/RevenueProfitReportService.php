<?php

namespace App\Modules\PMC\Report\RevenueProfit\Services;

use App\Common\Services\BaseService;
use App\Modules\PMC\ClosingPeriod\Models\ClosingPeriod;
use App\Modules\PMC\Quote\Enums\QuoteLineType;
use App\Modules\PMC\Report\RevenueProfit\Contracts\RevenueProfitReportServiceInterface;
use App\Modules\PMC\Report\RevenueProfit\Repositories\RevenueProfitReportRepository;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class RevenueProfitReportService extends BaseService implements RevenueProfitReportServiceInterface
{
    public const MARGIN_ALERT_THRESHOLD = 31.0;

    public const MONTHLY_ENDPOINT_WINDOW = 6;

    public const SUMMARY_INSIGHT_WINDOW = 12;

    public function __construct(protected RevenueProfitReportRepository $repository) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function getSummary(array $filters): array
    {
        $periodIds = $this->repository->resolveFilteredPeriodIds($filters);
        $orderIds = $this->repository->getOrderIdsForPeriods($periodIds);

        $revenue = $this->repository->getRevenue($periodIds);
        $extCommission = $this->repository->getExternalCommission($periodIds);
        $materialCost = $this->repository->getMaterialCost($orderIds);

        $estimatedCost = $extCommission + $materialCost;
        $grossProfit = $revenue - $estimatedCost;
        $marginPercent = $this->safeMargin($grossProfit, $revenue);

        $monthly = $this->buildMonthlyData($filters, self::SUMMARY_INSIGHT_WINDOW);
        $monthsWithData = $monthly->filter(fn (array $row): bool => (float) $row['revenue'] > 0)->values();

        $lastMonth = $monthsWithData->last();
        $prevMonth = $monthsWithData->count() >= 2
            ? $monthsWithData->slice(-2, 1)->first()
            : null;

        $momRevenue = $this->percentDelta($lastMonth['revenue'] ?? null, $prevMonth['revenue'] ?? null);
        $momProfit = $this->percentDelta($lastMonth['gross_profit'] ?? null, $prevMonth['gross_profit'] ?? null);

        $quarters = $this->groupByQuarter($monthly);
        $quartersWithData = $quarters->filter(fn (array $row): bool => (float) $row['revenue'] > 0)->values();
        $lastQuarter = $quartersWithData->last();
        $prevQuarter = $quartersWithData->count() >= 2
            ? $quartersWithData->slice(-2, 1)->first()
            : null;

        $qoqRevenue = $this->percentDelta($lastQuarter['revenue'] ?? null, $prevQuarter['revenue'] ?? null);
        $qoqProfit = $this->percentDelta($lastQuarter['gross_profit'] ?? null, $prevQuarter['gross_profit'] ?? null);

        $last6 = $monthsWithData->slice(-6);
        $avgMargin = $last6->isEmpty()
            ? 0.0
            : round($last6->avg(fn (array $row): float => (float) $row['margin_percent']), 1);

        $byProject = $this->getByProject($filters);
        $alertsCount = collect($byProject)->where('margin_alert', true)->count();

        $insights = $this->buildInsights(
            lastMonth: $lastMonth,
            prevMonth: $prevMonth,
            momRevenue: $momRevenue,
            alertsCount: $alertsCount,
            byProject: $byProject,
        );

        return [
            'period_label' => $this->repository->getPeriodLabel($filters),
            'revenue' => $this->formatAmount($revenue),
            'external_commission' => $this->formatAmount($extCommission),
            'material_cost' => $this->formatAmount($materialCost),
            'estimated_cost' => $this->formatAmount($estimatedCost),
            'gross_profit' => $this->formatAmount($grossProfit),
            'margin_percent' => $marginPercent,
            'margin_alert_threshold' => self::MARGIN_ALERT_THRESHOLD,
            'mom_revenue_percent' => $momRevenue,
            'mom_profit_percent' => $momProfit,
            'qoq_revenue_percent' => $qoqRevenue,
            'qoq_profit_percent' => $qoqProfit,
            'avg_margin_6_months' => $avgMargin,
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
            'revenue' => $this->formatAmount((float) $row['revenue']),
            'external_commission' => $this->formatAmount((float) $row['external_commission']),
            'material_cost' => $this->formatAmount((float) $row['material_cost']),
            'estimated_cost' => $this->formatAmount((float) $row['estimated_cost']),
            'gross_profit' => $this->formatAmount((float) $row['gross_profit']),
            'margin_percent' => (float) $row['margin_percent'],
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

        $totalRevenue = (float) $cpos->sum('frozen_receivable_amount');

        /** @var Collection<int|string, Collection<int, ClosingPeriod>> $byProject */
        $byProject = $periods->groupBy('project_id');

        /** @var Collection<int|string, Collection<int, \App\Modules\PMC\ClosingPeriod\Models\ClosingPeriodOrder>> $cposByPeriod */
        $cposByPeriod = $cpos->groupBy('closing_period_id');

        $rows = [];
        foreach ($byProject as $projectId => $projPeriods) {
            $projPeriodIds = $projPeriods->pluck('id')->map(fn ($id): int => (int) $id)->values()->all();

            $projOrderIds = collect($projPeriodIds)
                ->flatMap(fn (int $pid): Collection => $cposByPeriod->get($pid, collect())->pluck('order_id'))
                ->unique()
                ->map(fn ($id): int => (int) $id)
                ->values()
                ->all();

            $revenue = $this->repository->getRevenue($projPeriodIds);
            $extCommission = $this->repository->getExternalCommission($projPeriodIds);
            $materialCost = $this->repository->getMaterialCost($projOrderIds);
            $estimatedCost = $extCommission + $materialCost;
            $grossProfit = $revenue - $estimatedCost;
            $marginPercent = $this->safeMargin($grossProfit, $revenue);
            $share = $totalRevenue > 0 ? round($revenue / $totalRevenue * 100, 1) : 0.0;

            /** @var ClosingPeriod $sample */
            $sample = $projPeriods->first();
            $project = $sample->project;

            $rows[] = [
                'project_id' => (int) $projectId,
                'project_name' => $project?->name ?? '—',
                'revenue' => $this->formatAmount($revenue),
                'external_commission' => $this->formatAmount($extCommission),
                'material_cost' => $this->formatAmount($materialCost),
                'estimated_cost' => $this->formatAmount($estimatedCost),
                'gross_profit' => $this->formatAmount($grossProfit),
                'margin_percent' => $marginPercent,
                'share_of_revenue_percent' => $share,
                'margin_alert' => $marginPercent < self::MARGIN_ALERT_THRESHOLD,
            ];
        }

        usort($rows, fn (array $a, array $b): int => (float) $b['revenue'] <=> (float) $a['revenue']);

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return list<array<string, mixed>>
     */
    public function getByServiceCategory(array $filters): array
    {
        $periodIds = $this->repository->resolveFilteredPeriodIds($filters);
        if (empty($periodIds)) {
            return [];
        }

        $orderIds = $this->repository->getOrderIdsForPeriods($periodIds);
        $lines = $this->repository->getServiceCategoryLineRows($orderIds);

        if ($lines->isEmpty()) {
            return [];
        }

        $grouped = [];
        foreach ($lines as $line) {
            $key = $this->categoryKey($line);
            $label = $this->categoryLabel($line);

            $lineRevenue = $line->unit_price * $line->quantity;
            $lineMaterial = ($line->line_type === QuoteLineType::Material->value && $line->purchase_price !== null)
                ? $line->purchase_price * $line->quantity
                : 0.0;
            $lineContribution = $lineRevenue - $lineMaterial;

            if (! isset($grouped[$key])) {
                $grouped[$key] = ['key' => $key, 'label' => $label, 'contribution' => 0.0];
            }
            $grouped[$key]['contribution'] += $lineContribution;
        }

        $revenue = $this->repository->getRevenue($periodIds);
        $extCommission = $this->repository->getExternalCommission($periodIds);
        $materialCost = $this->repository->getMaterialCost($orderIds);
        $grossProfit = $revenue - $extCommission - $materialCost;

        $sumContribution = array_sum(array_column($grouped, 'contribution'));
        $adjustment = $grossProfit - $sumContribution;

        $rows = [];
        foreach ($grouped as $entry) {
            $share = $this->shareOfGrossProfit((float) $entry['contribution'], $grossProfit);
            $rows[] = [
                'category_key' => (string) $entry['key'],
                'category_label' => (string) $entry['label'],
                'profit' => $this->formatAmount((float) $entry['contribution']),
                'share_percent' => $share,
            ];
        }

        usort($rows, fn (array $a, array $b): int => (float) $b['profit'] <=> (float) $a['profit']);

        $rows[] = [
            'category_key' => 'internal-adjustment',
            'category_label' => 'Điều chỉnh nội bộ / tập trung',
            'profit' => $this->formatAmount($adjustment),
            'share_percent' => $this->shareOfGrossProfit($adjustment, $grossProfit),
        ];

        return $rows;
    }

    /**
     * Build raw (unformatted) monthly aggregates spanning the requested window.
     * Months without data are filled with zeros.
     *
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

                $revenue = $this->repository->getRevenue($monthPeriodIds);
                $extCommission = $this->repository->getExternalCommission($monthPeriodIds);
                $materialCost = $this->repository->getMaterialCost($monthOrderIds);
                $estimatedCost = $extCommission + $materialCost;
                $grossProfit = $revenue - $estimatedCost;
                $marginPercent = $this->safeMargin($grossProfit, $revenue);

                [, $month] = explode('-', $yearMonth);

                return [
                    'month' => 'T'.(int) $month,
                    'year_month' => $yearMonth,
                    'revenue' => $revenue,
                    'external_commission' => $extCommission,
                    'material_cost' => $materialCost,
                    'estimated_cost' => $estimatedCost,
                    'gross_profit' => $grossProfit,
                    'margin_percent' => $marginPercent,
                ];
            })
            ->values();
    }

    /**
     * Resolve the inclusive list of "Y-m" strings the monthly trend spans.
     *
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
     * Load closed periods (or the previewed open period) whose period_end falls
     * inside any of the listed months and matches the project filter.
     *
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
            return $this->repository->findPeriodsByIds([(int) $filters['closing_period_id']]);
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
     * Group monthly rows into quarter buckets (Y-Qn).
     *
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
                $revenue = (float) $group->sum(fn (array $r): float => (float) $r['revenue']);
                $gross = (float) $group->sum(fn (array $r): float => (float) $r['gross_profit']);

                return [
                    'quarter_key' => $key,
                    'revenue' => $revenue,
                    'gross_profit' => $gross,
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
        ?array $lastMonth,
        ?array $prevMonth,
        float $momRevenue,
        int $alertsCount,
        array $byProject,
    ): array {
        $insights = [];

        if ($lastMonth !== null && $prevMonth !== null) {
            $direction = $momRevenue >= 0 ? 'tăng' : 'giảm';
            $insights[] = sprintf(
                'Doanh thu tháng %s %s %.1f%% so với tháng %s.',
                (string) $lastMonth['month'],
                $direction,
                abs($momRevenue),
                (string) $prevMonth['month'],
            );
        }

        if ($alertsCount > 0) {
            $insights[] = sprintf(
                '%d dự án đang dưới ngưỡng margin %s%%.',
                $alertsCount,
                rtrim(rtrim(number_format(self::MARGIN_ALERT_THRESHOLD, 1, '.', ''), '0'), '.'),
            );
        }

        if (! empty($byProject)) {
            $top = $byProject[0];
            $insights[] = sprintf(
                'Dự án đóng góp doanh thu cao nhất: %s (%.1f%%).',
                (string) $top['project_name'],
                (float) $top['share_of_revenue_percent'],
            );
        }

        return array_slice($insights, 0, 3);
    }

    private function categoryKey(object $line): string
    {
        if ($line->line_type === QuoteLineType::Service->value && $line->service_category_name !== null) {
            return Str::slug((string) $line->service_category_name);
        }

        return Str::slug($this->categoryLabel($line));
    }

    private function categoryLabel(object $line): string
    {
        return match ($line->line_type) {
            QuoteLineType::Material->value => 'Vật tư',
            QuoteLineType::Adhoc->value => 'Dịch vụ tùy chọn',
            QuoteLineType::Service->value => $line->service_category_name ?? 'Dịch vụ chưa phân loại',
            default => 'Khác',
        };
    }

    private function safeMargin(float $grossProfit, float $revenue): float
    {
        if ($revenue <= 0) {
            return 0.0;
        }

        return round($grossProfit / $revenue * 100, 1);
    }

    private function shareOfGrossProfit(float $value, float $grossProfit): float
    {
        if ($grossProfit == 0.0) {
            return 0.0;
        }

        return round($value / $grossProfit * 100, 1);
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

        return round(((float) $current - $prev) / $prev * 100, 1);
    }

    private function formatAmount(float $amount): string
    {
        return number_format($amount, 2, '.', '');
    }
}
