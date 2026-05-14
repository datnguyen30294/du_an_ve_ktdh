<?php

namespace App\Modules\PMC\Policy\Controllers;

use App\Common\Controllers\BaseController;
use App\Modules\PMC\Policy\Contracts\PolicyServiceInterface;
use App\Modules\PMC\Policy\Enums\PolicyType;
use Illuminate\Http\JsonResponse;

/**
 * @tags Public Policies
 */
class PublicPolicyController extends BaseController
{
    public function __construct(
        protected PolicyServiceInterface $service,
    ) {}

    /**
     * Get a published policy by type (public, no auth).
     */
    public function show(string $type): JsonResponse
    {
        $policyType = PolicyType::tryFrom($type);

        if (! $policyType) {
            return response()->json([
                'success' => false,
                'message' => 'Loại chính sách không hợp lệ.',
            ], 404);
        }

        $policy = $this->service->getPublishedByType($policyType);

        if (! $policy) {
            return response()->json([
                'success' => true,
                'data' => null,
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'title' => $policy->title,
                'content' => $policy->content,
                'updated_at' => $policy->updated_at,
            ],
        ]);
    }
}
