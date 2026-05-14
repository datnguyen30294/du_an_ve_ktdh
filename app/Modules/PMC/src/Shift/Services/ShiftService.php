<?php

namespace App\Modules\PMC\Shift\Services;

use App\Common\Exceptions\BusinessException;
use App\Common\Services\BaseService;
use App\Modules\PMC\Shift\Contracts\ShiftServiceInterface;
use App\Modules\PMC\Shift\Models\Shift;
use App\Modules\PMC\Shift\Repositories\ShiftRepository;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\Response;

class ShiftService extends BaseService implements ShiftServiceInterface
{
    public function __construct(protected ShiftRepository $repository) {}

    /**
     * @return Collection<int, Shift>
     */
    public function all(): Collection
    {
        return $this->repository->all();
    }

    /**
     * @return Collection<int, Shift>
     */
    public function allForProject(int $projectId): Collection
    {
        return $this->repository->allForProject($projectId);
    }

    /**
     * @param  list<int>  $projectIds
     * @return Collection<int, Shift>
     */
    public function allForProjects(array $projectIds): Collection
    {
        return $this->repository->allForProjects($projectIds);
    }

    public function list(array $filters): LengthAwarePaginator
    {
        return $this->repository->list($filters);
    }

    public function findById(int $id): Shift
    {
        /** @var Shift */
        return $this->repository->findById($id, ['*'], ['project']);
    }

    public function findByIdForApiProject(int $id, int $apiProjectId): Shift
    {
        $shift = $this->findById($id);
        $this->ensureBelongsToApiProject($shift, $apiProjectId);

        return $shift;
    }

    protected function ensureBelongsToApiProject(Shift $shift, int $apiProjectId): void
    {
        if ((int) $shift->project_id !== $apiProjectId) {
            throw new BusinessException(
                'Ca làm việc không thuộc API key hiện tại.',
                'SHIFT_SCOPE_MISMATCH',
                Response::HTTP_FORBIDDEN,
            );
        }
    }

    public function create(array $data): Shift
    {
        $this->validateTimeConstraints(
            (int) $data['project_id'],
            (string) $data['start_time'],
            (string) $data['end_time'],
        );

        /** @var Shift $shift */
        $shift = $this->repository->create($data);

        return $shift->load('project');
    }

    public function update(int $id, array $data): Shift
    {
        $shift = $this->findById($id);

        if (isset($data['project_id']) && (int) $data['project_id'] !== (int) $shift->project_id) {
            throw new BusinessException(
                'Không được đổi dự án của ca. Hãy xoá ca và tạo ca mới ở dự án khác.',
                'SHIFT_PROJECT_IMMUTABLE',
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        $this->validateTimeConstraints(
            (int) $shift->project_id,
            (string) ($data['start_time'] ?? $shift->start_time),
            (string) ($data['end_time'] ?? $shift->end_time),
            $id,
        );

        $shift->update($data);

        return $shift->refresh()->load('project');
    }

    protected function validateTimeConstraints(
        int $projectId,
        string $startTime,
        string $endTime,
        ?int $excludeId = null,
    ): void {
        $start = $this->normalizeHm($startTime);
        $end = $this->normalizeHm($endTime);

        if ($start === $end) {
            throw new BusinessException(
                'Giờ bắt đầu không được trùng giờ kết thúc.',
                'SHIFT_ZERO_DURATION',
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        $duplicate = $this->repository->findExactTimeMatchInProject(
            $projectId,
            $start,
            $end,
            $excludeId,
        );

        if ($duplicate !== null) {
            throw new BusinessException(
                'Đã có ca trùng khung giờ trong dự án này.',
                'SHIFT_TIME_DUPLICATE',
                Response::HTTP_UNPROCESSABLE_ENTITY,
                ['conflict_shift_id' => (int) $duplicate->id],
            );
        }
    }

    protected function normalizeHm(string $value): string
    {
        if ($value === '') {
            return '00:00';
        }

        $tail = substr($value, -8);
        $time = strlen($tail) === 8 ? $tail : $value;

        return substr($time, 0, 5);
    }

    public function delete(int $id): void
    {
        $shift = $this->findById($id);

        if ($this->repository->hasWorkSchedules($shift->id)) {
            throw new BusinessException(
                'Ca đã có lịch việc, chuyển về "Tạm ẩn" thay vì xóa.',
                'SHIFT_IN_USE',
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        $shift->delete();
    }

    /**
     * @return array{total: int, active: int, inactive: int}
     */
    public function getStats(): array
    {
        return $this->repository->getStatistics();
    }
}
