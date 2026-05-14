<?php

namespace App\Modules\PMC\Report\Overview\Services;

use App\Common\Services\BaseService;
use App\Modules\PMC\Report\Commission\Contracts\CommissionReportServiceInterface;
use App\Modules\PMC\Report\Csat\Contracts\CsatReportServiceInterface;
use App\Modules\PMC\Report\Overview\Contracts\OverviewReportServiceInterface;
use App\Modules\PMC\Report\RevenueProfit\Contracts\RevenueProfitReportServiceInterface;
use App\Modules\PMC\Report\Sla\Contracts\SlaReportServiceInterface;
use Illuminate\Support\Facades\Log;
use Throwable;

class OverviewReportService extends BaseService implements OverviewReportServiceInterface
{
    public function __construct(
        protected SlaReportServiceInterface $slaReportService,
        protected RevenueProfitReportServiceInterface $revenueProfitReportService,
        protected CsatReportServiceInterface $csatReportService,
        protected CommissionReportServiceInterface $commissionReportService,
    ) {}

    /**
     * Aggregate KPI from 4 sibling report services. Soft-fail per block.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function getSummary(array $filters): array
    {
        $sla = $this->safeCall('sla', fn (): array => $this->slaReportService->getSummary($filters));
        $revenue = $this->safeCall('revenue_profit', fn (): array => $this->revenueProfitReportService->getSummary($filters));
        $csat = $this->safeCall('csat', fn (): array => $this->csatReportService->getSummary($filters));
        $commission = $this->safeCall('commission', fn (): array => $this->commissionReportService->getSummary($filters));

        return [
            'period_label' => $this->resolvePeriodLabel($revenue, $sla, $csat, $commission),
            'sla' => $sla === null ? null : [
                'on_time_rate' => (float) ($sla['on_time_rate'] ?? 0),
                'breached_count' => (int) ($sla['breached_count'] ?? 0),
            ],
            'revenue' => $revenue === null ? null : [
                'revenue' => (string) ($revenue['revenue'] ?? '0.00'),
                'margin_percent' => (float) ($revenue['margin_percent'] ?? 0),
            ],
            'csat' => $csat === null ? null : [
                'avg_score' => (float) ($csat['avg_score'] ?? 0),
                'max_score' => (int) ($csat['max_score'] ?? 5),
                'response_rate' => (float) ($csat['response_rate'] ?? 0),
            ],
            'commission' => $commission === null ? null : $this->buildCommissionBlock($commission),
        ];
    }

    /**
     * @param  callable(): array<string, mixed>  $callback
     * @return array<string, mixed>|null
     */
    private function safeCall(string $service, callable $callback): ?array
    {
        try {
            return $callback();
        } catch (Throwable $e) {
            Log::warning('OverviewReport sub-service failed', [
                'service' => $service,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * @param  array<string, mixed>|null  $revenue
     * @param  array<string, mixed>|null  $sla
     * @param  array<string, mixed>|null  $csat
     * @param  array<string, mixed>|null  $commission
     */
    private function resolvePeriodLabel(?array $revenue, ?array $sla, ?array $csat, ?array $commission): string
    {
        foreach ([$revenue, $sla, $csat, $commission] as $block) {
            if (is_array($block) && isset($block['period_label'])) {
                return (string) $block['period_label'];
            }
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $commission
     * @return array<string, mixed>
     */
    private function buildCommissionBlock(array $commission): array
    {
        /** @var array<string, mixed> $partyTotalsRaw */
        $partyTotalsRaw = $commission['party_totals'] ?? [];

        $partyTotals = [
            'operating_company' => (string) ($partyTotalsRaw['operating_company'] ?? '0.00'),
            'board_of_directors' => (string) ($partyTotalsRaw['board_of_directors'] ?? '0.00'),
            'management' => (string) ($partyTotalsRaw['management'] ?? '0.00'),
            'platform' => (string) ($partyTotalsRaw['platform'] ?? '0.00'),
        ];

        $totalAll = array_sum(array_map(static fn (string $v): float => (float) $v, $partyTotals));

        return [
            'party_totals' => $partyTotals,
            'total_all_parties' => number_format($totalAll, 2, '.', ''),
        ];
    }
}
