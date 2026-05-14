<?php

namespace App\Modules\Platform\ExternalApi\Controllers;

use App\Common\Controllers\BaseController;
use App\Modules\Platform\ExternalApi\Contracts\ApiClientServiceInterface;
use App\Modules\Platform\ExternalApi\Requests\CreateApiClientRequest;
use App\Modules\Platform\ExternalApi\Requests\ListApiClientRequest;
use App\Modules\Platform\ExternalApi\Requests\UpdateApiClientRequest;
use App\Modules\Platform\ExternalApi\Resources\ApiClientResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

/**
 * @tags API Clients
 */
class ApiClientController extends BaseController
{
    public function __construct(
        protected ApiClientServiceInterface $service,
    ) {}

    /**
     * List API clients.
     */
    public function index(ListApiClientRequest $request): AnonymousResourceCollection
    {
        return ApiClientResource::collection($this->service->list($request->validated()))
            ->additional(['success' => true]);
    }

    /**
     * Get API client detail.
     */
    public function show(string $id): ApiClientResource
    {
        return new ApiClientResource($this->service->findById($id));
    }

    /**
     * Create a new API client.
     */
    public function store(CreateApiClientRequest $request): JsonResponse
    {
        $result = $this->service->create($request->validated());

        return response()->json([
            'success' => true,
            'data' => new ApiClientResource($result['client']),
            'secret_key' => $result['secret_key'],
        ], Response::HTTP_CREATED);
    }

    /**
     * Update an API client.
     */
    public function update(UpdateApiClientRequest $request, string $id): ApiClientResource
    {
        return new ApiClientResource($this->service->update($id, $request->validated()));
    }

    /**
     * Delete an API client.
     */
    public function destroy(string $id): JsonResponse
    {
        $this->service->delete($id);

        return response()->json(['success' => true, 'message' => 'Đã xóa API client.']);
    }

    /**
     * Regenerate secret key.
     */
    public function regenerateSecret(string $id): JsonResponse
    {
        $result = $this->service->regenerateSecret($id);

        return response()->json([
            'success' => true,
            'data' => new ApiClientResource($result['client']),
            'secret_key' => $result['secret_key'],
        ]);
    }
}
