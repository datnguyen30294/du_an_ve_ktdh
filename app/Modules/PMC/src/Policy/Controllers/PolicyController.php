<?php

namespace App\Modules\PMC\Policy\Controllers;

use App\Common\Contracts\StorageServiceInterface;
use App\Common\Controllers\BaseController;
use App\Modules\PMC\Policy\Contracts\PolicyServiceInterface;
use App\Modules\PMC\Policy\Enums\PolicyType;
use App\Modules\PMC\Policy\Requests\UpdatePolicyRequest;
use App\Modules\PMC\Policy\Requests\UploadPolicyImageRequest;
use App\Modules\PMC\Policy\Resources\PolicyDetailResource;
use App\Modules\PMC\Policy\Resources\PolicyListResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/**
 * @tags Policies
 */
class PolicyController extends BaseController implements HasMiddleware
{
    public const IMAGE_DIRECTORY = 'policy-images';

    public function __construct(
        protected PolicyServiceInterface $service,
        protected StorageServiceInterface $storageService,
    ) {}

    /**
     * @return list<Middleware>
     */
    public static function middleware(): array
    {
        return [
            new Middleware('permission:policies.view', only: ['index', 'show']),
            new Middleware('permission:policies.update', only: ['update', 'uploadImage']),
        ];
    }

    /**
     * List all policies.
     *
     * @return array{success: bool, data: list<PolicyListResource>}
     */
    public function index(): JsonResponse
    {
        $policies = $this->service->getAll();

        return PolicyListResource::collection($policies)
            ->additional(['success' => true])
            ->response();
    }

    /**
     * Get a policy by type.
     */
    public function show(string $type): PolicyDetailResource|JsonResponse
    {
        $policyType = PolicyType::tryFrom($type);

        if (! $policyType) {
            return response()->json([
                'success' => false,
                'message' => 'Loại chính sách không hợp lệ.',
            ], 404);
        }

        $policy = $this->service->getByType($policyType);

        if (! $policy) {
            return response()->json([
                'success' => true,
                'data' => [
                    'type' => [
                        'value' => $policyType->value,
                        'label' => $policyType->label(),
                    ],
                    'title' => '',
                    'content' => '',
                    'is_published' => false,
                ],
            ]);
        }

        return new PolicyDetailResource($policy);
    }

    /**
     * Create or update a policy by type.
     */
    public function update(UpdatePolicyRequest $request, string $type): PolicyDetailResource|JsonResponse
    {
        $policyType = PolicyType::tryFrom($type);

        if (! $policyType) {
            return response()->json([
                'success' => false,
                'message' => 'Loại chính sách không hợp lệ.',
            ], 404);
        }

        $policy = $this->service->upsert($policyType, $request->validated());

        return new PolicyDetailResource($policy);
    }

    /**
     * Upload an image for policy content.
     *
     * @return array{success: bool, data: array{url: string}}
     */
    public function uploadImage(UploadPolicyImageRequest $request): JsonResponse
    {
        $file = $request->file('image');
        $path = $this->storageService->upload($file, self::IMAGE_DIRECTORY);
        $url = $this->storageService->getUrl($path);

        return response()->json([
            'success' => true,
            'data' => ['url' => $url],
        ]);
    }
}
