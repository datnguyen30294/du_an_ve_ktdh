<?php

namespace App\Modules\PMC\Account\Contracts;

use App\Modules\PMC\Account\Models\Role;

interface DefaultRoleServiceInterface
{
    public function generateName(string $jobTitleName, string $departmentName): string;

    public function createForPair(int $departmentId, int $jobTitleId): Role;

    public function createRolesForDepartment(int $departmentId): void;

    public function createRolesForJobTitle(int $jobTitleId): void;

    public function syncDepartmentName(int $departmentId, string $newName): void;

    public function syncJobTitleName(int $jobTitleId, string $newName): void;

    public function softDeleteByDepartment(int $departmentId): void;

    public function softDeleteByJobTitle(int $jobTitleId): void;
}
