<?php

namespace App\Modules\PMC\Account\Services;

use App\Common\Exceptions\BusinessException;
use App\Common\Services\BaseService;
use App\Modules\PMC\Account\Contracts\RoleServiceInterface;
use App\Modules\PMC\Account\Enums\RoleType;
use App\Modules\PMC\Account\Models\Role;
use App\Modules\PMC\Account\Repositories\AccountRepository;
use App\Modules\PMC\Account\Repositories\RoleRepository;
use Illuminate\Pagination\LengthAwarePaginator;
use Symfony\Component\HttpFoundation\Response;

class RoleService extends BaseService implements RoleServiceInterface
{
    public function __construct(
        protected RoleRepository $repository,
        protected AccountRepository $accountRepository,
    ) {}

    public function list(array $filters): LengthAwarePaginator
    {
        return $this->repository->list($filters);
    }

    public function findById(int $id): Role
    {
        /** @var Role */
        $role = $this->repository->findById($id);
        $role->load(['permissions', 'department', 'jobTitle']);

        return $role;
    }

    public function create(array $data): Role
    {
        return $this->executeInTransaction(function () use ($data): Role {
            // API-created roles are always custom
            $data['type'] = RoleType::Custom->value;
            unset($data['department_id'], $data['job_title_id']);

            $permissionIds = $data['permission_ids'] ?? [];
            unset($data['permission_ids']);

            /** @var Role */
            $role = $this->repository->create($data);

            if (! empty($permissionIds)) {
                $role->permissions()->sync($permissionIds);
            }

            return $role->load('permissions');
        });
    }

    public function update(int $id, array $data): Role
    {
        return $this->executeInTransaction(function () use ($id, $data): Role {
            $role = $this->findById($id);

            // Strip fields that should never be changed via API
            unset($data['type'], $data['department_id'], $data['job_title_id']);

            // Default roles: block name changes
            if ($role->isDefault() && isset($data['name'])) {
                throw new BusinessException(
                    'Không thể đổi tên vai trò mặc định.',
                    'ROLE_DEFAULT_NAME_CHANGE',
                    Response::HTTP_UNPROCESSABLE_ENTITY,
                );
            }

            $permissionIds = $data['permission_ids'] ?? null;
            unset($data['permission_ids']);

            $role->update($data);

            if ($permissionIds !== null) {
                $role->permissions()->sync($permissionIds);
            }

            return $role->refresh()->load(['permissions', 'department', 'jobTitle']);
        });
    }

    public function delete(int $id): void
    {
        $this->executeInTransaction(function () use ($id): void {
            $role = $this->findById($id);

            if ($role->isDefault()) {
                throw new BusinessException(
                    'Không thể xóa vai trò mặc định.',
                    'ROLE_DEFAULT_DELETE',
                    Response::HTTP_UNPROCESSABLE_ENTITY,
                );
            }

            $this->ensureRoleNotInUse($role, 'xóa');

            $role->delete();
        });
    }

    private function ensureRoleNotInUse(Role $role, string $action): void
    {
        $accountCount = $this->accountRepository->countByRoleId($role->id);

        if ($accountCount > 0) {
            throw new BusinessException(
                "Không thể {$action}: còn {$accountCount} tài khoản đang dùng vai trò này.",
                'ROLE_IN_USE',
                Response::HTTP_UNPROCESSABLE_ENTITY,
                ['account_count' => $accountCount],
            );
        }
    }
}
