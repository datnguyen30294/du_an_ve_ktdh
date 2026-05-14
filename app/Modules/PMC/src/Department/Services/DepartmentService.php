<?php

namespace App\Modules\PMC\Department\Services;

use App\Common\Exceptions\BusinessException;
use App\Common\Services\BaseService;
use App\Modules\PMC\Account\Contracts\DefaultRoleServiceInterface;
use App\Modules\PMC\Commission\Repositories\CommissionConfigRepository;
use App\Modules\PMC\Department\Contracts\DepartmentServiceInterface;
use App\Modules\PMC\Department\Models\Department;
use App\Modules\PMC\Department\Repositories\DepartmentRepository;
use Illuminate\Pagination\LengthAwarePaginator;
use Symfony\Component\HttpFoundation\Response;

class DepartmentService extends BaseService implements DepartmentServiceInterface
{
    public function __construct(
        protected DepartmentRepository $repository,
        protected DefaultRoleServiceInterface $defaultRoleService,
        protected CommissionConfigRepository $commissionConfigRepository,
    ) {}

    public function list(array $filters): LengthAwarePaginator
    {
        return $this->repository->list($filters);
    }

    public function findById(int $id): Department
    {
        /** @var Department */
        return $this->repository->findById($id, ['*'], ['parent', 'project']);
    }

    public function create(array $data): Department
    {
        return $this->executeInTransaction(function () use ($data): Department {
            /** @var Department */
            $department = $this->repository->create($data);

            $this->defaultRoleService->createRolesForDepartment($department->id);

            return $department;
        });
    }

    public function update(int $id, array $data): Department
    {
        return $this->executeInTransaction(function () use ($id, $data): Department {
            $department = $this->findById($id);

            if (isset($data['parent_id'])) {
                $this->validateParentId($department, (int) $data['parent_id']);
            }

            $oldName = $department->name;
            $department->update($data);

            if (isset($data['name']) && $data['name'] !== $oldName) {
                $this->defaultRoleService->syncDepartmentName($department->id, $data['name']);
            }

            return $department->refresh()->load('parent');
        });
    }

    public function delete(int $id): void
    {
        $this->executeInTransaction(function () use ($id): void {
            $department = $this->findById($id);

            if ($this->commissionConfigRepository->hasDeptRule($department->id)) {
                throw new BusinessException(
                    "Không thể xóa phòng ban {$department->name}: đang có cấu hình hoa hồng. Hãy xóa khỏi cấu hình hoa hồng trước.",
                    'DEPARTMENT_HAS_COMMISSION_CONFIG',
                    Response::HTTP_UNPROCESSABLE_ENTITY,
                );
            }

            $this->defaultRoleService->softDeleteByDepartment($department->id);

            $this->repository->reparentChildren($department->id);

            $department->delete();
        });
    }

    /**
     * @return list<int>
     */
    public function getDescendantIds(int $id): array
    {
        return $this->repository->getAllDescendantIds($id);
    }

    /**
     * Validate that parent_id does not create a circular reference.
     */
    protected function validateParentId(Department $department, int $parentId): void
    {
        if ($parentId === $department->id) {
            throw new BusinessException(
                'Phòng ban cha không được là chính nó.',
                'DEPARTMENT_CIRCULAR_REFERENCE',
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        $descendantIds = $this->repository->getAllDescendantIds($department->id);

        if (in_array($parentId, $descendantIds)) {
            throw new BusinessException(
                'Phòng ban cha không được là phòng ban con cháu.',
                'DEPARTMENT_CIRCULAR_REFERENCE',
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }
    }
}
