<?php

namespace App\Modules\PMC\JobTitle\Services;

use App\Common\Exceptions\BusinessException;
use App\Common\Services\BaseService;
use App\Modules\PMC\Account\Contracts\DefaultRoleServiceInterface;
use App\Modules\PMC\Account\Repositories\AccountRepository;
use App\Modules\PMC\JobTitle\Contracts\JobTitleServiceInterface;
use App\Modules\PMC\JobTitle\Models\JobTitle;
use App\Modules\PMC\JobTitle\Repositories\JobTitleRepository;
use Illuminate\Pagination\LengthAwarePaginator;
use Symfony\Component\HttpFoundation\Response;

class JobTitleService extends BaseService implements JobTitleServiceInterface
{
    public function __construct(
        protected JobTitleRepository $repository,
        protected DefaultRoleServiceInterface $defaultRoleService,
        protected AccountRepository $accountRepository,
    ) {}

    public function list(array $filters): LengthAwarePaginator
    {
        return $this->repository->list($filters);
    }

    public function findById(int $id): JobTitle
    {
        /** @var JobTitle */
        return $this->repository->findById($id, ['*'], ['project']);
    }

    public function create(array $data): JobTitle
    {
        return $this->executeInTransaction(function () use ($data): JobTitle {
            /** @var JobTitle */
            $jobTitle = $this->repository->create($data);

            $this->defaultRoleService->createRolesForJobTitle($jobTitle->id);

            return $jobTitle;
        });
    }

    public function update(int $id, array $data): JobTitle
    {
        return $this->executeInTransaction(function () use ($id, $data): JobTitle {
            $jobTitle = $this->findById($id);

            $oldName = $jobTitle->name;
            $jobTitle->update($data);

            if (isset($data['name']) && $data['name'] !== $oldName) {
                $this->defaultRoleService->syncJobTitleName($jobTitle->id, $data['name']);
            }

            return $jobTitle->refresh();
        });
    }

    public function checkDelete(int $id): array
    {
        $jobTitle = $this->findById($id);
        $accountCount = $this->accountRepository->countByJobTitleId($jobTitle->id);

        if ($accountCount > 0) {
            return [
                'can_delete' => false,
                'message' => "Không thể xóa: còn {$accountCount} tài khoản đang dùng chức danh này. Hãy đổi chức danh cho các tài khoản trước.",
                'account_count' => $accountCount,
            ];
        }

        return [
            'can_delete' => true,
            'message' => '',
            'account_count' => 0,
        ];
    }

    public function delete(int $id): void
    {
        $this->executeInTransaction(function () use ($id): void {
            $jobTitle = $this->findById($id);

            $accountCount = $this->accountRepository->countByJobTitleId($jobTitle->id);

            if ($accountCount > 0) {
                throw new BusinessException(
                    "Không thể xóa: còn {$accountCount} tài khoản đang dùng chức danh này. Hãy đổi chức danh cho các tài khoản trước.",
                    'JOB_TITLE_IN_USE',
                    Response::HTTP_UNPROCESSABLE_ENTITY,
                    ['account_count' => $accountCount],
                );
            }

            $this->defaultRoleService->softDeleteByJobTitle($jobTitle->id);

            $jobTitle->delete();
        });
    }
}
