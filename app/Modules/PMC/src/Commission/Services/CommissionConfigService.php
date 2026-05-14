<?php

namespace App\Modules\PMC\Commission\Services;

use App\Common\Services\BaseService;
use App\Modules\PMC\Commission\Contracts\CommissionConfigServiceInterface;
use App\Modules\PMC\Commission\Models\ProjectCommissionConfig;
use App\Modules\PMC\Commission\Repositories\CommissionConfigRepository;
use App\Modules\PMC\Project\Models\Project;
use App\Modules\PMC\Project\Repositories\ProjectRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class CommissionConfigService extends BaseService implements CommissionConfigServiceInterface
{
    public function __construct(
        protected CommissionConfigRepository $repository,
        protected ProjectRepository $projectRepository,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function listProjects(array $filters): LengthAwarePaginator
    {
        return $this->repository->listProjects($filters);
    }

    /**
     * @return array{project: Project, config: ProjectCommissionConfig|null, adjusters: Collection}
     */
    public function getConfigDetail(int $projectId): array
    {
        $project = $this->projectRepository->findById($projectId);
        $config = $this->repository->findByProject($projectId);
        $adjusters = $this->repository->getAdjusters($projectId);

        return [
            'project' => $project,
            'config' => $config,
            'adjusters' => $adjusters,
        ];
    }

    public function getConfigByProject(int $projectId): ?ProjectCommissionConfig
    {
        return $this->repository->findByProject($projectId);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function saveConfig(int $projectId, array $data): ProjectCommissionConfig
    {
        return $this->executeInTransaction(function () use ($projectId, $data): ProjectCommissionConfig {
            $config = $this->repository->upsertConfig($projectId);

            // Replace party rules
            $this->repository->deletePartyRules($config->id);

            foreach ($data['party_rules'] as $partyRuleData) {
                $this->repository->createPartyRule([
                    'config_id' => $config->id,
                    'party_type' => $partyRuleData['party_type'],
                    'value_type' => $partyRuleData['value_type'],
                    'percent' => $partyRuleData['percent'] ?? null,
                    'value_fixed' => $partyRuleData['value_fixed'] ?? null,
                ]);
            }

            // Replace dept rules (cascade deletes staff rules via FK)
            $this->repository->deleteDeptRules($config->id);

            foreach ($data['dept_rules'] ?? [] as $deptRuleData) {
                $deptRule = $this->repository->createDeptRule([
                    'config_id' => $config->id,
                    'department_id' => $deptRuleData['department_id'],
                    'sort_order' => $deptRuleData['sort_order'],
                    'value_type' => $deptRuleData['value_type'],
                    'percent' => $deptRuleData['percent'] ?? null,
                    'value_fixed' => $deptRuleData['value_fixed'] ?? null,
                ]);

                foreach ($deptRuleData['staff_rules'] as $staffRuleData) {
                    $this->repository->createStaffRule([
                        'dept_rule_id' => $deptRule->id,
                        'account_id' => $staffRuleData['account_id'],
                        'sort_order' => $staffRuleData['sort_order'],
                        'value_type' => $staffRuleData['value_type'],
                        'percent' => $staffRuleData['percent'] ?? null,
                        'value_fixed' => $staffRuleData['value_fixed'] ?? null,
                    ]);
                }
            }

            return $this->repository->findByProject($projectId);
        });
    }

    public function getAdjusters(int $projectId): Collection
    {
        return $this->repository->getAdjusters($projectId);
    }

    /**
     * @param  array<int>  $accountIds
     */
    public function saveAdjusters(int $projectId, array $accountIds): Collection
    {
        return $this->executeInTransaction(function () use ($projectId, $accountIds): Collection {
            return $this->repository->syncAdjusters($projectId, $accountIds);
        });
    }

    public function getAvailableDepartments(int $projectId): Collection
    {
        $this->projectRepository->findById($projectId);

        return $this->repository->getAvailableDepartments($projectId);
    }
}
