<?php

namespace App\Modules\PMC\Report\Csat\Services;

use App\Common\Services\BaseService;
use App\Modules\PMC\Report\Csat\Contracts\CsatReportServiceInterface;
use App\Modules\PMC\Report\Csat\Repositories\CsatReportRepository;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class CsatReportService extends BaseService implements CsatReportServiceInterface
{
    public function __construct(protected CsatReportRepository $repository) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function getSummary(array $filters): array
    {
        $tickets = $this->repository->getCompletedTickets($filters);
        $completedCount = $tickets->count();
        $ratings = $this->extractRatings($tickets);
        $ratedCount = $ratings->count();
        $warrantyCount = $this->countWarranty($tickets);

        return [
            'period_label' => $this->repository->getPeriodLabel($filters),
            'avg_score' => $this->computeAvgScore($ratings),
            'max_score' => CsatReportRepository::MAX_SCORE,
            'completed_count' => $completedCount,
            'rated_count' => $ratedCount,
            'response_rate' => $this->computeResponseRate($ratedCount, $completedCount),
            'nps_style' => $this->computeNpsStyle($ratings),
            'warranty_count' => $warrantyCount,
            'warranty_rate' => $this->computeRate($warrantyCount, $completedCount),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return list<array<string, mixed>>
     */
    public function getTrend(array $filters): array
    {
        $months = (int) ($filters['months'] ?? 6);

        $trendFilters = $filters;
        if (empty($filters['date_from']) && empty($filters['date_to'])) {
            $trendFilters['date_from'] = Carbon::now()->subMonths($months - 1)->startOfMonth()->format('Y-m-d');
            $trendFilters['date_to'] = Carbon::now()->endOfMonth()->format('Y-m-d');
        }

        $tickets = $this->repository->getCompletedTickets($trendFilters);

        $grouped = $tickets->groupBy(function (object $ticket): string {
            return Carbon::parse($ticket->completed_at)->format('Y-m');
        });

        $startMonth = empty($filters['date_from']) && empty($filters['date_to'])
            ? Carbon::now()->subMonths($months - 1)->startOfMonth()
            : Carbon::parse($trendFilters['date_from'])->startOfMonth();
        $endMonth = empty($filters['date_from']) && empty($filters['date_to'])
            ? Carbon::now()->startOfMonth()
            : Carbon::parse($trendFilters['date_to'])->startOfMonth();

        $result = [];
        $current = $startMonth->copy();

        while ($current->lte($endMonth)) {
            $key = $current->format('Y-m');
            /** @var Collection<int, object> $monthTickets */
            $monthTickets = $grouped->get($key, collect());
            $monthCompleted = $monthTickets->count();
            $monthRatings = $this->extractRatings($monthTickets);
            $monthResponses = $monthRatings->count();

            $result[] = [
                'month' => 'T'.$current->month,
                'avg_score' => $this->computeAvgScore($monthRatings),
                'responses' => $monthResponses,
                'response_rate' => $this->computeResponseRate($monthResponses, $monthCompleted),
            ];

            $current->addMonth();
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return list<array<string, mixed>>
     */
    public function getByProject(array $filters): array
    {
        $tickets = $this->repository->getCompletedTickets($filters);

        return $tickets->groupBy('project_id')->map(function (Collection $projectTickets, int|string|null $projectId): array {
            $completedCount = $projectTickets->count();
            $ratings = $this->extractRatings($projectTickets);
            $responses = $ratings->count();
            $warrantyCount = $this->countWarranty($projectTickets);

            return [
                'project_id' => $projectId !== null ? (int) $projectId : null,
                'project_name' => $projectTickets->first()->project_name ?? null,
                'completed_count' => $completedCount,
                'responses' => $responses,
                'response_rate' => $this->computeResponseRate($responses, $completedCount),
                'avg_score' => $this->computeAvgScore($ratings),
                'warranty_count' => $warrantyCount,
                'warranty_rate' => $this->computeRate($warrantyCount, $completedCount),
            ];
        })
            ->values()
            ->sort(function (array $a, array $b): int {
                if ($a['responses'] !== $b['responses']) {
                    return $b['responses'] <=> $a['responses'];
                }

                $avgA = $a['avg_score'] ?? -1.0;
                $avgB = $b['avg_score'] ?? -1.0;
                if ($avgA !== $avgB) {
                    return $avgB <=> $avgA;
                }

                return strcmp((string) ($a['project_name'] ?? ''), (string) ($b['project_name'] ?? ''));
            })
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, object>  $tickets
     * @return Collection<int, int>
     */
    private function extractRatings(Collection $tickets): Collection
    {
        return $tickets
            ->pluck('resident_rating')
            ->filter(static fn (mixed $rating): bool => $rating !== null && $rating !== '')
            ->map(static fn (mixed $rating): int => (int) $rating)
            ->values();
    }

    /**
     * @param  Collection<int, int>  $ratings
     */
    private function computeAvgScore(Collection $ratings): ?float
    {
        if ($ratings->isEmpty()) {
            return null;
        }

        return round($ratings->avg(), 2);
    }

    private function computeResponseRate(int $responses, int $completed): float
    {
        return $this->computeRate($responses, $completed);
    }

    private function computeRate(int $numerator, int $denominator): float
    {
        if ($denominator === 0) {
            return 0.0;
        }

        return round($numerator / $denominator * 100, 1);
    }

    /**
     * @param  Collection<int, object>  $tickets
     */
    private function countWarranty(Collection $tickets): int
    {
        return $tickets
            ->filter(static fn (object $ticket): bool => (int) ($ticket->has_warranty_request ?? 0) === 1)
            ->count();
    }

    /**
     * @param  Collection<int, int>  $ratings
     */
    private function computeNpsStyle(Collection $ratings): ?float
    {
        $total = $ratings->count();
        if ($total === 0) {
            return null;
        }

        $promoters = $ratings->filter(static fn (int $r): bool => $r >= 5)->count();
        $detractors = $ratings->filter(static fn (int $r): bool => $r <= 3)->count();

        return round(($promoters - $detractors) / $total * 100, 1);
    }
}
