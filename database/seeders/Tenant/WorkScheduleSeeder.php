<?php

namespace Database\Seeders\Tenant;

use App\Modules\PMC\Account\Models\Account;
use App\Modules\PMC\Shift\Models\Shift;
use App\Modules\PMC\WorkSchedule\Models\WorkSchedule;
use Illuminate\Database\Seeder;

class WorkScheduleSeeder extends Seeder
{
    public function run(): void
    {
        if (WorkSchedule::query()->exists()) {
            return;
        }

        /** @var \Illuminate\Support\Collection<int, Account> $accounts */
        $accounts = Account::query()
            ->with('projects')
            ->where('employee_code', '!=', 'admin')
            ->orderBy('id')
            ->get();

        /** @var \Illuminate\Support\Collection<int, Shift> $shifts */
        $shifts = Shift::query()->orderBy('project_id')->orderBy('sort_order')->get();

        if ($accounts->isEmpty() || $shifts->isEmpty()) {
            return;
        }

        $shiftsByProject = $shifts->groupBy('project_id');
        $today = now()->startOfDay();

        foreach ($accounts as $accountIndex => $account) {
            $projects = $account->projects;
            if ($projects->isEmpty()) {
                continue;
            }

            foreach ($projects as $projectIndex => $project) {
                $projectShifts = $shiftsByProject->get($project->id);
                if (! $projectShifts || $projectShifts->isEmpty()) {
                    continue;
                }

                for ($dayOffset = 0; $dayOffset < 5; $dayOffset++) {
                    $shift = $projectShifts[($accountIndex + $dayOffset + $projectIndex) % $projectShifts->count()];
                    $date = $today->copy()->addDays($dayOffset)->format('Y-m-d');

                    WorkSchedule::query()->create([
                        'account_id' => $account->id,
                        'project_id' => $project->id,
                        'shift_id' => $shift->id,
                        'date' => $date,
                        'note' => null,
                        'external_ref' => sprintf(
                            'HR-WS-%s-%s-%s-%s',
                            $date,
                            $account->employee_code,
                            $project->code,
                            $shift->code,
                        ),
                    ]);
                }
            }
        }
    }
}
