<?php

namespace App\Modules\PMC\Setting\Controllers;

use App\Common\Controllers\BaseController;
use App\Common\Http\JsonResponseHelper;
use App\Modules\PMC\Setting\Contracts\SystemSettingServiceInterface;
use App\Modules\PMC\Setting\Requests\UpdateSystemSettingsRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @tags System Settings
 */
class SystemSettingController extends BaseController
{
    /**
     * Map setting group key to permission sub-module name.
     *
     * @var array<string, string>
     */
    private const GROUP_PERMISSION_MAP = [
        'og_ticket' => 'settings-sla',
        'bank_account' => 'settings-bank-account',
        'acceptance_report' => 'settings-acceptance-report',
    ];

    public function __construct(
        protected SystemSettingServiceInterface $service,
    ) {}

    /**
     * Get all settings for a group.
     *
     * @return array{success: bool, data: array<string, mixed>}
     */
    public function show(Request $request, string $group): JsonResponse
    {
        $this->authorizeGroup($request, $group, 'view');

        $settings = $this->service->getGroup($group);

        return response()->json([
            'success' => true,
            'data' => $settings,
        ]);
    }

    /**
     * Update settings for a group (batch upsert).
     *
     * @return array{success: bool, message: string}
     */
    public function update(UpdateSystemSettingsRequest $request, string $group): JsonResponse
    {
        $this->authorizeGroup($request, $group, 'update');

        $items = $request->validated('settings');

        $settings = [];
        foreach ($items as $item) {
            $settings[$item['key']] = $item['value'] ?? null;
        }

        $this->service->updateGroup($group, $settings);

        return response()->json([
            'success' => true,
            'message' => 'Cập nhật cài đặt thành công.',
        ]);
    }

    private function authorizeGroup(Request $request, string $group, string $action): void
    {
        $subModule = self::GROUP_PERMISSION_MAP[$group] ?? null;

        if ($subModule === null) {
            throw new HttpResponseException(JsonResponseHelper::error(
                message: 'Nhóm cài đặt không hợp lệ.',
                statusCode: Response::HTTP_NOT_FOUND,
                errorCode: 'INVALID_SETTING_GROUP',
            ));
        }

        $user = $request->user();

        if (! $user || ! $user->hasAnyPermission(["{$subModule}.{$action}"])) {
            throw new HttpResponseException(JsonResponseHelper::error(
                message: 'Bạn không có quyền thực hiện hành động này.',
                statusCode: Response::HTTP_FORBIDDEN,
                errorCode: 'FORBIDDEN',
            ));
        }
    }
}
