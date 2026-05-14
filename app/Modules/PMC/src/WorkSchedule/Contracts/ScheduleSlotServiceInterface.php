<?php

namespace App\Modules\PMC\WorkSchedule\Contracts;

interface ScheduleSlotServiceInterface
{
    /**
     * @return array<string, mixed>
     */
    public function getPersonal(int $accountId, string $month): array;

    /**
     * @param  list<int>|null  $accountIds
     * @return array<string, mixed>
     */
    public function getTeam(string $month, ?int $projectId, ?array $accountIds, bool $strictProject = false): array;

    /**
     * @return array<string, mixed>
     */
    public function getDetail(int $accountId, string $date, int $shiftId): array;
}
