<?php

namespace App\Modules\PMC\Report\RevenueTicket\Services;

use App\Common\Services\BaseService;
use App\Modules\PMC\Report\RevenueTicket\Contracts\RevenueTicketReportServiceInterface;
use App\Modules\PMC\Report\RevenueTicket\Repositories\RevenueTicketReportRepository;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class RevenueTicketReportService extends BaseService implements RevenueTicketReportServiceInterface
{
    public function __construct(protected RevenueTicketReportRepository $repository) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function getSummary(array $filters): array
    {
        $rows = $this->repository->getDataset($filters);

        $totalRevenue = $this->sumDistinctReceivableRevenue($rows);
        $ticketCount = $rows->pluck('ticket_id')->unique()->count();
        $categoryCount = $rows->pluck('category_label')->unique()->count();
        $recordCount = $this->computeRecordCount($rows);

        return [
            'period_label' => $this->repository->getPeriodLabel($filters),
            'total_revenue' => $this->formatAmount($totalRevenue),
            'ticket_count' => $ticketCount,
            'record_count' => $recordCount,
            'category_count' => $categoryCount,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return list<array<string, mixed>>
     */
    public function getByCategory(array $filters): array
    {
        $rows = $this->repository->getDataset($filters);

        if ($rows->isEmpty()) {
            return [];
        }

        $totalTicketCount = $rows->pluck('ticket_id')->unique()->count();

        /** @var Collection<string, Collection<int, object>> $grouped */
        $grouped = $rows->groupBy(fn (object $row): string => $this->categoryGroupKey($row));

        return $grouped
            ->map(function (Collection $group) use ($totalTicketCount): array {
                /** @var object $sample */
                $sample = $group->first();
                $label = (string) $sample->category_label;

                return [
                    'category_key' => $this->categoryKey($sample->category_id, $label),
                    'category_label' => $label,
                    'revenue' => $this->formatAmount($this->sumDistinctReceivableRevenue($group)),
                    'ticket_count' => $group->pluck('ticket_id')->unique()->count(),
                    'ticket_share_percent' => $this->sharePercent(
                        $group->pluck('ticket_id')->unique()->count(),
                        $totalTicketCount,
                    ),
                ];
            })
            ->values()
            ->sortByDesc(fn (array $row): float => (float) $row['revenue'])
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return list<array<string, mixed>>
     */
    public function getByStaff(array $filters): array
    {
        $rows = $this->repository->getDataset($filters);

        if ($rows->isEmpty()) {
            return [];
        }

        $ticketRows = $this->uniqueTicketReceivableRows($rows);
        $totalTicketCount = $ticketRows->pluck('ticket_id')->unique()->count();

        /** @var Collection<string, Collection<int, object>> $grouped */
        $grouped = $ticketRows->groupBy(fn (object $row): string => $this->staffGroupKey($row));

        return $grouped
            ->map(function (Collection $group) use ($totalTicketCount): array {
                /** @var object $sample */
                $sample = $group->first();

                return [
                    'staff_id' => $sample->owner_account_id !== null ? (int) $sample->owner_account_id : null,
                    'staff_name' => (string) $sample->owner_name,
                    'revenue' => $this->formatAmount((float) $group->sum(fn (object $row): float => (float) $row->revenue)),
                    'ticket_count' => $group->pluck('ticket_id')->unique()->count(),
                    'ticket_share_percent' => $this->sharePercent(
                        $group->pluck('ticket_id')->unique()->count(),
                        $totalTicketCount,
                    ),
                ];
            })
            ->values()
            ->sortByDesc(fn (array $row): float => (float) $row['revenue'])
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return list<array<string, mixed>>
     */
    public function getDaily(array $filters): array
    {
        $rows = $this->repository->getDataset($filters);

        if ($rows->isEmpty()) {
            return [];
        }

        $ticketRows = $this->uniqueTicketReceivableRows($rows);

        /** @var Collection<string, Collection<int, object>> $grouped */
        $grouped = $ticketRows->groupBy(fn (object $row): string => $row->completed_date.'|'.($row->project_id ?? 'null'));

        return $grouped
            ->map(function (Collection $group): array {
                /** @var object $sample */
                $sample = $group->first();

                return [
                    'date' => (string) $sample->completed_date,
                    'project_id' => $sample->project_id !== null ? (int) $sample->project_id : null,
                    'project_name' => (string) $sample->project_name,
                    'ticket_count' => $group->pluck('ticket_id')->unique()->count(),
                    'revenue' => $this->formatAmount((float) $group->sum(fn (object $row): float => (float) $row->revenue)),
                ];
            })
            ->values()
            ->sortBy([
                ['date', 'asc'],
                ['project_name', 'asc'],
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return list<array<string, mixed>>
     */
    public function getDetails(array $filters): array
    {
        $rows = $this->repository->getDataset($filters);

        if ($rows->isEmpty()) {
            return [];
        }

        /** @var Collection<string, Collection<int, object>> $grouped */
        $grouped = $rows->groupBy(fn (object $row): string => implode('|', [
            $row->completed_date,
            $row->project_id ?? 'null',
            $row->category_id ?? 'null',
            $row->owner_account_id ?? 'null',
        ]));

        return $grouped
            ->map(function (Collection $group): array {
                /** @var object $sample */
                $sample = $group->first();

                return [
                    'date' => (string) $sample->completed_date,
                    'project_id' => $sample->project_id !== null ? (int) $sample->project_id : null,
                    'project_name' => (string) $sample->project_name,
                    'category_label' => (string) $sample->category_label,
                    'staff_id' => $sample->owner_account_id !== null ? (int) $sample->owner_account_id : null,
                    'staff_name' => (string) $sample->owner_name,
                    'ticket_count' => $group->pluck('ticket_id')->unique()->count(),
                    'revenue' => $this->formatAmount($this->sumDistinctReceivableRevenue($group)),
                ];
            })
            ->values()
            ->sortBy([
                ['date', 'desc'],
                ['revenue', 'desc'],
            ])
            ->values()
            ->all();
    }

    private function categoryGroupKey(object $row): string
    {
        return ($row->category_id ?? 'null').'|'.$row->category_label;
    }

    private function staffGroupKey(object $row): string
    {
        return ($row->owner_account_id ?? 'null').'|'.$row->owner_name;
    }

    private function categoryKey(?int $categoryId, string $label): string
    {
        if ($categoryId !== null) {
            return (string) $categoryId;
        }

        $slug = Str::slug($label);

        return $slug !== '' ? $slug : 'uncategorized';
    }

    /**
     * @param  Collection<int, object>  $rows
     */
    private function sumDistinctReceivableRevenue(Collection $rows): float
    {
        return (float) $rows
            ->unique(fn (object $row): int => (int) $row->receivable_id)
            ->sum(fn (object $row): float => (float) $row->revenue);
    }

    /**
     * Dedupe rows by receivable_id so each receivable appears exactly once.
     * Useful for aggregations that should NOT double-count across categories.
     *
     * @param  Collection<int, object>  $rows
     * @return Collection<int, object>
     */
    private function uniqueTicketReceivableRows(Collection $rows): Collection
    {
        /** @var Collection<int, object> */
        return $rows->unique(fn (object $row): int => (int) $row->receivable_id)->values();
    }

    /**
     * @param  Collection<int, object>  $rows
     */
    private function computeRecordCount(Collection $rows): int
    {
        return $rows
            ->map(fn (object $row): string => implode('|', [
                $row->completed_date,
                $row->project_id ?? 'null',
                $row->category_id ?? 'null',
                $row->owner_account_id ?? 'null',
            ]))
            ->unique()
            ->count();
    }

    private function formatAmount(float $amount): string
    {
        return number_format($amount, 2, '.', '');
    }

    private function sharePercent(int $part, int $total): float
    {
        if ($total === 0) {
            return 0.0;
        }

        return round($part / $total * 100, 1);
    }
}
