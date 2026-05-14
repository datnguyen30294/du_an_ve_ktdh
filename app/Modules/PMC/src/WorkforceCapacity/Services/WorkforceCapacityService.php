<?php

namespace App\Modules\PMC\WorkforceCapacity\Services;

use App\Common\Exceptions\BusinessException;
use App\Common\Services\BaseService;
use App\Modules\PMC\Account\Models\Account;
use App\Modules\PMC\Account\Repositories\AccountRepository;
use App\Modules\PMC\OgTicket\Enums\OgTicketStatus;
use App\Modules\PMC\OgTicket\Repositories\OgTicketRepository;
use App\Modules\PMC\Project\Models\Project;
use App\Modules\PMC\WorkforceCapacity\Contracts\WorkforceCapacityServiceInterface;
use Symfony\Component\HttpFoundation\Response;

class WorkforceCapacityService extends BaseService implements WorkforceCapacityServiceInterface
{
    private const STAFF_HARD_LIMIT = 500;

    /** @var list<string> Statuses counted as "pending" bucket. */
    private const PENDING_STATUSES = [
        'received',
        'assigned',
    ];

    /** @var list<string> Statuses counted as "in_progress" bucket. */
    private const IN_PROGRESS_STATUSES = [
        'surveying',
        'quoted',
        'approved',
        'ordered',
        'in_progress',
    ];

    public function __construct(
        protected AccountRepository $accountRepository,
        protected OgTicketRepository $ogTicketRepository,
    ) {}

    public function getCapacity(?int $projectId, ?string $search): array
    {
        $accounts = $this->accountRepository->listActiveForWorkforceCapacity($projectId, $search);

        if ($accounts->count() > self::STAFF_HARD_LIMIT) {
            throw new BusinessException(
                message: 'Quá nhiều nhân sự. Vui lòng lọc theo dự án.',
                errorCode: 'WORKFORCE_CAPACITY_TOO_MANY_STAFF',
                httpStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY,
                context: ['count' => $accounts->count()],
            );
        }

        /** @var list<int> $accountIds */
        $accountIds = $accounts->pluck('id')->map(fn ($id): int => (int) $id)->all();

        $assignments = $this->ogTicketRepository->aggregateAssignmentsForAccounts($accountIds);

        /** @var array<int, array{pending: int, in_progress: int, completed: int, rating_sum: int, rating_count: int}> $perAccount */
        $perAccount = [];

        foreach ($accountIds as $id) {
            $perAccount[$id] = [
                'pending' => 0,
                'in_progress' => 0,
                'completed' => 0,
                'rating_sum' => 0,
                'rating_count' => 0,
            ];
        }

        foreach ($assignments as $row) {
            $accountId = (int) $row->account_id;
            $status = (string) $row->status;

            if (! isset($perAccount[$accountId])) {
                continue;
            }

            if (in_array($status, self::PENDING_STATUSES, true)) {
                $perAccount[$accountId]['pending']++;
            } elseif (in_array($status, self::IN_PROGRESS_STATUSES, true)) {
                $perAccount[$accountId]['in_progress']++;
            } elseif ($status === OgTicketStatus::Completed->value) {
                $perAccount[$accountId]['completed']++;
            }

            if ($row->resident_rating !== null) {
                $rating = (int) $row->resident_rating;
                $perAccount[$accountId]['rating_sum'] += $rating;
                $perAccount[$accountId]['rating_count']++;
            }
        }

        $rows = [];
        $totalPending = 0;
        $totalInProgress = 0;
        $totalCompleted = 0;
        $weightedRatingSum = 0.0;
        $totalRatingEvents = 0;
        $staffWithRatings = 0;

        foreach ($accounts as $account) {
            /** @var Account $account */
            $id = (int) $account->id;
            $stats = $perAccount[$id];

            $avgRating = $stats['rating_count'] > 0
                ? round($stats['rating_sum'] / $stats['rating_count'], 1)
                : null;

            $projectNames = $account->relationLoaded('projects')
                ? $account->projects->map(fn (Project $p): string => (string) $p->name)->values()->all()
                : [];

            $rows[] = [
                'account_id' => $id,
                'full_name' => (string) $account->name,
                'employee_code' => $account->employee_code !== null ? (string) $account->employee_code : null,
                'job_title_name' => $account->jobTitle?->name !== null ? (string) $account->jobTitle->name : null,
                'project_names' => $projectNames,
                'pending' => $stats['pending'],
                'in_progress' => $stats['in_progress'],
                'completed' => $stats['completed'],
                'avg_rating' => $avgRating,
                'rating_count' => $stats['rating_count'],
                'capability_rating' => $account->capability_rating !== null ? (int) $account->capability_rating : null,
            ];

            $totalPending += $stats['pending'];
            $totalInProgress += $stats['in_progress'];
            $totalCompleted += $stats['completed'];

            if ($stats['rating_count'] > 0) {
                $weightedRatingSum += $stats['rating_sum'];
                $totalRatingEvents += $stats['rating_count'];
                $staffWithRatings++;
            }
        }

        $staffCount = count($rows);
        $pooledAvgRating = $totalRatingEvents > 0
            ? round($weightedRatingSum / $totalRatingEvents, 1)
            : null;

        return [
            'summary' => [
                'staff_count' => $staffCount,
                'total_pending' => $totalPending,
                'total_in_progress' => $totalInProgress,
                'total_completed' => $totalCompleted,
                'pooled_avg_rating' => $pooledAvgRating,
                'total_rating_events' => $totalRatingEvents,
                'staff_with_ratings' => $staffWithRatings,
            ],
            'rows' => $rows,
        ];
    }
}
