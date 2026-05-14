<?php

namespace App\Modules\PMC\WorkSchedule\Controllers;

use App\Common\Controllers\BaseController;
use App\Modules\PMC\WorkSchedule\Contracts\ScheduleSlotServiceInterface;
use App\Modules\PMC\WorkSchedule\Requests\PersonalScheduleSlotRequest;
use App\Modules\PMC\WorkSchedule\Requests\ScheduleSlotDetailRequest;
use App\Modules\PMC\WorkSchedule\Requests\TeamScheduleSlotRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/**
 * @tags Schedule Slots
 */
class ScheduleSlotController extends BaseController implements HasMiddleware
{
    public function __construct(
        protected ScheduleSlotServiceInterface $service,
    ) {}

    /**
     * @return list<Middleware>
     */
    public static function middleware(): array
    {
        return [
            new Middleware('permission:schedule-slots.view'),
        ];
    }

    /**
     * Lịch cá nhân 1 nhân viên trong tháng.
     */
    public function personal(PersonalScheduleSlotRequest $request): JsonResponse
    {
        $data = $this->service->getPersonal(
            (int) $request->validated('account_id'),
            (string) $request->validated('month'),
        );

        return response()->json(['success' => true, 'data' => $data]);
    }

    /**
     * Lịch tất cả nhân viên (có thể lọc theo dự án) trong tháng.
     */
    public function team(TeamScheduleSlotRequest $request): JsonResponse
    {
        $accountIds = $request->validated('account_ids');
        $projectId = $request->validated('project_id');
        $data = $this->service->getTeam(
            (string) $request->validated('month'),
            $projectId !== null ? (int) $projectId : null,
            is_array($accountIds) ? array_map('intval', $accountIds) : null,
            (bool) $request->validated('strict_project', false),
        );

        return response()->json(['success' => true, 'data' => $data]);
    }

    /**
     * Drawer chi tiết 1 ca × 1 ngày × 1 nhân viên.
     */
    public function detail(ScheduleSlotDetailRequest $request): JsonResponse
    {
        $data = $this->service->getDetail(
            (int) $request->validated('account_id'),
            (string) $request->validated('date'),
            (int) $request->validated('shift_id'),
        );

        return response()->json(['success' => true, 'data' => $data]);
    }
}
