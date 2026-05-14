<?php

namespace App\Modules\PMC\Project\Services;

use App\Common\Exceptions\BusinessException;
use App\Common\Services\BaseService;
use App\Modules\PMC\Account\Repositories\AccountRepository;
use App\Modules\PMC\Commission\Repositories\CommissionConfigRepository;
use App\Modules\PMC\Project\Contracts\ProjectServiceInterface;
use App\Modules\PMC\Project\Models\Project;
use App\Modules\PMC\Project\Repositories\ProjectRepository;
use Illuminate\Pagination\LengthAwarePaginator;
use Symfony\Component\HttpFoundation\Response;

class ProjectService extends BaseService implements ProjectServiceInterface
{
    public function __construct(
        protected ProjectRepository $repository,
        protected CommissionConfigRepository $commissionConfigRepository,
        protected AccountRepository $accountRepository,
    ) {}

    public function list(array $filters): LengthAwarePaginator
    {
        return $this->repository->list($filters);
    }

    public function findById(int $id): Project
    {
        /** @var Project */
        return $this->repository->findById($id, ['*'], ['accounts.departments', 'accounts.jobTitle']);
    }

    public function create(array $data): Project
    {
        return $this->executeInTransaction(function () use ($data): Project {
            /** @var Project */
            return $this->repository->create($data);
        });
    }

    public function update(int $id, array $data): Project
    {
        return $this->executeInTransaction(function () use ($id, $data): Project {
            $project = $this->repository->findById($id);
            $project->update($data);

            return $project->refresh();
        });
    }

    public function delete(int $id): void
    {
        $this->executeInTransaction(function () use ($id): void {
            $project = $this->repository->findById($id);
            $project->delete();
        });
    }

    /**
     * @param  array<int>  $accountIds
     */
    public function syncMembers(int $id, array $accountIds): Project
    {
        return $this->executeInTransaction(function () use ($id, $accountIds): Project {
            $project = $this->repository->findById($id);
            $currentIds = $this->repository->getMemberAccountIds($id);
            $removedIds = array_diff($currentIds, $accountIds);

            if (! empty($removedIds)) {
                $blockedIds = $this->commissionConfigRepository->findAccountsWithCommissionConfig(
                    array_values($removedIds),
                    $id,
                );

                if (! empty($blockedIds)) {
                    $account = $this->accountRepository->findById($blockedIds[0]);
                    throw new BusinessException(
                        "Không thể xóa nhân viên {$account->name} khỏi dự án: đang có cấu hình hoa hồng. Hãy xóa khỏi cấu hình hoa hồng trước.",
                        'PROJECT_MEMBER_HAS_COMMISSION_CONFIG',
                        Response::HTTP_UNPROCESSABLE_ENTITY,
                    );
                }
            }

            $project->accounts()->sync($accountIds);

            return $project->load(['accounts.departments', 'accounts.jobTitle']);
        });
    }
}
