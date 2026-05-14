<?php

namespace Database\Seeders\Tenant;

use App\Modules\PMC\Project\Models\Project;
use App\Modules\PMC\Shift\Models\Shift;
use Illuminate\Database\Seeder;

class ShiftSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            ['code' => 'SANG', 'name' => 'Ca sáng', 'type' => 'Cả tuần', 'work_group' => 'Làm việc', 'start_time' => '06:00', 'end_time' => '14:00', 'break_hours' => 1.0, 'status' => 'active', 'sort_order' => 1],
            ['code' => 'CHIEU', 'name' => 'Ca chiều', 'type' => 'Cả tuần', 'work_group' => 'Làm việc', 'start_time' => '14:00', 'end_time' => '22:00', 'break_hours' => 1.0, 'status' => 'active', 'sort_order' => 2],
            ['code' => 'TOI', 'name' => 'Ca tối', 'type' => 'Cả tuần', 'work_group' => 'Làm việc', 'start_time' => '22:00', 'end_time' => '06:00', 'break_hours' => 1.0, 'status' => 'active', 'sort_order' => 3],
        ];

        Project::query()->each(function (Project $project) use ($templates): void {
            foreach ($templates as $data) {
                Shift::query()->updateOrCreate(
                    ['project_id' => $project->id, 'code' => $data['code']],
                    $data + ['project_id' => $project->id],
                );
            }
        });
    }
}
