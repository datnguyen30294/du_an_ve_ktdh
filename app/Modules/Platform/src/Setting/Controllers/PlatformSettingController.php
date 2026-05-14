<?php

namespace App\Modules\Platform\Setting\Controllers;

use App\Common\Controllers\BaseController;
use App\Modules\Platform\Setting\Contracts\PlatformSettingServiceInterface;
use App\Modules\Platform\Setting\Requests\UpdatePlatformSettingsRequest;
use Illuminate\Http\JsonResponse;

/**
 * @tags Platform Settings
 */
class PlatformSettingController extends BaseController
{
    public function __construct(
        protected PlatformSettingServiceInterface $service,
    ) {}

    /**
     * Get all settings for a group.
     *
     * @return array{success: bool, data: array<string, mixed>}
     */
    public function show(string $group): JsonResponse
    {
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
    public function update(UpdatePlatformSettingsRequest $request, string $group): JsonResponse
    {
        $items = $request->validated('settings');

        $settings = [];
        foreach ($items as $item) {
            $settings[$item['key']] = $item['value'] ?? null;
        }

        $this->service->updateGroup($group, $settings);

        return response()->json([
            'success' => true,
            'message' => 'Cập nhật cài đặt Platform thành công.',
        ]);
    }
}
