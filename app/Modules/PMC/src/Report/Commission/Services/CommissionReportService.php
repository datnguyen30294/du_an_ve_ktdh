<?php

namespace App\Modules\PMC\Report\Commission\Services;

use App\Common\Services\BaseService;
use App\Modules\PMC\ClosingPeriod\Enums\SnapshotRecipientType;
use App\Modules\PMC\Report\Commission\Contracts\CommissionReportServiceInterface;
use App\Modules\PMC\Report\Commission\Repositories\CommissionReportRepository;
use Illuminate\Support\Collection;

class CommissionReportService extends BaseService implements CommissionReportServiceInterface
{
    public function __construct(protected CommissionReportRepository $repository) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function getSummary(array $filters): array
    {
        $periodIds = $this->repository->resolveFilteredPeriodIds($filters);
        $partyTotals = $this->repository->getPartyTotals($periodIds);
        $estimatedGrossProfit = $this->repository->getEstimatedGrossProfit($periodIds);

        return [
            'period_label' => $this->repository->getPeriodLabel($filters),
            'party_totals' => $partyTotals,
            'estimated_gross_profit' => $estimatedGrossProfit,
            'platform_rules' => [
                'percent' => (float) config('commission.platform_default_percent', 5),
                'fixed_per_order' => (float) config('commission.platform_default_fixed', 1000),
            ],
        ];
    }

    /**
     * Build proportional attribution rows grouped by (account_id × project_id).
     *
     * @param  array<string, mixed>  $filters
     * @return list<array<string, mixed>>
     */
    public function getByStaff(array $filters): array
    {
        $periodIds = $this->repository->resolveFilteredPeriodIds($filters);
        $staffRows = $this->repository->getStaffSnapshots($periodIds);

        if ($staffRows->isEmpty()) {
            return [];
        }

        /** @var list<int> $orderIds */
        $orderIds = $staffRows->pluck('order_id')
            ->unique()
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();

        $topLevel = $this->repository->getTopLevelSnapshotsForOrders($orderIds, $periodIds);

        // Index: order_id => recipient_type => amount (sum if duplicated)
        $topLevelByOrder = [];
        foreach ($topLevel as $row) {
            $orderId = (int) $row->order_id;
            $type = (string) $row->recipient_type;
            $topLevelByOrder[$orderId][$type] = ($topLevelByOrder[$orderId][$type] ?? 0.0) + (float) $row->amount;
        }

        // Per-order: total staff amount (denominator for ratio)
        $totalStaffPerOrder = $staffRows
            ->groupBy('order_id')
            ->map(fn (Collection $group): float => (float) $group->sum(fn (object $r): float => (float) $r->amount));

        // Aggregate by (account_id × project_id)
        $grouped = [];
        foreach ($staffRows as $row) {
            $orderId = (int) $row->order_id;
            $staffAmount = (float) $row->amount;
            $totalStaff = (float) ($totalStaffPerOrder[$orderId] ?? 0);
            $ratio = $totalStaff > 0 ? $staffAmount / $totalStaff : 0.0;

            $topAmounts = $topLevelByOrder[$orderId] ?? [];
            $operating = (float) ($topAmounts[SnapshotRecipientType::OperatingCompany->value] ?? 0) * $ratio;
            $bod = (float) ($topAmounts[SnapshotRecipientType::BoardOfDirectors->value] ?? 0) * $ratio;
            $platform = (float) ($topAmounts[SnapshotRecipientType::Platform->value] ?? 0) * $ratio;
            $management = $staffAmount;

            $key = ($row->account_id ?? 'null').'|'.($row->project_id ?? 'null');

            if (! isset($grouped[$key])) {
                $grouped[$key] = [
                    'account_id' => $row->account_id,
                    'staff_name' => (string) $row->staff_name,
                    'department_name' => $row->staff_department,
                    'project_id' => $row->project_id,
                    'project_name' => (string) $row->project_name,
                    'operating_company' => 0.0,
                    'board_of_directors' => 0.0,
                    'management' => 0.0,
                    'platform' => 0.0,
                ];
            }

            $grouped[$key]['operating_company'] += $operating;
            $grouped[$key]['board_of_directors'] += $bod;
            $grouped[$key]['management'] += $management;
            $grouped[$key]['platform'] += $platform;
        }

        $result = array_map(function (array $row): array {
            $total = $row['operating_company'] + $row['board_of_directors'] + $row['management'] + $row['platform'];

            return [
                'account_id' => $row['account_id'],
                'staff_name' => $row['staff_name'],
                'department_name' => $row['department_name'],
                'project_id' => $row['project_id'],
                'project_name' => $row['project_name'],
                'operating_company' => $this->formatAmount($row['operating_company']),
                'board_of_directors' => $this->formatAmount($row['board_of_directors']),
                'management' => $this->formatAmount($row['management']),
                'platform' => $this->formatAmount($row['platform']),
                'total' => $this->formatAmount($total),
            ];
        }, array_values($grouped));

        usort($result, fn (array $a, array $b): int => (float) $b['total'] <=> (float) $a['total']);

        return $result;
    }

    private function formatAmount(float $amount): string
    {
        return number_format($amount, 2, '.', '');
    }
}
