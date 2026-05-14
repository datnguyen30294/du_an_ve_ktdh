<?php

namespace Database\Seeders\Tenant;

use App\Modules\PMC\Account\Models\Account;
use App\Modules\PMC\Commission\Enums\CommissionPartyType;
use App\Modules\PMC\Commission\Enums\CommissionValueType;
use App\Modules\PMC\Commission\Models\CommissionAdjuster;
use App\Modules\PMC\Commission\Models\CommissionDeptRule;
use App\Modules\PMC\Commission\Models\CommissionPartyRule;
use App\Modules\PMC\Commission\Models\CommissionStaffRule;
use App\Modules\PMC\Commission\Models\ProjectCommissionConfig;
use App\Modules\PMC\Department\Models\Department;
use App\Modules\PMC\Project\Models\Project;
use Illuminate\Database\Seeder;

class CommissionSeeder extends Seeder
{
    /** @var list<int> */
    private array $usedDeptIds = [];

    /** @var list<int> */
    private array $usedAccountIds = [];

    public function run(): void
    {
        $projects = Project::query()->where('status', 'managing')->get();

        foreach ($projects as $project) {
            if (ProjectCommissionConfig::query()->where('project_id', $project->id)->exists()) {
                continue;
            }

            $this->usedDeptIds = [];
            $this->usedAccountIds = [];

            $this->seedForProject($project);
        }
    }

    private function seedForProject(Project $project): void
    {
        // 1. Ensure 2 departments belong to this project
        $dept1 = $this->ensureDepartmentForProject($project, 'Phòng Kỹ thuật (HH)');
        $dept2 = $this->ensureDepartmentForProject($project, 'Phòng Kế toán (HH)');

        // 2. Ensure accounts in each department, assigned to project
        $accounts1 = $this->ensureAccountsForDept($project, $dept1, 2);
        $accounts2 = $this->ensureAccountsForDept($project, $dept2, 1);

        if ($accounts1->isEmpty() || $accounts2->isEmpty()) {
            return;
        }

        // 3. Create config + party rules
        /** @var ProjectCommissionConfig $config */
        $config = ProjectCommissionConfig::query()->create([
            'project_id' => $project->id,
        ]);

        // Party rules (3 bên — platform handled externally)
        CommissionPartyRule::query()->create([
            'config_id' => $config->id,
            'party_type' => CommissionPartyType::OperatingCompany->value,
            'value_type' => CommissionValueType::Percent->value,
            'percent' => 35.00,
        ]);
        CommissionPartyRule::query()->create([
            'config_id' => $config->id,
            'party_type' => CommissionPartyType::BoardOfDirectors->value,
            'value_type' => CommissionValueType::Percent->value,
            'percent' => 20.00,
        ]);
        CommissionPartyRule::query()->create([
            'config_id' => $config->id,
            'party_type' => CommissionPartyType::Management->value,
            'value_type' => CommissionValueType::Percent->value,
            'percent' => 40.00,
        ]);

        // 4. Dept rule 1: Kỹ thuật — both (fixed + percent)
        /** @var CommissionDeptRule $deptRule1 */
        $deptRule1 = CommissionDeptRule::query()->create([
            'config_id' => $config->id,
            'department_id' => $dept1->id,
            'sort_order' => 1,
            'value_type' => CommissionValueType::Both->value,
            'percent' => 60.00,
            'value_fixed' => 50000.00,
        ]);

        CommissionStaffRule::query()->create([
            'dept_rule_id' => $deptRule1->id,
            'account_id' => $accounts1[0]->id,
            'sort_order' => 1,
            'value_type' => CommissionValueType::Both->value,
            'percent' => 60.00,
            'value_fixed' => 30000.00,
        ]);

        if ($accounts1->count() > 1) {
            CommissionStaffRule::query()->create([
                'dept_rule_id' => $deptRule1->id,
                'account_id' => $accounts1[1]->id,
                'sort_order' => 2,
                'value_type' => CommissionValueType::Percent->value,
                'percent' => 40.00,
                'value_fixed' => null,
            ]);
        }

        // 5. Dept rule 2: Kế toán — percent only
        /** @var CommissionDeptRule $deptRule2 */
        $deptRule2 = CommissionDeptRule::query()->create([
            'config_id' => $config->id,
            'department_id' => $dept2->id,
            'sort_order' => 2,
            'value_type' => CommissionValueType::Percent->value,
            'percent' => 40.00,
            'value_fixed' => null,
        ]);

        CommissionStaffRule::query()->create([
            'dept_rule_id' => $deptRule2->id,
            'account_id' => $accounts2[0]->id,
            'sort_order' => 1,
            'value_type' => CommissionValueType::Percent->value,
            'percent' => 100.00,
            'value_fixed' => null,
        ]);

        // 6. Create adjusters (unique account_ids)
        $adjusterIds = collect([$accounts1[0]->id, $accounts2[0]->id])->unique();

        foreach ($adjusterIds as $accountId) {
            CommissionAdjuster::query()->firstOrCreate([
                'project_id' => $project->id,
                'account_id' => $accountId,
            ]);
        }
    }

    /**
     * Ensure a department belongs to the project. Each call returns a different department.
     */
    private function ensureDepartmentForProject(Project $project, string $fallbackName): Department
    {
        // Try existing departments for this project (exclude already used)
        $dept = Department::query()
            ->where('project_id', $project->id)
            ->whereNotIn('id', $this->usedDeptIds)
            ->first();

        if ($dept) {
            $this->usedDeptIds[] = $dept->id;

            return $dept;
        }

        // Try unassigned department
        $dept = Department::query()
            ->whereNull('project_id')
            ->whereNotIn('id', $this->usedDeptIds)
            ->first();

        if ($dept) {
            $dept->update(['project_id' => $project->id]);
            $this->usedDeptIds[] = $dept->id;

            return $dept;
        }

        // Create new department
        /** @var Department $dept */
        $dept = Department::query()->create([
            'project_id' => $project->id,
            'code' => 'DEPT-HH-'.rand(100, 999),
            'name' => $fallbackName,
        ]);

        $this->usedDeptIds[] = $dept->id;

        return $dept;
    }

    /**
     * Ensure accounts in a department are assigned to the project.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Account>
     */
    private function ensureAccountsForDept(Project $project, Department $dept, int $count): \Illuminate\Database\Eloquent\Collection
    {
        // Get accounts already in this department AND in the project (exclude already used)
        $accounts = Account::query()
            ->byDepartment($dept->id)
            ->whereHas('projects', fn ($q) => $q->where('projects.id', $project->id))
            ->whereNotIn('id', $this->usedAccountIds)
            ->limit($count)
            ->get();

        if ($accounts->count() >= $count) {
            $this->usedAccountIds = array_merge($this->usedAccountIds, $accounts->pluck('id')->toArray());

            return $accounts;
        }

        // Get accounts in this department but not yet in the project
        $unassigned = Account::query()
            ->byDepartment($dept->id)
            ->whereNotIn('id', $this->usedAccountIds)
            ->whereDoesntHave('projects', fn ($q) => $q->where('projects.id', $project->id))
            ->limit($count - $accounts->count())
            ->get();

        foreach ($unassigned as $account) {
            $account->projects()->syncWithoutDetaching([$project->id]);
        }

        $accounts = $accounts->merge($unassigned);

        if ($accounts->count() >= $count) {
            $this->usedAccountIds = array_merge($this->usedAccountIds, $accounts->pluck('id')->toArray());

            return $accounts;
        }

        // Reassign accounts from other departments + add to project
        $remaining = $count - $accounts->count();
        $excludeIds = array_merge($this->usedAccountIds, $accounts->pluck('id')->toArray());

        $otherAccounts = Account::query()
            ->whereNotIn('id', $excludeIds)
            ->limit($remaining)
            ->get();

        foreach ($otherAccounts as $account) {
            $account->departments()->syncWithoutDetaching([$dept->id]);
            $account->projects()->syncWithoutDetaching([$project->id]);
        }

        $result = $accounts->merge($otherAccounts);
        $this->usedAccountIds = array_merge($this->usedAccountIds, $result->pluck('id')->toArray());

        return $result;
    }
}
