<?php

namespace App\Modules\PMC\Account\Services;

use App\Common\Contracts\StorageServiceInterface;
use App\Common\Exceptions\BusinessException;
use App\Common\Services\BaseService;
use App\Modules\PMC\Account\Contracts\AccountServiceInterface;
use App\Modules\PMC\Account\Models\Account;
use App\Modules\PMC\Account\Repositories\AccountRepository;
use App\Modules\PMC\Commission\Repositories\CommissionConfigRepository;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Symfony\Component\HttpFoundation\Response;

class AccountService extends BaseService implements AccountServiceInterface
{
    public const AVATAR_DIRECTORY = 'avatars';

    public function __construct(
        protected AccountRepository $repository,
        protected StorageServiceInterface $storageService,
        protected CommissionConfigRepository $commissionConfigRepository,
    ) {}

    public function list(array $filters): LengthAwarePaginator
    {
        return $this->repository->list($filters);
    }

    public function findById(int $id): Account
    {
        /** @var Account */
        return $this->repository->findById($id);
    }

    public function create(array $data): Account
    {
        return $this->executeInTransaction(function () use ($data): Account {
            $projectIds = $data['project_ids'] ?? [];
            $departmentIds = $data['department_ids'] ?? [];
            unset($data['project_ids'], $data['department_ids']);

            /** @var Account $account */
            $account = $this->repository->create($data);

            $account->departments()->sync($departmentIds);

            if (! empty($projectIds)) {
                $account->projects()->sync($projectIds);
            }

            return $account->load(['departments', 'jobTitle', 'role', 'projects']);
        });
    }

    public function update(int $id, array $data): Account
    {
        return $this->executeInTransaction(function () use ($id, $data): Account {
            $projectIds = $data['project_ids'] ?? null;
            $departmentIds = $data['department_ids'] ?? null;
            unset($data['project_ids'], $data['department_ids']);

            $account = $this->findById($id);
            $account->update($data);

            if ($departmentIds !== null) {
                $account->departments()->sync($departmentIds);
            }

            if ($projectIds !== null) {
                $account->projects()->sync($projectIds);
            }

            return $account->refresh()->load(['departments', 'jobTitle', 'role', 'projects']);
        });
    }

    public function delete(int $id): void
    {
        $account = $this->findById($id);

        if ($this->commissionConfigRepository->hasStaffRule($account->id)
            || $this->commissionConfigRepository->hasAdjuster($account->id)) {
            throw new BusinessException(
                "Không thể xóa tài khoản {$account->name}: đang có cấu hình hoa hồng. Hãy xóa khỏi cấu hình hoa hồng trước.",
                'ACCOUNT_HAS_COMMISSION_CONFIG',
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        $account->delete();
    }

    public function changePassword(int $id, array $data): Account
    {
        $account = $this->findById($id);
        $account->update(['password' => $data['password']]);

        return $account->refresh();
    }

    public function uploadAvatar(int $id, UploadedFile $file): Account
    {
        return $this->executeInTransaction(function () use ($id, $file): Account {
            $account = $this->findById($id);

            if ($account->avatar_path) {
                $this->storageService->delete($account->avatar_path);
            }

            $path = $this->storageService->upload($file, self::AVATAR_DIRECTORY);
            $account->update(['avatar_path' => $path]);

            return $account->refresh()->load(['departments', 'jobTitle', 'role', 'projects']);
        });
    }

    public function deleteAvatar(int $id): Account
    {
        return $this->executeInTransaction(function () use ($id): Account {
            $account = $this->findById($id);

            if ($account->avatar_path) {
                $this->storageService->delete($account->avatar_path);
                $account->update(['avatar_path' => null]);
            }

            return $account->refresh()->load(['departments', 'jobTitle', 'role', 'projects']);
        });
    }
}
