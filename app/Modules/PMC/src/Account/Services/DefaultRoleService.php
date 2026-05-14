<?php

namespace App\Modules\PMC\Account\Services;

use App\Common\Services\BaseService;
use App\Modules\PMC\Account\Contracts\DefaultRoleServiceInterface;
use App\Modules\PMC\Account\Enums\RoleType;
use App\Modules\PMC\Account\Models\Role;
use App\Modules\PMC\Account\Repositories\RoleRepository;
use App\Modules\PMC\Department\Repositories\DepartmentRepository;
use App\Modules\PMC\JobTitle\Repositories\JobTitleRepository;

class DefaultRoleService extends BaseService implements DefaultRoleServiceInterface
{
    public function __construct(
        protected RoleRepository $roleRepository,
        protected DepartmentRepository $departmentRepository,
        protected JobTitleRepository $jobTitleRepository,
    ) {}

    /**
     * Generate a default role name from job title and department names.
     */
    public function generateName(string $jobTitleName, string $departmentName): string
    {
        return "{$jobTitleName} - {$departmentName}";
    }

    /**
     * Create a default role for a specific department + job title pair.
     *
     * If a role with the same generated name already exists, convert it to default.
     */
    public function createForPair(int $departmentId, int $jobTitleId): Role
    {
        return $this->executeInTransaction(function () use ($departmentId, $jobTitleId): Role {
            $department = $this->departmentRepository->findById($departmentId);
            $jobTitle = $this->jobTitleRepository->findById($jobTitleId);

            // Already exists as default for this pair
            $existing = $this->roleRepository->findDefaultForPair($departmentId, $jobTitleId);

            if ($existing) {
                return $existing;
            }

            $name = $this->generateName($jobTitle->name, $department->name);

            // A role with the same name exists — convert it to default
            $existingByName = $this->roleRepository->findByName($name);

            if ($existingByName) {
                $existingByName->update([
                    'type' => RoleType::Default,
                    'department_id' => $departmentId,
                    'job_title_id' => $jobTitleId,
                ]);

                return $existingByName;
            }

            /** @var Role */
            return $this->roleRepository->create([
                'name' => $name,
                'type' => RoleType::Default,
                'department_id' => $departmentId,
                'job_title_id' => $jobTitleId,
                'is_active' => true,
            ]);
        });
    }

    /**
     * Create default roles for a department paired with all existing job titles.
     */
    public function createRolesForDepartment(int $departmentId): void
    {
        $this->executeInTransaction(function () use ($departmentId): void {
            $jobTitleIds = $this->jobTitleRepository->pluckIds();

            foreach ($jobTitleIds as $jobTitleId) {
                $this->createForPair($departmentId, $jobTitleId);
            }
        });
    }

    /**
     * Create default roles for a job title paired with all existing departments.
     */
    public function createRolesForJobTitle(int $jobTitleId): void
    {
        $this->executeInTransaction(function () use ($jobTitleId): void {
            $departmentIds = $this->departmentRepository->pluckIds();

            foreach ($departmentIds as $departmentId) {
                $this->createForPair($departmentId, $jobTitleId);
            }
        });
    }

    /**
     * Update names of default roles associated with a department when it's renamed.
     */
    public function syncDepartmentName(int $departmentId, string $newName): void
    {
        $this->executeInTransaction(function () use ($departmentId, $newName): void {
            $roles = $this->roleRepository->getDefaultsByDepartment($departmentId);

            foreach ($roles as $role) {
                $role->update([
                    'name' => $this->generateName($role->jobTitle->name, $newName),
                ]);
            }
        });
    }

    /**
     * Update names of default roles associated with a job title when it's renamed.
     */
    public function syncJobTitleName(int $jobTitleId, string $newName): void
    {
        $this->executeInTransaction(function () use ($jobTitleId, $newName): void {
            $roles = $this->roleRepository->getDefaultsByJobTitle($jobTitleId);

            foreach ($roles as $role) {
                $role->update([
                    'name' => $this->generateName($newName, $role->department->name),
                ]);
            }
        });
    }

    /**
     * Soft-delete all default roles associated with a department.
     */
    public function softDeleteByDepartment(int $departmentId): void
    {
        $this->executeInTransaction(function () use ($departmentId): void {
            $this->roleRepository->softDeleteDefaultsByDepartment($departmentId);
        });
    }

    /**
     * Soft-delete all default roles associated with a job title.
     */
    public function softDeleteByJobTitle(int $jobTitleId): void
    {
        $this->executeInTransaction(function () use ($jobTitleId): void {
            $this->roleRepository->softDeleteDefaultsByJobTitle($jobTitleId);
        });
    }
}
